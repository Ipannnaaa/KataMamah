<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Layar antara "Mengalihkan…".
 *
 * Dipanggil lewat Controller::redirectVia(). Tujuan dan pesannya diambil dari
 * sesi, tidak pernah dari query string, supaya halaman ini tidak bisa dipakai
 * melempar pengunjung ke alamat luar. Siapa pun yang membuka /mengalihkan
 * tanpa aksi sebelumnya hanya dikembalikan ke halaman depan.
 */
final class RedirectController extends Controller
{
    public function show(Request $request): Response
    {
        $alih = Session::getFlash('_alih');

        if (!is_array($alih) || !isset($alih['to'])) {
            return $this->redirect('/');
        }

        $to = (string) $alih['to'];

        // Hanya path internal. Alamat yang diawali skema atau '//' akan
        // membawa pengunjung keluar, jadi dibuang dan diganti halaman depan.
        if ($to === '' || $to[0] !== '/' || str_starts_with($to, '//')) {
            $to = '/';
        }

        // Flash lain — pesan sukses, kode antrean — harus ikut sampai tujuan,
        // bukan habis di layar ini.
        Session::keepFlash(['_alih']);

        $detik = max(1, (int) config('app.redirect_delay', 2));

        return $this->view('redirect/show', [
            'title' => 'Mengalihkan',
            'show_flash' => false,
            'tujuan' => url($to),
            'judul' => (string) ($alih['judul'] ?? 'Berhasil'),
            'ket' => (string) ($alih['ket'] ?? ''),
            'detik' => $detik,
            // Tanpa JavaScript pun tetap berpindah sendiri.
            'head' => '<meta http-equiv="refresh" content="' . $detik . ';url=' . e(url($to)) . '">',
        ]);
    }
}
