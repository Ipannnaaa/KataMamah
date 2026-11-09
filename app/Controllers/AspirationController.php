<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Aspiration;

/**
 * Kotak aspirasi warga.
 *
 * Terbuka tanpa akun, dengan alasan yang sama seperti aduan fasilitas: yang
 * paling punya masukan soal layanan publik justru orang yang jarang berurusan
 * dengan birokrasi, dan mewajibkan mereka mendaftar dulu berarti masukannya
 * tidak pernah masuk. Penahannya pembatas laju per IP, bukan akun.
 */
final class AspirationController extends Controller
{
    public function form(Request $request): Response
    {
        return $this->view('pages/aspirasi', [
            'title' => 'Kotak aspirasi warga',
        ]);
    }

    public function store(Request $request): Response
    {
        $kembali = '/aspirasi';
        $limit = (array) config('app.rate_limit.aspirasi', ['max' => 3, 'decay' => 900]);
        $key = 'aspirasi:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, (int) $limit['max'], (int) $limit['decay'])) {
            $menit = max(1, (int) ceil(RateLimiter::secondsLeft($key) / 60));

            return $this->failure(
                'Pengiriman dari perangkat ini dibatasi sementara. Coba lagi sekitar ' . $menit . ' menit lagi.',
                $kembali
            );
        }

        $validator = Validator::make($request->all(), [
            'topik' => 'required|in:' . implode(',', array_keys(Aspiration::TOPIK)),
            // Batas bawah 25 huruf, lebih panjang daripada aduan fasilitas.
            // Aduan menunjuk satu kantor sehingga konteksnya sudah jelas dari
            // halamannya; aspirasi tidak punya konteks itu sama sekali, jadi
            // isinya harus berdiri sendiri supaya bisa ditindaklanjuti.
            'aspirasi' => 'required|min:25|max:2000',
            'kontak' => 'max:160',
        ], [
            'topik' => 'Topik',
            'aspirasi' => 'Isi aspirasi',
            'kontak' => 'Kontak',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors($kembali, $validator->errors(), $request->all());
        }

        RateLimiter::hit($key, (int) $limit['decay']);

        Aspiration::create([
            'user_id' => Auth::check() ? Auth::id() : null,
            'topic' => $request->str('topik'),
            'message' => $request->str('aspirasi'),
            'contact' => $request->str('kontak') !== '' ? $request->str('kontak') : null,
            'ip' => $request->ip(),
        ]);

        return $this->redirectVia(
            $kembali,
            'Aspirasi terkirim',
            'Terima kasih. Masukan Anda masuk ke super admin untuk ditinjau.'
        );
    }
}
