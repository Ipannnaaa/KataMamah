<?php

declare(strict_types=1);

/**
 * Pengisi data demo untuk menguji rekap harian/mingguan/bulanan.
 *
 * Membuat ~60 antrean tambahan di Dinas Dukcapil Sentolo (lokasi 6) yang
 * tersebar pada 30 hari terakhir, lengkap dengan riwayat status dan
 * pembayaran. Dijalankan SETELAH schema.sql dan seed.sql.
 *
 *   php database/seed-demo.php
 *
 * Aman dijalankan berulang: setiap eksekusi menambah antrean baru dengan
 * kode berbeda, dan nomor urut melanjutkan nomor terakhir pada tanggal itu.
 */

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\CodeGenerator;
use App\Core\DB;

$locationId = 6;
$services = DB::all('SELECT id, fee FROM services WHERE location_id = ?', [$locationId]);
$officers = DB::all("SELECT id FROM users WHERE role = 'officer' AND location_id = ?", [$locationId]);
$citizens = DB::all("SELECT id FROM users WHERE role = 'user'");

if ($services === [] || $officers === [] || $citizens === []) {
    fwrite(STDERR, "Data referensi belum ada. Jalankan schema.sql lalu seed.sql terlebih dahulu.\n");
    exit(1);
}

$slots = array_keys((array) config('app.queue.slots', ['09:00' => '']));
$dibuat = 0;

for ($dayAgo = 30; $dayAgo >= 1; $dayAgo--) {
    $date = date('Y-m-d', strtotime('-' . $dayAgo . ' day'));
    $dow = (int) date('N', strtotime($date));

    // Sabtu dan Minggu tutup.
    if ($dow >= 6) {
        continue;
    }

    $jumlah = random_int(1, 3);

    for ($i = 0; $i < $jumlah; $i++) {
        $service = $services[array_rand($services)];
        $officer = $officers[array_rand($officers)];
        $citizen = $citizens[array_rand($citizens)];
        $fee = (float) $service['fee'];

        // 80% selesai, 10% dibatalkan, 10% masih ditangani.
        $roll = random_int(1, 10);
        $status = $roll <= 8 ? 'selesai' : ($roll === 9 ? 'dibatalkan' : 'ditangani');

        $createdAt = $date . ' ' . sprintf('%02d:%02d:00', random_int(7, 14), random_int(0, 59));
        $acceptedAt = date('Y-m-d H:i:s', strtotime($createdAt) + random_int(5, 40) * 60);
        $finishedAt = date('Y-m-d H:i:s', strtotime($acceptedAt) + random_int(10, 70) * 60);

        $number = 1 + (int) DB::column(
            'SELECT COALESCE(MAX(queue_number), 0) FROM queues WHERE location_id = ? AND queue_date = ?',
            [$locationId, $date]
        );

        $code = CodeGenerator::uniqueQueueCode();

        $paymentStatus = 'tidak_perlu';

        if ($fee > 0) {
            $paymentStatus = $status === 'selesai' ? 'lunas' : 'menunggu';
        }

        $queueId = DB::insert('queues', [
            'code' => $code,
            'location_id' => $locationId,
            'service_id' => (int) $service['id'],
            'user_id' => (int) $citizen['id'],
            'officer_id' => $status === 'dibatalkan' ? null : (int) $officer['id'],
            'queue_date' => $date,
            'queue_number' => $number,
            'slot_time' => $slots[array_rand($slots)],
            'status' => $status,
            'payment_status' => $paymentStatus,
            'accepted_at' => $status === 'dibatalkan' ? null : $acceptedAt,
            'finished_at' => $status === 'selesai' ? $finishedAt : null,
            'created_at' => $createdAt,
        ]);

        DB::insert('queue_status_logs', [
            'queue_id' => $queueId,
            'from_status' => null,
            'to_status' => 'baru',
            'actor_id' => (int) $citizen['id'],
            'note' => 'Permohonan dibuat oleh warga',
            'created_at' => $createdAt,
        ]);

        if ($status === 'dibatalkan') {
            DB::insert('queue_status_logs', [
                'queue_id' => $queueId,
                'from_status' => 'baru',
                'to_status' => 'dibatalkan',
                'actor_id' => (int) $citizen['id'],
                'note' => 'Dibatalkan oleh warga',
                'created_at' => $acceptedAt,
            ]);
        } else {
            DB::insert('queue_status_logs', [
                'queue_id' => $queueId,
                'from_status' => 'baru',
                'to_status' => 'ditinjau',
                'actor_id' => (int) $officer['id'],
                'note' => 'Antrean diterima petugas',
                'created_at' => $acceptedAt,
            ]);

            DB::insert('queue_status_logs', [
                'queue_id' => $queueId,
                'from_status' => 'ditinjau',
                'to_status' => 'ditangani',
                'actor_id' => (int) $officer['id'],
                'note' => null,
                'created_at' => $acceptedAt,
            ]);

            if ($status === 'selesai') {
                DB::insert('queue_status_logs', [
                    'queue_id' => $queueId,
                    'from_status' => 'ditangani',
                    'to_status' => 'selesai',
                    'actor_id' => (int) $officer['id'],
                    'note' => 'Permohonan selesai',
                    'created_at' => $finishedAt,
                ]);
            }
        }

        if ($fee > 0) {
            DB::insert('payments', [
                'queue_id' => $queueId,
                'amount' => $fee,
                'method' => 'tunai',
                'status' => $paymentStatus === 'lunas' ? 'lunas' : 'menunggu',
                'paid_at' => $paymentStatus === 'lunas' ? $finishedAt : null,
                'verified_by' => $paymentStatus === 'lunas' ? 2 : null,
                'created_at' => $createdAt,
            ]);
        }

        $dibuat++;
    }
}

echo $dibuat, " antrean demo dibuat untuk lokasi #", $locationId, ".\n";
echo "Buka /admin/rekap untuk melihat hasilnya.\n";
