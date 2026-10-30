<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\CodeGenerator;
use App\Core\DB;
use App\Models\Location;
use App\Models\Service;
use RuntimeException;

/**
 * Semua perubahan pada antrean lewat kelas ini, supaya kuota, nomor urut,
 * dan riwayat status tidak pernah ditulis setengah jalan.
 */
final class QueueService
{
    /**
     * Membuat antrean baru. Nomor urut diambil di dalam transaksi dengan
     * SELECT ... FOR UPDATE agar dua pemohon pada detik yang sama tidak
     * mendapat nomor yang sama atau menembus kuota.
     *
     * @param array<string, mixed> $input  service_id, queue_date, slot_time, note_user
     * @param array<string, mixed> $files  hasil UploadService::collect()
     * @return array{code: string, number: int, queue_id: int}
     */
    public static function create(int $locationId, int $userId, array $input, array $files = []): array
    {
        $service = Service::findForLocation((int) $input['service_id'], $locationId);

        if ($service === null) {
            throw new RuntimeException('Layanan tidak tersedia di lokasi ini.');
        }

        $location = Location::find($locationId);

        if ($location === null) {
            throw new RuntimeException('Lokasi tidak ditemukan.');
        }

        $date = (string) $input['queue_date'];

        /*
         * Hari tutup ditolak di sini, bukan hanya disamarkan di formulir.
         *
         * Formulir memang menandai hari tutup dan menonaktifkan tombolnya,
         * tetapi itu hanya tampilan: permintaan POST yang menyebut tanggal
         * mana pun tetap diterima, dan warga mendapat kode antrean untuk
         * hari kantornya libur.
         */
        if (!self::isOpenOn($location, $date)) {
            throw new RuntimeException('Kantor ini tutup pada tanggal tersebut. Silakan pilih tanggal lain.');
        }

        return DB::transaction(static function () use ($location, $service, $userId, $input, $files, $date): array {
            $quota = (int) $location['quota_per_day'];

            $used = (int) DB::column(
                "SELECT COUNT(*) FROM queues
                  WHERE location_id = ? AND queue_date = ? AND status <> 'dibatalkan'
                  FOR UPDATE",
                [$location['id'], $date]
            );

            if ($used >= $quota) {
                throw new RuntimeException('Kuota tanggal tersebut sudah penuh. Silakan pilih tanggal lain.');
            }

            $number = (int) DB::column(
                'SELECT COALESCE(MAX(queue_number), 0) + 1 FROM queues
                  WHERE location_id = ? AND queue_date = ?',
                [$location['id'], $date]
            );

            /*
             * NULL berbeda dari 0. 0 berarti layanannya memang gratis menurut
             * peraturan; NULL berarti tarifnya belum dikonfirmasi ke instansi.
             * Keduanya dulu sama-sama jatuh ke "tidak perlu bayar" — janji
             * yang tidak bisa ditepati aplikasi ini untuk tarif yang belum
             * pasti.
             */
            $feeMentah = $service['fee'];
            $fee = $feeMentah === null ? null : (float) $feeMentah;

            $statusBayar = match (true) {
                $fee === null => 'belum_pasti',
                $fee > 0 => 'menunggu',
                default => 'tidak_perlu',
            };

            $code = CodeGenerator::uniqueQueueCode();

            $queueId = DB::insert('queues', [
                'code' => $code,
                'location_id' => (int) $location['id'],
                'service_id' => (int) $service['id'],
                'user_id' => $userId,
                'queue_date' => $date,
                'queue_number' => $number,
                'slot_time' => $input['slot_time'] ?? null,
                'status' => 'baru',
                'note_user' => $input['note_user'] ?? null,
                'payment_status' => $statusBayar,
            ]);

            foreach ($files as $file) {
                DB::insert('queue_documents', [
                    'queue_id' => $queueId,
                    'requirement_id' => $file['requirement_id'],
                    'file_path' => $file['path'],
                    'original_name' => $file['original_name'],
                    'mime' => $file['mime'],
                    'size_bytes' => $file['size'],
                ]);
            }

            if ($fee !== null && $fee > 0) {
                DB::insert('payments', [
                    'queue_id' => $queueId,
                    'amount' => $fee,
                    'method' => 'tunai',
                    'status' => 'menunggu',
                ]);
            }

            self::log($queueId, null, 'baru', $userId, 'Permohonan dibuat oleh warga');

            return ['code' => $code, 'number' => $number, 'queue_id' => $queueId];
        });
    }

