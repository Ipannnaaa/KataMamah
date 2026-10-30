<?php

/**
 * Papan antrean petugas + rekap kinerja.
 *
 * @var string $focus
 * @var string $tab
 * @var array<int, array<string, mixed>> $cards
 * @var array<string, int> $counts
 * @var array<string, mixed> $summary
 * @var array<int, array{label: string, total: int}> $byService
 * @var string $period
 */

$max = 0;

foreach ($byService as $row) {
    $max = max($max, $row['total']);
}

$tabs = [
    'baru' => 'Antrean baru',
    'saya' => 'Milik saya',
    'selesai' => 'Selesai hari ini',
];

?>
<div class="page-head">
    <div>
        <h1 class="page-title"><?= $focus === 'rekap' ? 'Rekap kinerja' : 'Papan antrean hari ini' ?></h1>
        <p class="lede"><?= e(tanggal(date('Y-m-d'))) ?></p>
    </div>
</div>

<?php if ($focus !== 'rekap') : ?>
    <nav class="tabs" aria-label="Tab papan antrean">
        <?php foreach ($tabs as $key => $label) : ?>
            <a class="tab<?= $tab === $key ? ' is-active' : '' ?>" href="<?= e(url('/petugas?tab=' . $key)) ?>">
                <?= e($label) ?> <span class="tab__count"><?= e((string) ($counts[$key] ?? 0)) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($cards === []) : ?>
        <p class="empty">Tidak ada antrean pada tab ini.</p>
    <?php else : ?>
        <div class="card-grid">
            <?php foreach ($cards as $queue) : ?>
                <article class="card">
                    <div class="card__top">
                        <span class="queue-number"><?= e(str_pad((string) $queue['queue_number'], 3, '0', STR_PAD_LEFT)) ?></span>
                        <span class="<?= e(status_class((string) $queue['status'])) ?>"><?= e(status_label((string) $queue['status'])) ?></span>
                    </div>
                    <strong class="card__title"><?= e((string) $queue['service_name']) ?></strong>
                    <p class="card__text">
                        <?= e((string) $queue['user_name']) ?> · kode <?= e((string) $queue['code']) ?><br>
                        <?= e(tanggal((string) $queue['queue_date'])) ?>
                        <?php if (!empty($queue['slot_time'])) : ?>
                            · sesi <?= e(substr((string) $queue['slot_time'], 0, 5)) ?>
                        <?php endif; ?>
                    </p>

                    <?php if (!empty($queue['note_user'])) : ?>
                        <p class="card__quote"><?= e((string) $queue['note_user']) ?></p>
                    <?php endif; ?>

                    <?php if ($tab === 'baru') : ?>
                        <form method="post" action="<?= e(url('/petugas/antrean/' . $queue['code'] . '/terima')) ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn--primary btn--block" type="submit">Terima antrean</button>
                        </form>
                    <?php elseif ($tab === 'saya') : ?>
                        <form method="post" action="<?= e(url('/petugas/antrean/' . $queue['code'] . '/status')) ?>" class="row-form">
                            <?= csrf_field() ?>
                            <label class="sr-only" for="status-<?= e((string) $queue['id']) ?>">Status baru</label>
                            <select id="status-<?= e((string) $queue['id']) ?>" name="status">
                                <?php foreach ((array) config('roles.transitions.' . $queue['status'], []) as $target) : ?>
                                    <option value="<?= e((string) $target) ?>">Ubah ke: <?= e(status_label((string) $target)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn--dark" type="submit">Simpan</button>
                        </form>

                        <form method="post" action="<?= e(url('/petugas/antrean/' . $queue['code'] . '/catatan')) ?>" class="stack-form">
                            <?= csrf_field() ?>
                            <label class="field">
                                <span class="field__label">Catatan penanganan</span>
                                <textarea name="note_officer" rows="3"><?= e((string) ($queue['note_officer'] ?? '')) ?></textarea>
                            </label>
                            <div class="actions">
                                <button class="btn btn--ghost" type="submit">Simpan catatan</button>
                                <a class="btn btn--ghost" href="<?= e(url('/petugas/antrean/' . $queue['code'] . '/chat')) ?>">Chat warga</a>
                            </div>
                        </form>
                    <?php else : ?>
                        <p class="card__foot">
                            Selesai <?= e(jam((string) ($queue['finished_at'] ?? ''))) ?>
                            · <a href="<?= e(url('/petugas/antrean/' . $queue['code'] . '/chat')) ?>">lihat percakapan</a>
                        </p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<h2 class="section-title">Rekap · <?= e((string) $summary['label']) ?></h2>
<nav class="chip-row" aria-label="Periode rekap">
    <?php foreach (['hari' => 'Hari ini', 'minggu' => 'Minggu ini', 'bulan' => 'Bulan ini', 'tahun' => 'Tahun ini'] as $key => $label) : ?>
        <a class="chip <?= $period === $key ? 'chip--on' : '' ?>" href="<?= e(url('/petugas/rekap?periode=' . $key)) ?>">
            <?= e($label) ?>
        </a>
    <?php endforeach; ?>
</nav>

<div class="stat-grid">
    <div class="stat"><strong><?= e((string) $summary['selesai']) ?></strong><span>Ditangani sampai selesai</span></div>
    <div class="stat"><strong><?= e((string) $summary['avg_handle']) ?> mnt</strong><span>Rata-rata proses</span></div>
    <div class="stat"><strong><?= e((string) ($summary['ditinjau'] + $summary['ditangani'])) ?></strong><span>Masih terbuka</span></div>
    <div class="stat"><strong><?= e((string) $summary['dibatalkan']) ?></strong><span>Dibatalkan</span></div>
</div>

<?php if ($byService !== []) : ?>
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
