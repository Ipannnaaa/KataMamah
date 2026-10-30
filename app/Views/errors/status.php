<?php

/**
 * Halaman error untuk semua kode status.
 *
 * Judul dan saran per kode diambil dari config/errors lewat error_info(),
 * supaya pengunjung tahu apa yang terjadi dan apa langkah berikutnya, bukan
 * sekadar diberi angka. Pesan teknis dari
 * pemanggil tetap ditampilkan di bawahnya kalau ada isinya dan berbeda dari
 * keterangan baku — di situlah detail seperti "Antrean tidak ada di lokasi
 * Anda" muncul.
 *
 * @var int $status
 * @var string $message
 */

use App\Core\Auth;

$info = error_info($status);

$detail = trim($message);
$tampilkanDetail = $detail !== '' && $detail !== $info['ket'] && $detail !== $info['judul'];

?>
<section class="container section errorpage">
    <div class="errorpage__art" aria-hidden="true">
        <svg viewBox="0 0 220 150" width="220" height="150" fill="none">
            <!-- laut -->
            <rect width="220" height="150" rx="16" fill="#D8EEF8"></rect>

            <!-- pulau kecil, memakai bahasa visual peta utama -->
            <path d="M44 104c-10-7-14-20-8-31 5-10 2-18 11-25 10-8 26-11 39-6 12 5 17 2 29 5 15 4 26 13 28 25 2 11-4 18-13 24-11 7-19 13-33 14-17 2-42 2-53-6z"
                  fill="#8A6744"></path>
            <path d="M44 96c-10-7-14-20-8-31 5-10 2-18 11-25 10-8 26-11 39-6 12 5 17 2 29 5 15 4 26 13 28 25 2 11-4 18-13 24-11 7-19 13-33 14-17 2-42 2-53-6z"
                  fill="#7CC065"></path>

            <!-- rute putus-putus yang berhenti di tempat kosong -->
            <path d="M62 86c14-10 24-4 34-14 9-9 20-10 30-4" stroke="#F2F7F0" stroke-width="3"
                  stroke-linecap="round" stroke-dasharray="1 9"></path>

            <!-- pin yang jatuh di luar daratan -->
            <g class="errorpage__pin">
                <ellipse cx="176" cy="126" rx="13" ry="4" fill="#3C7FA0" opacity=".3"></ellipse>
                <path d="M176 84c-9 0-16 7-16 16 0 11 16 26 16 26s16-15 16-26c0-9-7-16-16-16z"
                      fill="#D64550"></path>
                <circle cx="176" cy="100" r="6" fill="#ffffff"></circle>
            </g>
        </svg>
    </div>

    <p class="eyebrow">Kode <?= e((string) $status) ?></p>
    <h1 class="errorpage__title"><?= e($info['judul']) ?></h1>
    <p class="errorpage__lede"><?= e($info['ket']) ?> <?= e($info['saran']) ?></p>

    <?php if ($tampilkanDetail) : ?>
        <p class="errorpage__detail"><?= e($detail) ?></p>
    <?php endif; ?>

    <p class="errorpage__actions">
        <a class="btn btn--primary" href="<?= e(url('/peta')) ?>">Buka peta layanan</a>
        <a class="btn btn--ghost" href="<?= e(url('/cek-antrean')) ?>">Cek kode antrean</a>
        <?php if (Auth::check()) : ?>
            <a class="btn btn--quiet" href="<?= e(url((string) config('roles.home.' . Auth::role(), '/akun'))) ?>">
                Kembali ke beranda akun
            </a>
        <?php else : ?>
            <a class="btn btn--quiet" href="<?= e(url('/')) ?>">Halaman depan</a>
        <?php endif; ?>
    </p>
</section>
