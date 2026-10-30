<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\District;
use App\Models\FacilityReport;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Queue;
use App\Models\Service;
use App\Models\User;
use App\Services\PhotoService;
use App\Services\QueueService;
use App\Services\RecapService;
use Throwable;

final class AdminController extends Controller
{
    public function index(Request $request): Response
    {
        $locationId = $this->scope();
        $today = date('Y-m-d');

        return $this->view('admin/index', [
            'title' => 'Ringkasan hari ini',
            'focus' => 'ringkasan',
            'location' => $this->scopeLocation($locationId),
            'counts' => Queue::countsByStatus($locationId, $today),
            'summary' => RecapService::summary($locationId, 'hari'),
            'perHour' => RecapService::perHour($locationId),
            'perDay' => RecapService::perDay($locationId, date('Y-m-d', strtotime('-6 days')), $today),
            'queues' => Queue::forLocation($locationId, null, null, 15),
            'payments' => Payment::forLocation($locationId),
            'paymentTotals' => Payment::totalsToday($locationId),
            'officers' => User::officersAt($locationId),
            'byService' => RecapService::byService($locationId, 'hari'),
            'filter' => '',
            'period' => 'hari',
        ], 'panel');
    }

    public function queues(Request $request): Response
    {
        $locationId = $this->scope();
        $filter = $request->str('status');

        if ($filter !== '' && !array_key_exists($filter, (array) config('roles.status', []))) {
            $filter = '';
        }

        // Halaman antrean memakai view yang sama dengan fokus berbeda.
        return $this->view('admin/index', [
            'title' => 'Informasi antrean',
            'focus' => 'antrean',
            'location' => $this->scopeLocation($locationId),
            'counts' => Queue::countsByStatus($locationId, date('Y-m-d')),
            'summary' => RecapService::summary($locationId, 'hari'),
            'perHour' => RecapService::perHour($locationId),
            'perDay' => [],
            'queues' => Queue::forLocation($locationId, $filter !== '' ? $filter : null, $request->str('tanggal') ?: null, 200),
            'payments' => [],
            'paymentTotals' => Payment::totalsToday($locationId),
            'officers' => User::officersAt($locationId),
            'byService' => [],
            'filter' => $filter,
            'period' => 'hari',
        ], 'panel');
    }

    public function payments(Request $request): Response
    {
        $locationId = $this->scope();

        return $this->view('admin/index', [
            'title' => 'Informasi pembayaran',
            'focus' => 'pembayaran',
            'location' => $this->scopeLocation($locationId),
            'counts' => Queue::countsByStatus($locationId, date('Y-m-d')),
            'summary' => RecapService::summary($locationId, 'hari'),
            'perHour' => [],
            'perDay' => [],
            'queues' => [],
            'payments' => Payment::forLocation($locationId, $request->str('status') ?: null),
            'paymentTotals' => Payment::totalsToday($locationId),
            'officers' => User::officersAt($locationId),
            'byService' => [],
            'filter' => '',
            'period' => 'hari',
        ], 'panel');
    }

    public function recap(Request $request): Response
    {
        $locationId = $this->scope();
        $period = $request->str('periode', 'hari');

        if (!in_array($period, ['hari', 'minggu', 'bulan', 'tahun'], true)) {
            $period = 'hari';
        }

        $range = RecapService::range($period);

        return $this->view('admin/index', [
            'title' => 'Rekap ' . $range['label'],
            'focus' => 'rekap',
            'location' => $this->scopeLocation($locationId),
            'counts' => Queue::countsByStatus($locationId, date('Y-m-d')),
            'summary' => RecapService::summary($locationId, $period),
            'perHour' => RecapService::perHour($locationId),
            'perDay' => RecapService::perDay($locationId, $range['from'], $range['to']),
            'queues' => [],
            'payments' => [],
            'paymentTotals' => Payment::totalsToday($locationId),
            'officers' => User::officersAt($locationId),
            'byService' => RecapService::byService($locationId, $period),
            'byOfficer' => RecapService::byOfficer($locationId, $period),
            'filter' => '',
            'period' => $period,
        ], 'panel');
    }

