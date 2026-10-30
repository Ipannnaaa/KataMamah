<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\FacilityReport;
use App\Models\Location;

/**
 * Aduan warga tentang sebuah kantor layanan.
 *
 * Tidak memerlukan akun. Data yang salah paling sering ditemukan orang yang
 * baru pertama datang ke sana, dan memaksa mereka mendaftar dulu berarti
 * aduan itu tidak pernah masuk. Sebagai gantinya ada pembatas laju per IP.
 */
final class ReportController extends Controller
{
    public function store(Request $request, array $params): Response
    {
        $location = Location::findBySlug((string) ($params['slug'] ?? ''));

        if ($location === null) {
            return $this->abort($request, 404, 'Lokasi layanan tidak ditemukan.');
        }

        $kembali = '/lokasi/' . $location['slug'];
        $limit = (array) config('app.rate_limit.report', ['max' => 5, 'decay' => 900]);
        $key = 'report:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, (int) $limit['max'], (int) $limit['decay'])) {
            $menit = max(1, (int) ceil(RateLimiter::secondsLeft($key) / 60));

            return $this->failure(
                'Aduan dari perangkat ini dibatasi sementara. Coba lagi sekitar ' . $menit . ' menit lagi.',
                $kembali
            );
        }

        $validator = Validator::make($request->all(), [
            'kategori' => 'required|in:' . implode(',', array_keys(FacilityReport::KATEGORI)),
            // Batas bawah 15 huruf: "jelek" saja tidak bisa ditindaklanjuti
            // admin, dan menolaknya lebih baik daripada menumpuk aduan kosong.
            'aduan' => 'required|min:15|max:2000',
            'kontak' => 'max:160',
        ], [
            'kategori' => 'Jenis aduan',
            'aduan' => 'Isi aduan',
            'kontak' => 'Kontak',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors($kembali . '#aduan', $validator->errors(), $request->all());
        }

        RateLimiter::hit($key, (int) $limit['decay']);

        FacilityReport::create([
            'location_id' => (int) $location['id'],
            'user_id' => Auth::check() ? Auth::id() : null,
            'category' => $request->str('kategori'),
            'message' => $request->str('aduan'),
            'contact' => $request->str('kontak') !== '' ? $request->str('kontak') : null,
            'ip' => $request->ip(),
        ]);

        return $this->redirectVia(
            $kembali . '#aduan',
            'Aduan terkirim',
            'Terima kasih. Aduan Anda masuk ke admin kantor ini untuk ditinjau.'
        );
    }
}
