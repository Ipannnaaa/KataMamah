<?php

/**
 * Layout halaman publik.
 *
 * @var string $content
 * @var string|null $title
 */

use App\Core\Auth;
use App\Core\View;

?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? 'Layanan publik') . ' · ' . config('app.name')) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/layout.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/map.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/anim.css')) ?>">
<?= View::partial('arah-transisi') ?>
<?php /* Tambahan <head> dari controller, mis. meta refresh di layar Mengalihkan. */ ?>
<?= $head ?? '' ?>
</head>
<body>

<header class="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="<?= e(url('/')) ?>">
            <span class="brand__mark"><?= e(mb_substr((string) config('app.short_name', 'T'), 0, 1)) ?></span>
            <span class="brand__text">
                <strong><?= e((string) config('app.name')) ?></strong>
                <small>Peta layanan publik</small>
            </span>
        </a>

        <nav class="nav" aria-label="Navigasi utama">
            <a href="<?= e(url('/peta')) ?>">Peta</a>
            <a href="<?= e(url('/cari')) ?>">Cari</a>
            <a href="<?= e(url('/tentang')) ?>">Tentang</a>
            <a href="<?= e(url('/cek-antrean')) ?>">Cek Antrean</a>
        </nav>

        <div class="site-header__actions">
            <?php if (Auth::check()) : ?>
                <a class="btn btn--ghost" href="<?= e(url((string) config('roles.home.' . Auth::role(), '/akun'))) ?>">
                    <?= e(Auth::name()) ?>
                </a>
                <form method="post" action="<?= e(url('/keluar')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button class="btn btn--quiet" type="submit">Keluar</button>
                </form>
            <?php else : ?>
                <a class="btn btn--ghost" href="<?= e(url('/masuk')) ?>">Masuk</a>
                <a class="btn btn--primary" href="<?= e(url('/daftar')) ?>">Daftar</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main id="konten">
    <?php /* Layar antara seperti /mengalihkan sudah memuat pesannya sendiri;
           tanpa ini pesan sukses tampil dua kali, di sini dan di tujuan. */ ?>
    <?= ($show_flash ?? true) ? View::partial('flash') : '' ?>
    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="container site-footer__inner">
        <p>© <?= e(date('Y')) ?> <?= e((string) config('app.name')) ?> · Pemerintah Kabupaten <?= e((string) config('app.wilayah')) ?></p>
        <nav class="nav nav--small" aria-label="Tautan kaki">
            <a href="<?= e(url('/tentang')) ?>">Tentang</a>
            <a href="<?= e(url('/cek-antrean')) ?>">Cek antrean</a>
            <a href="<?= e(url('/masuk')) ?>">Masuk petugas</a>
        </nav>
    </div>
</footer>

<script src="<?= e(asset('js/ui.js')) ?>" type="module"></script>
<?php if (!empty($scripts) && is_array($scripts)) : ?>
    <?php foreach ($scripts as $script) : ?>
        <script src="<?= e(asset('js/' . $script)) ?>" type="module"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