    /**
     * Pindah status dengan tiga lapis pemeriksaan: transisi sah, peran
     * berwenang, dan kepemilikan antrean.
     *
     * @param array<string, mixed> $queue
     */
    public static function changeStatus(array $queue, string $to, int $actorId, string $actorRole, ?string $note = null): void
    {
        $from = (string) $queue['status'];
        $allowed = (array) config('roles.transitions.' . $from, []);

        if (!in_array($to, $allowed, true)) {
            throw new RuntimeException('Perubahan status dari "' . $from . '" ke "' . $to . '" tidak diizinkan.');
        }

        $roles = (array) config('roles.transition_roles.' . $to, []);

        if (!in_array($actorRole, $roles, true)) {
            throw new RuntimeException('Peran Anda tidak berwenang mengubah status ke "' . $to . '".');
        }

        // Warga hanya boleh membatalkan antreannya sendiri.
        if ($actorRole === 'user' && (int) $queue['user_id'] !== $actorId) {
            throw new RuntimeException('Antrean ini bukan milik Anda.');
        }

        // Petugas hanya boleh menyentuh antrean yang ia klaim, kecuali saat
        // mengklaim antrean yang masih berstatus baru.
        if ($actorRole === 'officer' && $from !== 'baru' && (int) ($queue['officer_id'] ?? 0) !== $actorId) {
            throw new RuntimeException('Antrean ini ditangani petugas lain.');
        }

        DB::transaction(static function () use ($queue, $from, $to, $actorId, $actorRole, $note): void {
            $data = ['status' => $to];

            if ($to === 'ditinjau') {
                $data['officer_id'] = $actorRole === 'officer' ? $actorId : ($queue['officer_id'] ?? null);
                $data['accepted_at'] = date('Y-m-d H:i:s');
            }

            if ($to === 'selesai') {
                $data['finished_at'] = date('Y-m-d H:i:s');
            }

            /*
             * Status lama ikut jadi syarat UPDATE, bukan hanya id.
             *
             * $queue dibaca saat halaman dibuka, lalu baru dipakai di sini.
             * Di antara keduanya petugas lain bisa sudah mengklaim antrean
             * yang sama — tanpa syarat ini, yang menekan belakangan menimpa
             * officer_id dan accepted_at milik yang pertama tanpa pesan
             * apa pun, dan antreannya hilang dari papan petugas pertama.
             */
            $terubah = DB::update(
                'queues',
                $data,
                'id = :id AND status = :dari',
                ['id' => $queue['id'], 'dari' => $from]
            );

            // rowCount aman di sini: status selalu berubah (from ≠ to menurut
            // config roles.transitions), jadi 0 hanya berarti barisnya sudah
            // tidak berstatus $from lagi.
            if ($terubah === 0) {
                throw new RuntimeException(
                    'Antrean ini baru saja diubah orang lain. Muat ulang halamannya lalu coba lagi.'
                );
            }

            self::log((int) $queue['id'], $from, $to, $actorId, $note);
        });
    }

    /**
     * @param array<string, mixed> $queue
     */
    public static function assignOfficer(array $queue, int $officerId, int $actorId): void
    {
        $officer = DB::first(
            "SELECT id, name FROM users
              WHERE id = ? AND role = 'officer' AND location_id = ? LIMIT 1",
            [$officerId, $queue['location_id']]
        );

        if ($officer === null) {
            throw new RuntimeException('Petugas tidak terdaftar di lokasi ini.');
        }

        DB::transaction(static function () use ($queue, $officer, $actorId): void {
            $data = ['officer_id' => (int) $officer['id']];

            if ((string) $queue['status'] === 'baru') {
                $data['status'] = 'ditinjau';
                $data['accepted_at'] = date('Y-m-d H:i:s');
            }

            /*
             * Statusnya dibaca ulang di dalam transaksi, bukan diandalkan
             * dari rowCount seperti di changeStatus: menugaskan petugas yang
             * sama dua kali tidak mengubah satu kolom pun, dan rowCount 0 di
             * situ berarti "tidak ada yang berubah", bukan "baris hilang".
             */
            $sekarang = DB::column(
                'SELECT status FROM queues WHERE id = ? FOR UPDATE',
                [$queue['id']]
            );

            if ((string) $sekarang !== (string) $queue['status']) {
                throw new RuntimeException(
                    'Antrean ini baru saja diubah orang lain. Muat ulang halamannya lalu coba lagi.'
                );
            }

            self::log(
                (int) $queue['id'],
                (string) $queue['status'],
                (string) ($data['status'] ?? $queue['status']),
                $actorId,
                'Ditugaskan kepada ' . $officer['name']
            );
        });
    }

    /**
     * @param array<string, mixed> $queue
     */
    public static function saveOfficerNote(array $queue, string $note, int $actorId): void
    {
        DB::update('queues', ['note_officer' => $note], 'id = :id', ['id' => $queue['id']]);

        self::log((int) $queue['id'], (string) $queue['status'], (string) $queue['status'], $actorId, 'Catatan penanganan diperbarui');
    }

    public static function log(int $queueId, ?string $from, string $to, ?int $actorId, ?string $note = null): void
    {
        DB::insert('queue_status_logs', [
            'queue_id' => $queueId,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actorId,
            'note' => $note === null ? null : mb_substr($note, 0, 255),
        ]);
    }

    /**
     * Tanggal yang masih boleh dipilih warga, dengan sisa kuotanya.
     *
     * @return array<int, array{date: string, label: string, left: int, quota: int, closed: bool}>
     */
    /**
     * Apakah kantor buka pada satu tanggal, menurut locations.open_hours.
     *
     * @param array<string, mixed> $location
     */
    public static function isOpenOn(array $location, string $date): bool
    {
        $jam = json_decode((string) ($location['open_hours'] ?? ''), true);

        if (!is_array($jam)) {
            return false;
        }

        $hari = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'][(int) date('N', (int) strtotime($date)) - 1];
        $buka = $jam[$hari] ?? null;

        return is_array($buka) && count($buka) === 2;
    }

    public static function availableDates(int $locationId): array
    {
        return Location::upcomingQuota($locationId, 7);
    }
}