    public function officers(Request $request): Response
    {
        $locationId = $this->scope();

        return $this->view('admin/index', [
            'title' => 'Petugas lokasi',
            'focus' => 'petugas',
            'location' => $this->scopeLocation($locationId),
            'counts' => Queue::countsByStatus($locationId, date('Y-m-d')),
            'summary' => RecapService::summary($locationId, 'hari'),
            'perHour' => [],
            'perDay' => [],
            'queues' => [],
            'payments' => [],
            'paymentTotals' => Payment::totalsToday($locationId),
            'officers' => User::officersAt($locationId),
            'byService' => [],
            'filter' => '',
            'period' => 'hari',
        ], 'panel');
    }

    public function export(Request $request): Response
    {
        $locationId = $this->scope();
        $period = $request->str('periode', 'bulan');
        $range = RecapService::range($period);

        $csv = RecapService::csv($locationId, $range['from'], $range['to']);
        $name = 'rekap-' . $range['from'] . '-sd-' . $range['to'] . '.csv';

        return Response::text($csv)
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $name . '"');
    }

    /* ----------------------------------------------------------- landmark */

    public function landmarks(Request $request): Response
    {
        $locationId = $this->scope();
        $selected = $this->scopeLocation($locationId);

        return $this->view('admin/landmarks', [
            'title' => 'Landmark & deskripsi',
            'focus' => 'landmark',
            'location' => $selected,
            'landmarks' => Location::siblingsOf($locationId),
            'districts' => District::all(),
            'selected' => $selected,
            'services' => $selected === null ? [] : Service::withRequirements((int) $selected['id']),
        ], 'panel');
    }

    /**
     * @param array<string, string> $params
     */
    public function landmarkServices(Request $request, array $params): Response
    {
        $locationId = (int) Auth::locationId();
        $selected = Location::find((int) $params['id']);

        if ($selected === null || !$this->sameDistrict($selected, $locationId)) {
            return $this->abort($request, 403, 'Landmark ini di luar wilayah Anda.');
        }

        $lingkup = $this->scope();

        return $this->view('admin/landmarks', [
            'title' => 'Deskripsi · ' . $selected['name'],
            'focus' => 'landmark',
            'location' => $this->scopeLocation($lingkup),
            'landmarks' => Location::siblingsOf($lingkup),
            'districts' => District::all(),
            'selected' => $selected,
            'services' => Service::withRequirements((int) $selected['id']),
        ], 'panel');
    }

    public function saveLandmark(Request $request): Response
    {
        $locationId = (int) Auth::locationId();

        // Daftar kategori yang sah diambil dari config, sumber yang sama
        // dengan pilihan di formulirnya. Ditulis dua kali, keduanya pernah
        // berbeda: validator dan <select> hanya memuat 9 dari 24 kategori.
        $kategori = implode(',', array_keys((array) config('map.category_labels', [])));

        $validator = Validator::make($request->all(), [
            'name' => 'required|max:150',
            'category' => 'required|in:' . $kategori,
            'district_id' => 'required|numeric',
            'lng' => 'required|numeric',
            'lat' => 'required|numeric',
            'quota_per_day' => 'required|numeric|int_min:1',
            'short_desc' => 'max:255',
        ], [
            'name' => 'Nama landmark',
            'category' => 'Kategori',
            'quota_per_day' => 'Kuota per hari',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors('/admin/landmark', $validator->errors(), $request->all());
        }

        $data = [
            'district_id' => $request->int('district_id'),
            'name' => $request->str('name'),
            'category' => $request->str('category'),
            'lng' => $request->str('lng'),
            'lat' => $request->str('lat'),
            'address' => $request->str('address') ?: null,
            'phone' => $request->str('phone') ?: null,
            'email' => $request->str('email') ?: null,
            'short_desc' => $request->str('short_desc') ?: null,
            'long_desc' => $request->str('long_desc') ?: null,
            'quota_per_day' => $request->int('quota_per_day'),
            'is_active' => $request->str('is_active') !== '' ? 1 : 0,
        ];

        $id = $request->int('id');

        if ($id > 0) {
            $existing = Location::find($id);

            if ($existing === null || !$this->sameDistrict($existing, $locationId)) {
                return $this->abort($request, 403, 'Landmark ini di luar wilayah Anda.');
            }

            DB::update('locations', $data, 'id = :id', ['id' => $id]);

            return $this->success('Landmark "' . $data['name'] . '" diperbarui.', '/admin/landmark');
        }

        /*
         * Kecamatan tujuan ikut diperiksa saat membuat, bukan hanya saat
         * mengubah. Tanpa ini admin satu kecamatan bisa menitipkan landmark
         * baru ke kecamatan mana pun lewat district_id di formulir —
         * pemeriksaan wilayahnya baru berlaku setelah landmark itu ada.
         */
        if (!$this->sameDistrict(['district_id' => $data['district_id']], $locationId)) {
            return $this->abort($request, 403, 'Kecamatan itu di luar wilayah Anda.');
        }

        $data['slug'] = $this->uniqueSlug($data['name']);
        $newId = DB::insert('locations', $data);

        return $this->success('Landmark baru ditambahkan.', '/admin/landmark/' . $newId . '/deskripsi');
    }

    /**
     * Hapus = nonaktifkan. Data antrean lama tetap utuh untuk rekap.
     *
     * @param array<string, string> $params
     */
    public function toggleLandmark(Request $request, array $params): Response
    {
        $locationId = (int) Auth::locationId();
        $landmark = Location::find((int) $params['id']);

        if ($landmark === null || !$this->sameDistrict($landmark, $locationId)) {
            return $this->abort($request, 403, 'Landmark ini di luar wilayah Anda.');
        }

        $now = (int) $landmark['is_active'] === 1 ? 0 : 1;
        DB::update('locations', ['is_active' => $now], 'id = :id', ['id' => $landmark['id']]);

        return $this->success(
            'Landmark "' . $landmark['name'] . '" ' . ($now === 1 ? 'diaktifkan' : 'dinonaktifkan') . '.',
            '/admin/landmark'
        );
    }

    /* --------------------------------------------------------- foto gedung */

    /**
     * Unggah, ganti, atau hapus foto gedung satu kantor.
     *
     * Terpisah dari saveLandmark supaya admin tidak perlu menyimpan ulang
     * seluruh data kantor hanya untuk mengganti fotonya — dan supaya
     * formulir besar itu tidak ikut jadi multipart.
     *
     * @param array<string, string> $params
     */
    public function savePhoto(Request $request, array $params): Response
    {
        $locationId = (int) Auth::locationId();
        $landmark = Location::find((int) $params['id']);

        if ($landmark === null || !$this->sameDistrict($landmark, $locationId)) {
            return $this->abort($request, 403, 'Landmark ini di luar wilayah Anda.');
        }

        $kembali = '/admin/landmark/' . $landmark['id'] . '/deskripsi';
        $lama = (string) ($landmark['cover_image'] ?? '');

        if ($request->str('hapus') !== '') {
            if ($lama === '') {
                return $this->failure('Kantor ini memang belum berfoto.', $kembali);
            }

            PhotoService::delete($lama);
            DB::update(
                'locations',
                ['cover_image' => null, 'cover_credit' => null],
                'id = :id',
                ['id' => $landmark['id']]
            );

            return $this->success('Foto ' . $landmark['name'] . ' dihapus.', $kembali);
        }

        $sumber = mb_substr(trim($request->str('cover_credit')), 0, 160);
        $file = $request->file('foto');
        $adaBerkas = is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        // Tanpa berkas baru, formulir ini hanya memperbaiki keterangan sumber.
        if (!$adaBerkas) {
            if ($lama === '') {
                return $this->failure('Pilih dulu berkas fotonya.', $kembali);
            }

            if ($sumber === '') {
                return $this->backWithErrors($kembali, ['cover_credit' => 'Sumber foto wajib diisi.'], $request->all());
            }

            DB::update('locations', ['cover_credit' => $sumber], 'id = :id', ['id' => $landmark['id']]);

            return $this->success('Sumber foto diperbarui.', $kembali);
        }

        if ($sumber === '') {
            return $this->backWithErrors(
                $kembali,
                ['cover_credit' => 'Tulis dari mana fotonya berasal sebelum mengunggah.'],
                $request->all()
            );
        }

        try {
            $nama = PhotoService::store($file);
        } catch (Throwable $e) {
            return $this->failure($e->getMessage(), $kembali);
        }

        DB::update(
            'locations',
            ['cover_image' => $nama, 'cover_credit' => $sumber],
            'id = :id',
            ['id' => $landmark['id']]
        );

        // Foto lama dihapus setelah yang baru tercatat, bukan sebelumnya:
        // kalau penyimpanan gagal di tengah jalan, kantor tidak jadi
        // kehilangan foto yang sudah ada.
        PhotoService::delete($lama);

        return $this->success('Foto ' . $landmark['name'] . ' tersimpan.', $kembali);
    }

    /* ------------------------------------------------------------ layanan */

    public function saveService(Request $request): Response
    {
        $locationId = (int) Auth::locationId();
        $targetLocation = $request->int('location_id') > 0 ? $request->int('location_id') : $locationId;
        $target = Location::find($targetLocation);

        if ($target === null || !$this->sameDistrict($target, $locationId)) {
            return $this->abort($request, 403, 'Lokasi ini di luar wilayah Anda.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|max:150',
            'fee' => 'numeric',
            'sla_days' => 'numeric',
        ], ['name' => 'Nama layanan', 'fee' => 'Biaya']);

        if ($validator->fails()) {
            return $this->backWithErrors('/admin/landmark/' . $targetLocation . '/deskripsi', $validator->errors(), $request->all());
        }

        /*
         * Kosong dan 0 tidak sama. 0 berarti layanannya memang gratis
         * menurut peraturan; NULL berarti tarifnya belum dikonfirmasi ke
         * instansi, dan halaman kantor menuliskannya "Perlu dikonfirmasi".
         *
         * Sebelumnya isian kosong disimpan sebagai 0, sehingga menyimpan
         * ulang layanan apa pun yang tarifnya belum pasti diam-diam
         * mengubahnya jadi "Gratis".
         */
        $data = [
            'location_id' => $targetLocation,
            'name' => $request->str('name'),
            'description' => $request->str('description') ?: null,
            'fee' => $request->str('fee') !== '' ? $request->str('fee') : null,
            'fee_basis' => $request->str('fee_basis') ?: null,
            'docs_basis' => $request->str('docs_basis') ?: null,
            'sla_days' => $request->int('sla_days') > 0 ? $request->int('sla_days') : null,
            'is_active' => 1,
        ];

        $id = $request->int('id');

        if ($id > 0) {
            DB::update('services', $data, 'id = :id AND location_id = :loc', ['id' => $id, 'loc' => $targetLocation]);
            $serviceId = $id;
        } else {
            $data['slug'] = $this->uniqueServiceSlug($data['name'], $targetLocation);
            $serviceId = DB::insert('services', $data);
        }

        // Syarat berkas dikirim sebagai array sejajar: label[], wajib[], unggah[].
        $this->syncRequirements($serviceId, $request);

        return $this->success('Layanan dan syarat berkas disimpan.', '/admin/landmark/' . $targetLocation . '/deskripsi');
    }

    /**
     * @param array<string, string> $params
     */
    public function removeService(Request $request, array $params): Response
    {
        $locationId = (int) Auth::locationId();

        $service = DB::first(
            'SELECT s.*, l.district_id
               FROM services s
               JOIN locations l ON l.id = s.location_id
              WHERE s.id = ? LIMIT 1',
            [(int) $params['id']]
        );

        if ($service === null || !$this->sameDistrict($service, $locationId)) {
            return $this->abort($request, 403, 'Layanan ini di luar wilayah Anda.');
        }

        DB::update('services', ['is_active' => 0], 'id = :id', ['id' => $service['id']]);

        return $this->success('Layanan dinonaktifkan.', '/admin/landmark/' . $service['location_id'] . '/deskripsi');
    }

    /* ------------------------------------------------------------ antrean */

    /**
     * @param array<string, string> $params
     */
    public function queue(Request $request, array $params): Response
    {
        $locationId = $this->scope();
        $queue = Queue::findForLocation($params['code'], $locationId);

        if ($queue === null) {
            return $this->abort($request, 404, 'Antrean tidak ada di lokasi Anda.');
        }

        return $this->view('admin/queue', [
            'title' => 'Antrean ' . $queue['code'],
            'focus' => 'antrean',
            'location' => $this->scopeLocation($locationId),
            'queue' => $queue,
            'logs' => Queue::statusLogs((int) $queue['id']),
            'documents' => Queue::documents((int) $queue['id']),
            'officers' => User::officersAt($locationId),
        ], 'panel');
    }

    /**
     * @param array<string, string> $params
     */
    public function assign(Request $request, array $params): Response
    {
        $locationId = $this->scope();
        $queue = Queue::findForLocation($params['code'], $locationId);

        if ($queue === null) {
            return $this->abort($request, 404, 'Antrean tidak ada di lokasi Anda.');
        }

        try {
            QueueService::assignOfficer($queue, $request->int('officer_id'), (int) Auth::id());
        } catch (Throwable $e) {
            return $this->failure($e->getMessage(), '/admin/antrean/' . $queue['code']);
        }

        return $this->success('Petugas ditugaskan untuk ' . $queue['code'] . '.', '/admin/antrean/' . $queue['code']);
    }

    /**
     * @param array<string, string> $params
     */
    public function markPaid(Request $request, array $params): Response
    {
        $locationId = $this->scope();
        $payment = Payment::findForLocation((int) $params['id'], $locationId);

        if ($payment === null) {
            return $this->abort($request, 404, 'Data pembayaran tidak ada di lokasi Anda.');
        }

        // Menandai lunas dua kali akan menimpa waktu dan nama pemverifikasi
        // yang pertama — catatan siapa yang memverifikasi dan kapan hilang.
        if ((string) $payment['status'] === 'lunas') {
            return $this->failure('Pembayaran ' . $payment['code'] . ' sudah ditandai lunas.', '/admin/pembayaran');
        }

        DB::transaction(static function () use ($payment): void {
            DB::update('payments', [
                'status' => 'lunas',
                'paid_at' => date('Y-m-d H:i:s'),
                'verified_by' => (int) Auth::id(),
            ], 'id = :id', ['id' => $payment['id']]);

            DB::update('queues', ['payment_status' => 'lunas'], 'id = :id', ['id' => $payment['queue_id']]);
        });

        return $this->success('Pembayaran ' . $payment['code'] . ' ditandai lunas.', '/admin/pembayaran');
    }

    /* ------------------------------------------------------------ bantuan */

    /**
     * Admin hanya boleh menyentuh landmark di kecamatan lokasinya sendiri.
     *
     * @param array<string, mixed> $row  baris yang punya kolom district_id
     */
    /**
     * Kantor yang menjadi lingkup panel ini; NULL berarti seluruh kabupaten.
     *
     * Superadmin tidak terikat satu kantor (users.location_id NULL). Nilai
     * itu dulu dicor ke 0 dan diteruskan apa adanya ke query, sehingga
     * seluruh halaman panelnya mencari location_id = 0: antrean, pembayaran,
     * rekap, dan daftar petugasnya kosong, dan tiap aksi per-antrean
     * berakhir 404.
     */
    private function scope(): ?int
    {
        return Auth::is('superadmin') ? null : (int) Auth::locationId();
    }

    /**
     * Baris kantor untuk kepala panel, atau NULL bila lingkupnya sekabupaten.
     *
     * @return array<string, mixed>|null
     */
    private function scopeLocation(?int $locationId): ?array
    {
        return $locationId === null ? null : Location::find($locationId);
    }

    private function sameDistrict(array $row, int $locationId): bool
    {
        if (Auth::is('superadmin')) {
            return true;
        }

        $mine = Location::find($locationId);

        return $mine !== null && (int) $row['district_id'] === (int) $mine['district_id'];
    }

    private function uniqueSlug(string $name): string
    {
        $base = $this->slugify($name);
        $slug = $base;
        $i = 2;

        while (DB::column('SELECT 1 FROM locations WHERE slug = ? LIMIT 1', [$slug]) !== null) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    private function uniqueServiceSlug(string $name, int $locationId): string
    {
        $base = $this->slugify($name);
        $slug = $base;
        $i = 2;

        while (DB::column('SELECT 1 FROM services WHERE slug = ? AND location_id = ? LIMIT 1', [$slug, $locationId]) !== null) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-') ?: 'item';
    }

    private function syncRequirements(int $serviceId, Request $request): void
    {
        $labels = $request->input('req_label');

        if (!is_array($labels)) {
            return;
        }

        $wajib = (array) ($request->input('req_wajib') ?? []);
        $unggah = (array) ($request->input('req_unggah') ?? []);

        // Catatan ikut ditulis ulang. Baris ini sempat tidak ada, dan karena
        // seluruh syarat dihapus lalu disisipkan lagi, menyimpan satu layanan
        // menghapus catatan praktis tiap berkasnya tanpa jejak.
        $catatan = (array) ($request->input('req_note') ?? []);

        DB::run('DELETE FROM service_requirements WHERE service_id = ?', [$serviceId]);

        $order = 0;

        foreach ($labels as $index => $label) {
            $label = trim((string) $label);

            if ($label === '') {
                continue;
            }

            $note = trim((string) ($catatan[$index] ?? ''));

            DB::insert('service_requirements', [
                'service_id' => $serviceId,
                'label' => mb_substr($label, 0, 180),
                'note' => $note !== '' ? mb_substr($note, 0, 255) : null,
                'is_mandatory' => isset($wajib[$index]) ? 1 : 0,
                'needs_upload' => isset($unggah[$index]) ? 1 : 0,
                'sort_order' => $order++,
            ]);
        }
    }

    /**
     * Daftar aduan warga tentang kantor ini.
     *
     * Admin lokasi hanya melihat kantornya sendiri; superadmin tidak terikat
     * satu lokasi, jadi melihat seluruh kabupaten.
     */
    public function reports(Request $request): Response
    {
        $locationId = $this->scope();
        $filter = $request->str('status');

        if ($filter !== '' && !isset(FacilityReport::STATUS[$filter])) {
            $filter = '';
        }

        return $this->view('admin/reports', [
            'title' => 'Aduan fasilitas',
            'focus' => 'aduan',
            'location' => $this->scopeLocation($locationId),
            'reports' => FacilityReport::forPanel($locationId, $filter),
            'counts' => FacilityReport::countByStatus($locationId),
            'filter' => $filter,
        ], 'panel');
    }

    /**
     * Ubah status sebuah aduan dan catat siapa yang menanganinya.
     */
    public function updateReport(Request $request, array $params): Response
    {
        $report = FacilityReport::find((int) ($params['id'] ?? 0));

        if ($report === null) {
            return $this->failure('Aduan tidak ditemukan.', '/admin/aduan');
        }

        // Admin lokasi tidak boleh menyentuh aduan kantor lain.
        if (Auth::role() !== 'superadmin' && (int) $report['location_id'] !== (int) Auth::locationId()) {
            return $this->abort($request, 403, 'Aduan ini bukan untuk kantor Anda.');
        }

        $status = $request->str('status');

        if (!isset(FacilityReport::STATUS[$status])) {
            return $this->failure('Status aduan tidak dikenal.', '/admin/aduan');
        }

        $catatan = trim($request->str('catatan'));

        FacilityReport::setStatus(
            (int) $report['id'],
            $status,
            $catatan !== '' ? mb_substr($catatan, 0, 2000) : null,
            (int) Auth::id()
        );

        return $this->success(
            'Aduan #' . $report['id'] . ' ditandai "' . FacilityReport::labelStatus($status) . '".',
            '/admin/aduan'
        );
    }
}
