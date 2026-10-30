<?php

/**
 * Layout halaman pembuka: langit bergradasi, konten di tengah, dan panorama
 * Kulon Progo menempel di dasar layar.
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
<title><?= e(($title ?? 'Beranda') . ' · ' . config('app.name')) ?></title>
<meta name="description" content="Portal informasi dan antrean layanan publik Kabupaten <?= e((string) config('app.wilayah')) ?>.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/layout.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/landing.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/anim.css')) ?>">
<?= View::partial('arah-transisi') ?>
</head>
<body class="is-landing">

<div class="sky">
    <!-- awan -->
    <span class="cloud cloud--1" aria-hidden="true"></span>
    <span class="cloud cloud--2" aria-hidden="true"></span>
    <span class="cloud cloud--3" aria-hidden="true"></span>
    <span class="cloud cloud--4" aria-hidden="true"></span>

    <header class="landing-header">
        <a class="brand brand--landing" href="<?= e(url('/')) ?>">
            <span class="brand__mark"><?= e(mb_substr((string) config('app.short_name', 'T'), 0, 1)) ?></span>
            <span class="brand__text">
                <strong><?= e((string) config('app.name')) ?></strong>
                <small>Peta layanan publik</small>
            </span>
        </a>

        <nav class="landing-nav" aria-label="Navigasi utama">
            <a href="<?= e(url('/peta')) ?>">Peta</a>
            <a href="<?= e(url('/cek-antrean')) ?>">Cek Antrean</a>
            <a href="<?= e(url('/tentang')) ?>">Tentang</a>
        </nav>

        <div class="landing-actions">
            <?php if (Auth::check()) : ?>
                <a class="btn btn--ghost btn--small" href="<?= e(url((string) config('roles.home.' . Auth::role(), '/akun'))) ?>">
                    <?= e(Auth::name()) ?>
                </a>
            <?php else : ?>
                <a class="btn btn--ghost btn--small" href="<?= e(url('/masuk')) ?>">Masuk</a>
                <a class="btn btn--primary btn--small" href="<?= e(url('/daftar')) ?>">Daftar</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="landing-main" id="konten">
        <?= View::partial('flash') ?>
        <?= $content ?>
    </main>

    <div class="landing-scene" aria-hidden="false">
        <?= View::partial('skyline') ?>
    </div>

    <footer class="landing-footer">
        <p>
            © <?= e(date('Y')) ?> <?= e((string) config('app.name')) ?> ·
            Dinas Komunikasi dan Informatika Kabupaten <?= e((string) config('app.wilayah')) ?>
        </p>
    </footer>
</div>

<script src="<?= e(asset('js/ui.js')) ?>" type="module"></script>
<script src="<?= e(asset('js/search.js')) ?>" type="module"></script>
</body>
</html>
