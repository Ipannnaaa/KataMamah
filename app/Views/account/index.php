<?php

/**
 * Halaman pengguna: antrean aktif, daftar antrean, rekap pribadi.
 *
 * @var string $focus
 * @var array<int, array<string, mixed>> $active
 * @var array<int, array<string, mixed>> $queues
 * @var array<string, int|float> $stats
 * @var array<int, array{label: string, total: int}> $byService
 * @var string $filter
 */

$max = 0;

foreach ($byService as $row) {
    $max = max($max, $row['total']);
}

?>
<section class="container section">
    <nav class="subnav" aria-label="Menu akun">
        <a class="<?= $focus === 'antrean' ? 'is-active' : '' ?>" href="<?= e(url('/akun')) ?>">Antrean saya</a>
        <a class="<?= $focus === 'rekap' ? 'is-active' : '' ?>" href="<?= e(url('/akun/rekap')) ?>">Rekap riwayat</a>
        <a href="<?= e(url('/akun/profil')) ?>">Profil peserta</a>
        <a class="subnav__cta" href="<?= e(url('/peta')) ?>">Ambil antrean baru</a>
    </nav>

    <h1 class="page-title">Antrean saya</h1>

    <div class="stat-grid">
        <div class="stat"><strong><?= e((string) $stats['aktif']) ?></strong><span>Antrean aktif</span></div>
        <div class="stat"><strong><?= e((string) $stats['selesai']) ?></strong><span>Selesai</span></div>
        <div class="stat"><strong><?= e((string) $stats['dibatalkan']) ?></strong><span>Dibatalkan</span></div>
        <div class="stat"><strong><?= e(rupiah((float) $stats['biaya'])) ?></strong><span>Total biaya lunas</span></div>
    </div>

    <?php if ($active !== []) : ?>
        <h2 class="section-title">Sedang berjalan</h2>
        <div class="stack">
            <?php foreach ($active as $queue) : ?>
                <article class="card">
                    <div class="card__top">
                        <span class="<?= e(status_class((string) $queue['status'])) ?>"><?= e(status_label((string) $queue['status'])) ?></span>
                        <span class="card__meta">Kode <strong><?= e((string) $queue['code']) ?></strong></span>
                    </div>
                    <strong class="card__title">
                        <?= e((string) $queue['service_name']) ?> · <?= e((string) $queue['location_name']) ?>
                    </strong>
                    <p class="card__text">
                        Nomor <?= e(str_pad((string) $queue['queue_number'], 3, '0', STR_PAD_LEFT)) ?>
                        · <?= e(tanggal((string) $queue['queue_date'])) ?>
                        <?php if (!empty($queue['officer_name'])) : ?>
                            · ditangani <?= e((string) $queue['officer_name']) ?>
                        <?php endif; ?>
                    </p>
                    <p class="actions">
                        <a class="btn btn--ghost" href="<?= e(url('/akun/antrean/' . $queue['code'])) ?>">Detail</a>
                        <?php if ((string) $queue['status'] !== 'baru') : ?>
                            <a class="btn btn--dark" href="<?= e(url('/akun/antrean/' . $queue['code'] . '/chat')) ?>">Chat petugas</a>
                        <?php endif; ?>
                    </p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2 class="section-title">Daftar antrean yang diambil</h2>
    <nav class="chip-row" aria-label="Filter status">
        <a class="chip <?= $filter === '' ? 'chip--on' : '' ?>" href="<?= e(url('/akun/antrean')) ?>">Semua</a>
        <?php foreach ((array) config('roles.status', []) as $key => $label) : ?>
            <a class="chip <?= $filter === (string) $key ? 'chip--on' : '' ?>"
               href="<?= e(url('/akun/antrean?status=' . $key)) ?>"><?= e((string) $label) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($queues === []) : ?>
        <p class="empty">Belum ada antrean pada filter ini.</p>
    <?php else : ?>
        <table class="table">
            <caption class="sr-only">Daftar antrean</caption>
            <thead>
                <tr>
                    <th scope="col">Kode</th>
                    <th scope="col">Layanan &amp; lokasi</th>
                    <th scope="col">Tanggal</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($queues as $queue) : ?>
                    <tr>
                        <th scope="row"><?= e((string) $queue['code']) ?></th>
                        <td>
                            <strong><?= e((string) $queue['service_name']) ?></strong><br>
                            <small><?= e((string) $queue['location_name']) ?></small>
                        </td>
                        <td><?= e(tanggal((string) $queue['queue_date'])) ?></td>
                        <td><span class="<?= e(status_class((string) $queue['status'])) ?>"><?= e(status_label((string) $queue['status'])) ?></span></td>
                        <td><a href="<?= e(url('/akun/antrean/' . $queue['code'])) ?>">Buka</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2 class="section-title">Rekap per jenis layanan</h2>
    <?php if ($byService === []) : ?>
        <p class="empty">Rekap muncul setelah Anda mengambil antrean pertama.</p>
    <?php else : ?>
        <ul class="bars">
            <?php foreach ($byService as $row) : ?>
                <li class="bar">
                    <span class="bar__label"><?= e($row['label']) ?></span>
                    <span class="bar__track">
                        <span class="bar__fill" style="width: <?= e((string) ($max > 0 ? round(($row['total'] / $max) * 100) : 0)) ?>%"></span>
                    </span>
                    <span class="bar__value"><?= e((string) $row['total']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
