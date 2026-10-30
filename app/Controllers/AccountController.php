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
use App\Models\Message;
use App\Models\Queue;
use App\Models\User;
use App\Services\ChatService;
use App\Services\QueueService;
use App\Services\VerificationService;
use Throwable;

final class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = (int) Auth::id();

        return $this->view('account/index', [
            'title' => 'Antrean saya',
            'focus' => 'antrean',
            'active' => Queue::activeForUser($userId),
            'queues' => Queue::forUser($userId),
            'stats' => User::stats($userId),
            'byService' => $this->byService($userId),
            'filter' => '',
        ]);
    }

    public function queues(Request $request): Response
    {
        $userId = (int) Auth::id();
        $filter = $request->str('status');

        if ($filter !== '' && !array_key_exists($filter, (array) config('roles.status', []))) {
            $filter = '';
        }

        return $this->view('account/index', [
            'title' => 'Daftar antrean',
            'focus' => 'antrean',
            'active' => Queue::activeForUser($userId),
            'queues' => Queue::forUser($userId, $filter !== '' ? $filter : null),
            'stats' => User::stats($userId),
            'byService' => $this->byService($userId),
            'filter' => $filter,
        ]);
    }

    public function recap(Request $request): Response
    {
        $userId = (int) Auth::id();

        return $this->view('account/index', [
            'title' => 'Rekap riwayat saya',
            'focus' => 'rekap',
            'active' => Queue::activeForUser($userId),
            'queues' => Queue::forUser($userId),
            'stats' => User::stats($userId),
            'byService' => $this->byService($userId),
            'filter' => '',
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params): Response
    {
        $queue = Queue::findForUser($params['code'], (int) Auth::id());

        if ($queue === null) {
            return $this->abort($request, 404, 'Antrean tidak ditemukan atau bukan milik Anda.');
        }

        return $this->view('account/show', [
            'title' => 'Antrean ' . $queue['code'],
            'queue' => $queue,
            'logs' => Queue::statusLogs((int) $queue['id']),
            'documents' => Queue::documents((int) $queue['id']),
            'chatOpen' => ChatService::isOpen($queue),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function chat(Request $request, array $params): Response
    {
        $user = Auth::user() ?? [];
        $queue = Queue::findForUser($params['code'], (int) Auth::id());

        if ($queue === null) {
            return $this->abort($request, 404, 'Antrean tidak ditemukan atau bukan milik Anda.');
        }

        try {
            $messages = ChatService::messages($queue, $user);
        } catch (Throwable $e) {
            return $this->abort($request, 403, $e->getMessage());
        }

        return $this->view('chat/thread', [
            'title' => 'Chat ' . $queue['code'],
            'layoutRole' => 'user',
            'queue' => $queue,
            'messages' => $messages,
            'threads' => Message::threadsForUser((int) Auth::id()),
            'open' => ChatService::isOpen($queue),
            'me' => $user,
            'scripts' => ['chat.js'],
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function cancel(Request $request, array $params): Response
    {
        $queue = Queue::findForUser($params['code'], (int) Auth::id());

        if ($queue === null) {
            return $this->abort($request, 404, 'Antrean tidak ditemukan atau bukan milik Anda.');
        }

        try {
            QueueService::changeStatus(
                $queue,
                'dibatalkan',
                (int) Auth::id(),
                'user',
                'Dibatalkan oleh warga: ' . $request->str('alasan', 'tanpa alasan')
            );
        } catch (Throwable $e) {
            return $this->failure($e->getMessage(), '/akun/antrean/' . $queue['code']);
        }

        return $this->success('Antrean ' . $queue['code'] . ' dibatalkan.', '/akun/antrean');
    }

    public function profile(Request $request): Response
    {
        $user = Auth::user() ?? [];

        if (!$request->isPost()) {
            return $this->view('account/profile', [
                'title' => 'Profil peserta',
                'user' => $user,
                'districts' => District::all(),
                'stats' => User::stats((int) $user['id']),
            ]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|max:120',
            'email' => 'required|email|max:160',
            'phone' => 'phone',
            'nik' => 'digits:16',
            'address' => 'max:255',
            'gender' => 'in:L,P',
            'birth_date' => 'date',
        ], [
            'name' => 'Nama lengkap',
            'email' => 'Email',
            'phone' => 'Nomor ponsel',
            'nik' => 'NIK',
            'address' => 'Alamat',
        ]);

        $errors = $validator->errors();
        $email = $request->str('email');
        $nik = $request->str('nik');
        $userId = (int) $user['id'];

        if (!isset($errors['email']) && User::emailExists($email, $userId)) {
            $errors['email'] = 'Email ini sudah dipakai akun lain.';
        }

        if ($nik !== '' && !isset($errors['nik']) && User::nikExists($nik, $userId)) {
            $errors['nik'] = 'NIK ini sudah dipakai akun lain.';
        }

        /*
         * Sandi diperiksa SEBELUM apa pun ditulis.
         *
         * Dulu profil disimpan lebih dulu, lalu sandi lama dicocokkan dengan
         * Auth::attempt($user['email']) — alamat yang baru saja ditimpa.
         * Mengubah email dan sandi sekaligus karena itu selalu dijawab
         * "sandi saat ini tidak cocok" padahal benar, dan perubahan
         * profilnya telanjur tersimpan walaupun permintaannya dinyatakan
         * gagal.
         */
        $new = $request->str('password');

        if ($new !== '' && Auth::attempt((string) $user['email'], $request->str('password_current')) === null) {
            $errors['password_current'] = 'Sandi saat ini tidak cocok.';
        }

        if ($new !== '' && mb_strlen($new) < 8) {
            $errors['password'] = 'Sandi baru minimal 8 karakter.';
        }

        if ($new !== '' && $new !== $request->str('password_confirmation')) {
            $errors['password_confirmation'] = 'Ulangi sandi tidak cocok.';
        }

        if ($errors !== []) {
            return $this->backWithErrors('/akun/profil', $errors, $request->all());
        }

        /*
         * Alamat email tidak langsung diganti.
         *
         * Akun yang belum terverifikasi tidak bisa masuk, jadi mengganti
         * alamat begitu saja berarti akun tetap bertanda terverifikasi untuk
         * alamat yang belum pernah dibuktikan — dan satu salah ketik membuat
         * pemiliknya tidak pernah lagi menerima apa pun dari sini. Alamat
         * baru disimpan sebagai pending_email dan baru menggantikan yang
         * lama setelah tautannya dibuka.
         */
        $emailBaru = strtolower($email);
        $gantiEmail = $emailBaru !== strtolower((string) $user['email']);

        $data = [
            'name' => $request->str('name'),
            'phone' => $request->str('phone') !== '' ? $request->str('phone') : null,
            'nik' => $nik !== '' ? $nik : null,
            'address' => $request->str('address') !== '' ? $request->str('address') : null,
            'gender' => $request->str('gender') !== '' ? $request->str('gender') : null,
            'birth_date' => $request->str('birth_date') !== '' ? $request->str('birth_date') : null,
            'district_id' => $request->int('district_id') > 0 ? $request->int('district_id') : null,
        ];

        if ($gantiEmail) {
            $data['pending_email'] = $emailBaru;
        }

        if ($new !== '') {
            $data['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
        }

        DB::update('users', $data, 'id = :id', ['id' => $userId]);

        if (!$gantiEmail) {
            return $this->success('Profil diperbarui.', '/akun/profil');
        }

        try {
            VerificationService::sendEmailChange(User::find($userId) ?? [], $request->ip());
        } catch (Throwable $e) {
            error_log('[verifikasi] gagal kirim penggantian email ke ' . $emailBaru . ': ' . $e->getMessage());
            DB::update('users', ['pending_email' => null], 'id = :id', ['id' => $userId]);

            return $this->failure(
                'Profil tersimpan, tetapi email konfirmasi ke ' . $emailBaru . ' gagal dikirim. '
                . 'Alamat lama masih berlaku; coba lagi nanti.',
                '/akun/profil'
            );
        }

        return $this->success(
            'Profil tersimpan. Konfirmasi dikirim ke ' . $emailBaru
            . ' — alamat lama tetap berlaku sampai tautannya dibuka.',
            '/akun/profil'
        );
    }

    /**
     * @return array<int, array{label: string, total: int}>
     */
    private function byService(int $userId): array
    {
        $rows = DB::all(
            'SELECT s.name AS label, COUNT(*) AS total
               FROM queues q
               JOIN services s ON s.id = q.service_id
              WHERE q.user_id = ?
           GROUP BY s.id, s.name
           ORDER BY total DESC
              LIMIT 8',
            [$userId]
        );

        return array_map(
            static fn (array $r): array => ['label' => (string) $r['label'], 'total' => (int) $r['total']],
            $rows
        );
    }
}
