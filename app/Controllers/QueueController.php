<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\District;
use App\Models\Location;
use App\Models\Queue;
use App\Models\Service;
use App\Models\User;
use App\Services\QueueService;
use App\Services\UploadService;
use RuntimeException;
use Throwable;

/**
 * Pengambilan antrean. Tidak memerlukan akun: warga yang belum masuk mengisi
 * identitasnya di formulir, dan sistem membuatkan akun tamu tanpa sandi yang
 * bisa diklaim kemudian lewat halaman daftar.
 */
final class QueueController extends Controller
{
    /**
     * @param array<string, string> $params
     */
    public function create(Request $request, array $params): Response
    {
        $location = Location::findBySlug($params['slug']);

        if ($location === null) {
            return $this->abort($request, 404, 'Lokasi layanan tidak ditemukan.');
        }

        $locationId = (int) $location['id'];
        $account = Auth::is('user') ? Auth::user() : null;

        // Halaman sukses tampil sekali lewat flash (redirect-after-post),
        // jadi refresh tidak mengirim ulang formulir.
        $code = Session::getFlash('queue_code');

        if (is_string($code) && $code !== '') {
            $queue = Queue::findByCode($code);

            if ($queue !== null) {
                return $this->view('queue/success', [
                    'title' => 'Antrean tersimpan',
                    'queue' => $queue,
                    'documents' => Queue::documents((int) $queue['id']),
                    'guest' => $account === null,
                ]);
            }
        }

        $viewData = [
            'title' => 'Daftar antrean · ' . $location['name'],
            'location' => $location,
            'services' => Service::withRequirements($locationId),
            'dates' => QueueService::availableDates($locationId),
            'slots' => (array) config('app.queue.slots', []),
            'user' => $account,
            'districts' => District::all(),
            'scripts' => ['queue.js'],
        ];

        if (!$request->isPost()) {
            return $this->view('queue/create', $viewData);
        }

        $back = '/lokasi/' . $location['slug'] . '/daftar';
        $maxDays = (int) config('app.queue.max_days_ahead', 30);

        /*
         * Sesi waktu dicocokkan ke daftar yang ditawarkan, bukan sekadar
         * "wajib diisi". Kolomnya bertipe TIME dan MySQL di sini tidak
         * berjalan ketat: teks sembarang dulu tersimpan diam-diam sebagai
         * 00:00:00, dan warga diberi kode antrean untuk pukul dua belas
         * malam.
         */
        $sesi = implode(',', array_keys((array) config('app.queue.slots', [])));

        $rules = [
            'service_id' => 'required|numeric',
            'queue_date' => 'required|date|date_min:today|date_max:+' . $maxDays . ' days',
            'slot_time' => 'required|in:' . $sesi,
            'note_user' => 'max:2000',
        ];

        $labels = [
            'service_id' => 'Layanan',
            'queue_date' => 'Tanggal kedatangan',
            'slot_time' => 'Sesi waktu',
            'note_user' => 'Catatan',
            'nama' => 'Nama lengkap',
            'email' => 'Email',
            'telepon' => 'Nomor ponsel',
            'nik' => 'NIK',
        ];

        // Tanpa akun, identitas pemohon wajib diisi di formulir.
        if ($account === null) {
            $rules += [
                'nama' => 'required|max:120',
                'email' => 'required|email|max:160',
                'telepon' => 'required|phone',
                'nik' => 'digits:16',
            ];
        }

        $validator = Validator::make($request->all(), $rules, $labels);

        if ($validator->fails()) {
            return $this->backWithErrors($back, $validator->errors(), $request->all());
        }

        $serviceId = $request->int('service_id');
        $service = Service::findForLocation($serviceId, $locationId);

        if ($service === null) {
            return $this->backWithErrors($back, [
                'service_id' => 'Layanan yang dipilih tidak tersedia di lokasi ini.',
            ], $request->all());
        }

        $missing = $this->missingUploads($serviceId, $request);

        if ($missing !== []) {
            return $this->backWithErrors($back, [
                'berkas' => 'Berkas berikut wajib diunggah: ' . implode(', ', $missing) . '.',
            ], $request->all());
        }

        try {
            $userId = $account !== null
                ? (int) $account['id']
                : User::findOrCreateGuest([
                    'name' => $request->str('nama'),
                    'email' => $request->str('email'),
                    'phone' => $request->str('telepon'),
                    'nik' => $request->str('nik'),
                    'district_id' => $request->int('district_id') > 0 ? $request->int('district_id') : null,
                ]);

            /*
             * Hanya syarat milik layanan yang dipilih yang diterima.
             * Tanpa daftar ini, lampiran bisa dikirim atas nama syarat
             * layanan kantor lain — berkasnya tersimpan dan muncul di
             * halaman petugas dengan label yang bukan miliknya.
             */
            $diizinkan = array_map(
                static fn (array $r): int => (int) $r['id'],
                Service::requirements($serviceId)
            );

            $files = UploadService::collect($request->file('berkas'), $diizinkan);
        } catch (Throwable $e) {
            return $this->backWithErrors($back, ['berkas' => $e->getMessage()], $request->all());
        }

        try {
            $result = QueueService::create($locationId, $userId, [
                'service_id' => $serviceId,
                'queue_date' => $request->str('queue_date'),
                'slot_time' => $request->str('slot_time'),
                'note_user' => $request->str('note_user') !== '' ? $request->str('note_user') : null,
            ], $files);
        } catch (RuntimeException $e) {
            // Pesan buatan sendiri — aman ditunjukkan ke warga.
            UploadService::discard($files);

            return $this->backWithErrors($back, ['queue_date' => $e->getMessage()], $request->all());
        } catch (Throwable $e) {
            /*
             * Apa pun selain itu (galat basis data, dan sebagainya) tidak
             * pernah ditampilkan apa adanya: pesannya dulu memuat nama
             * tabel dan nama constraint, dan halaman ini terbuka untuk
             * siapa saja tanpa akun.
             */
            UploadService::discard($files);
            error_log('[antrean] gagal membuat antrean di lokasi ' . $locationId . ': ' . $e->getMessage());

            return $this->backWithErrors(
                $back,
                ['queue_date' => 'Antrean gagal disimpan. Coba lagi beberapa saat lagi.'],
                $request->all()
            );
        }

        Session::flash('queue_code', $result['code']);

        return $this->redirectVia(
            $back,
            'Antrean berhasil dibuat',
            'Kode antrean Anda sedang disiapkan…'
        );
    }

    /**
     * @return array<int, string>
     */
    private function missingUploads(int $serviceId, Request $request): array
    {
        $uploaded = $request->file('berkas');
        $errors = is_array($uploaded) && isset($uploaded['error']) && is_array($uploaded['error'])
            ? $uploaded['error']
            : [];

        $missing = [];

        foreach (Service::requirements($serviceId) as $requirement) {
            if ((int) $requirement['is_mandatory'] !== 1 || (int) $requirement['needs_upload'] !== 1) {
                continue;
            }

            if ((int) ($errors[(int) $requirement['id']] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $missing[] = (string) $requirement['label'];
            }
        }

        return $missing;
    }
}
