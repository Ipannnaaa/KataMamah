<?php

/**
 * Layout panel internal (petugas, admin).
 *
 * @var string $content
 * @var string|null $title
 * @var array<string, mixed>|null $location
 * @var string|null $focus
 */

use App\Core\Auth;
use App\Core\View;

$role = (string) Auth::role();
$isAdmin = in_array($role, ['admin', 'superadmin'], true);

$menu = $isAdmin
    ? [
        ['Ringkasan', '/admin', 'ringkasan'],
        ['Landmark & deskripsi', '/admin/landmark', 'landmark'],
        ['Informasi antrean', '/admin/antrean', 'antrean'],
        ['Pembayaran', '/admin/pembayaran', 'pembayaran'],
        ['Aduan fasilitas', '/admin/aduan', 'aduan'],
        ['Rekap', '/admin/rekap', 'rekap'],
        ['Petugas', '/admin/petugas', 'petugas'],
    ]
    : [
        ['Papan antrean', '/petugas', 'papan'],
        ['Rekap kinerja', '/petugas/rekap', 'rekap'],
    ];

?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? 'Panel') . ' · ' . config('app.name')) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/layout.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/anim.css')) ?>">
<?= View::partial('arah-transisi') ?>
</head>
<body class="has-panel">

<header class="panel-header">
    <div class="container panel-header__inner">
        <a class="brand brand--dark" href="<?= e(url('/')) ?>">
            <span class="brand__mark"><?= e(mb_substr((string) config('app.short_name', 'T'), 0, 1)) ?></span>
            <strong><?= e((string) config('app.name')) ?></strong>
        </a>
        <span class="chip chip--dark"><?= e($isAdmin ? 'Panel admin' : 'Panel petugas') ?></span>
        <?php if (!empty($location)) : ?>
            <p class="panel-header__place"><?= e((string) $location['name']) ?></p>
        <?php endif; ?>
        <div class="panel-header__actions">
            <span class="avatar avatar--dark" aria-hidden="true"><?= e(mb_substr(Auth::name(), 0, 1)) ?></span>
            <span class="panel-header__name"><?= e(Auth::name()) ?></span>
            <form method="post" action="<?= e(url('/keluar')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <button class="btn btn--quiet btn--on-dark" type="submit">Keluar</button>
            </form>
        </div>
    </div>
</header>

<div class="container panel-body">
    <aside class="panel-nav">
        <nav aria-label="Menu panel">
            <?php foreach ($menu as [$label, $href, $key]) : ?>
                <a class="panel-nav__item<?= ($focus ?? '') === $key ? ' is-active' : '' ?>" href="<?= e(url($href)) ?>">
                    <?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <main class="panel-main" id="konten">
        <?= View::partial('flash') ?>
        <?= $content ?>
    </main>
</div>

<script src="<?= e(asset('js/ui.js')) ?>" type="module"></script>
<?php if (!empty($scripts) && is_array($scripts)) : ?>
    <?php foreach ($scripts as $script) : ?>
        <script src="<?= e(asset('js/' . $script)) ?>" type="module"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
