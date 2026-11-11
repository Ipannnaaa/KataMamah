<?php

/**
 * Dashboard admin lokasi. Satu view, beberapa fokus:
 * ringkasan | antrean | pembayaran | rekap | petugas.
 *
 * @var string $focus
 * @var array<string, mixed>|null $location
 * @var array<string, int> $counts
 * @var array<string, mixed> $summary
 * @var array<int, array{label: string, total: int}> $perHour
 * @var array<int, array<string, mixed>> $perDay
 * @var array<int, array<string, mixed>> $queues
 * @var array<int, array<string, mixed>> $payments
 * @var array<string, float> $paymentTotals
 * @var array<int, array<string, mixed>> $officers
 * @var array<int, array{label: string, total: int}> $byService
 * @var string $filter
 * @var string $period
 */

$byOfficer = $byOfficer ?? [];
$tren = $tren ?? [];
$cari = $cari ?? '';
$pilihanKantor = $pilihanKantor ?? [];
$kode = $kode ?? null;
$hourMax = 1;

foreach ($perHour as $row) {
    $hourMax = max($hourMax, $row['total']);
}

$serviceMax = 1;

foreach ($byService as $row) {
    $serviceMax = max($serviceMax, $row['total']);
}

// Bar per petugas dulu ikut memakai $serviceMax, padahal itu angka
// tertinggi antarlayanan. Akibatnya batang petugas tersibuk tidak pernah
// penuh dan perbandingan antarpetugas jadi sulit dibaca.
$officerMax = 1;

foreach ($byOfficer as $row) {
    $officerMax = max($officerMax, $row['total']);
}

?>
<div class="page-head">
    <div>
        <h1 class="page-title"><?= e((string) ($title ?? 'Ringkasan')) ?></h1>
        <p class="lede">
            <?= e(tanggal(date('Y-m-d'))) ?>
            <?php if ($location !== null) : ?>
                · <?= e((string) $location['name']) ?>
            <?php endif; ?>
        </p>
    </div>
    <p class="actions">
        <a class="btn btn--ghost" href="<?= e(url('/admin/rekap/export?periode=' . $period)) ?>">Unduh rekap CSV</a>
        <a class="btn btn--primary" href="<?= e(url('/admin/landmark')) ?>">Kelola landmark</a>
    </p>
</div>

<div class="stat-grid stat-grid--five">
    <div class="stat"><strong><?= e((string) $summary['total']) ?></strong><span>Antrean hari ini</span></div>
    <div class="stat"><strong><?= e((string) $counts['baru']) ?></strong><span>Menunggu ditinjau</span></div>
    <div class="stat"><strong><?= e((string) $counts['ditangani']) ?></strong><span>Sedang ditangani</span></div>
    <div class="stat"><strong><?= e((string) $counts['selesai']) ?></strong><span>Telah selesai</span></div>
    <div class="stat"><strong><?= e(rupiah($paymentTotals['lunas'] ?? 0)) ?></strong><span>Pembayaran masuk</span></div>
</div>

<?php if ($perHour !== []) : ?>
    <section class="card">
        <h2 class="card__title">Antrean masuk per jam</h2>
        <p class="card__text">Dipakai untuk menata jumlah petugas per sesi.</p>
        <ul class="columns">
            <?php foreach ($perHour as $row) : ?>
                <li class="column"
                    data-judul="Pukul <?= e($row['label']) ?>.00"
                    data-isi="<?= e($row['total'] . ' antrean masuk') ?>">
                    <span class="column__value"><?= e((string) $row['total']) ?></span>
                    <span class="column__bar" style="height: <?= e((string) round(($row['total'] / $hourMax) * 100)) ?>%"></span>
                    <span class="column__label"><?= e($row['label']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php if ($tren !== [] && in_array($focus, ['ringkasan', 'rekap'], true)) : ?>
    <section class="card">
        <?= \App\Core\View::partial('grafik-garis', [
            'id' => 'tren-antrean',
            'judul' => 'Tren antrean ' . count($tren) . ' hari terakhir',
            'titik' => $tren,
            'deret' => [
                ['kunci' => 'total', 'nama' => 'Total', 'warna' => '#0e6b5c'],
                ['kunci' => 'selesai', 'nama' => 'Selesai', 'warna' => '#1f5fa8'],
                ['kunci' => 'dibatalkan', 'nama' => 'Dibatalkan', 'warna' => '#a64f12'],
            ],
        ]) ?>
        <p class="card__text">
            Arahkan kursor ke grafik untuk melihat angka per hari, termasuk rata-rata
            waktu tunggu. Klik nama seri untuk menyembunyikannya.
        </p>
    </section>

    <section class="card">
        <?= \App\Core\View::partial('grafik-donat', [
            'id' => 'komposisi-status',
            'judul' => 'Komposisi status ' . mb_strtolower((string) $summary['label']),
            'bagian' => [
                ['nama' => 'Menunggu ditinjau', 'nilai' => (int) $summary['baru'], 'warna' => '#8a978f'],
                ['nama' => 'Diterima petugas', 'nilai' => (int) $summary['ditinjau'], 'warna' => '#1f5fa8'],
                ['nama' => 'Sedang ditangani', 'nilai' => (int) $summary['ditangani'], 'warna' => '#0e6b5c'],
                ['nama' => 'Selesai', 'nilai' => (int) $summary['selesai'], 'warna' => '#09453b'],
                ['nama' => 'Dibatalkan', 'nilai' => (int) $summary['dibatalkan'], 'warna' => '#8a2e2e'],
            ],
        ]) ?>
    </section>
<?php endif; ?>

<?php if (in_array($focus, ['ringkasan', 'antrean'], true)) : ?>
    <section class="card">
        <div class="card__head">
            <h2 class="card__title">Informasi antrean</h2>
            <nav class="chip-row" aria-label="Filter status">
                <a class="chip <?= $filter === '' ? 'chip--on' : '' ?>" href="<?= e(url('/admin/antrean')) ?>">Semua</a>
                <?php foreach ((array) config('roles.status', []) as $key => $label) : ?>
                    <a class="chip <?= $filter === (string) $key ? 'chip--on' : '' ?>"
                       href="<?= e(url('/admin/antrean?status=' . $key)) ?>"><?= e((string) $label) ?></a>
                <?php endforeach; ?>
            </nav>
        </div>

        <?php if ($focus === 'antrean') : ?>
            <form class="cari-kode" method="get" action="<?= e(url('/admin/antrean')) ?>" role="search">
                <label class="field field--inline">
                    <span class="field__label">Cari kode pemesanan</span>
                    <input type="search" name="cari" value="<?= e($cari) ?>"
                           placeholder="TCM-XXXXX" autocomplete="off" spellcheck="false">
                </label>
                <button class="btn btn--primary" type="submit">Cari</button>
                <?php if ($cari !== '') : ?>
                    <a class="btn btn--ghost" href="<?= e(url('/admin/antrean')) ?>">Bersihkan</a>
                <?php endif; ?>
            </form>

            <?php if ($cari !== '') : ?>
                <p class="card__text">
                    Hasil pencarian <strong><?= e((string) $kode) ?></strong>
                    <?= $location === null
                        ? 'di seluruh kabupaten.'
                        : 'di ' . e((string) $location['name']) . '. Antrean kantor lain tidak ditampilkan.' ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($queues === []) : ?>
            <p class="empty">Tidak ada antrean pada filter ini.</p>
        <?php else : ?>
            <table class="table">
                <caption class="sr-only">Daftar antrean lokasi</caption>
                <thead>
                    <tr>
                        <th scope="col">Kode</th>
                        <th scope="col">Warga &amp; layanan</th>
                        <th scope="col">Tanggal</th>
                        <th scope="col">Status</th>
                        <th scope="col">Petugas</th>
                        <th scope="col">Pembayaran</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($queues as $queue) : ?>
                        <tr>
                            <th scope="row">
                                <a href="<?= e(url('/admin/antrean/' . $queue['code'])) ?>"><?= e((string) $queue['code']) ?></a>
                            </th>
                            <td>
                                <strong><?= e((string) $queue['user_name']) ?></strong><br>
                                <small><?= e((string) $queue['service_name']) ?> · nomor <?= e(str_pad((string) $queue['queue_number'], 3, '0', STR_PAD_LEFT)) ?></small>
                                <?php if ($location === null) : ?>
                                    <?php /* Super admin melihat seluruh kabupaten: tanpa nama kantor,
                                             kode yang ketemu lewat pencarian tidak memberi tahu
                                             antrean itu ada di mana. Admin kantor tidak perlu, semua
                                             barisnya memang kantornya sendiri. */ ?>
                                    <br><small class="muted"><?= e((string) $queue['location_name']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= e(tanggal((string) $queue['queue_date'])) ?></td>
                            <td><span class="<?= e(status_class((string) $queue['status'])) ?>"><?= e(status_label((string) $queue['status'])) ?></span></td>
                            <td><?= e((string) ($queue['officer_name'] ?? 'Belum ditugaskan')) ?></td>
                            <td><?= e(pay_label((string) $queue['payment_status'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if (in_array($focus, ['ringkasan', 'pembayaran'], true)) : ?>
    <section class="card">
        <div class="card__head">
            <h2 class="card__title">Informasi pembayaran</h2>
            <p class="card__meta">
                Menunggu verifikasi: <strong><?= e(rupiah($paymentTotals['menunggu'] ?? 0)) ?></strong>
            </p>
        </div>

        <?php if ($payments === []) : ?>
            <p class="empty">Belum ada tagihan pada lokasi ini.</p>
        <?php else : ?>
            <table class="table">
                <caption class="sr-only">Daftar pembayaran</caption>
                <thead>
                    <tr>
                        <th scope="col">Kode</th>
                        <th scope="col">Warga</th>
                        <th scope="col">Nominal</th>
                        <th scope="col">Metode</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment) : ?>
                        <tr>
                            <th scope="row"><?= e((string) $payment['code']) ?></th>
                            <td><?= e((string) $payment['user_name']) ?></td>
                            <td><?= e(rupiah((float) $payment['amount'])) ?></td>
                            <td><?= e((string) $payment['method']) ?></td>
                            <td><?= e(pay_label((string) $payment['status'])) ?></td>
                            <td>
                                <?php if ((string) $payment['status'] === 'menunggu') : ?>
                                    <form method="post" action="<?= e(url('/admin/pembayaran/' . $payment['id'] . '/lunas')) ?>">
                                        <?= csrf_field() ?>
                                        <button class="btn btn--small btn--primary" type="submit">Tandai lunas</button>
                                    </form>
                                <?php else : ?>
                                    <small><?= $payment['paid_at'] !== null ? e(tanggal((string) $payment['paid_at'])) : '-' ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if (in_array($focus, ['ringkasan', 'rekap'], true)) : ?>
    <section class="card">
        <div class="card__head">
            <h2 class="card__title">Rekap per hari</h2>
            <nav class="chip-row" aria-label="Periode rekap">
                <?php foreach (['hari' => 'Hari', 'minggu' => 'Minggu', 'bulan' => 'Bulan', 'tahun' => 'Tahun'] as $key => $label) : ?>
                    <a class="chip <?= $period === $key ? 'chip--on' : '' ?>" href="<?= e(url('/admin/rekap?periode=' . $key)) ?>">
                        <?= e($label) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <div class="stat-grid">
            <div class="stat"><strong><?= e((string) $summary['selesai']) ?></strong><span>Selesai</span></div>
            <div class="stat"><strong><?= e((string) $summary['avg_wait']) ?> mnt</strong><span>Rata-rata tunggu</span></div>
            <div class="stat"><strong><?= e((string) $summary['avg_handle']) ?> mnt</strong><span>Rata-rata proses</span></div>
            <div class="stat"><strong><?= e(rupiah((float) $summary['paid'])) ?></strong><span>Pembayaran lunas</span></div>
        </div>

        <?php if ($perDay !== []) : ?>
            <table class="table">
                <caption class="sr-only">Rekap harian</caption>
                <thead>
                    <tr>
                        <th scope="col">Tanggal</th>
                        <th scope="col">Total</th>
                        <th scope="col">Selesai</th>
                        <th scope="col">Dibatalkan</th>
                        <th scope="col">Rata-rata tunggu</th>
                        <th scope="col">Pembayaran</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($perDay as $row) : ?>
                        <tr>
                            <th scope="row"><?= e(tanggal((string) $row['queue_date'])) ?></th>
                            <td><?= e((string) $row['total']) ?></td>
                            <td><?= e((string) $row['selesai']) ?></td>
                            <td><?= e((string) $row['dibatalkan']) ?></td>
                            <td><?= e((string) (int) round((float) $row['avg_wait'])) ?> mnt</td>
                            <td><?= e(rupiah((float) $row['paid'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($byService !== []) : ?>
            <h3 class="label">Per layanan</h3>
            <ul class="bars">
                <?php foreach ($byService as $row) : ?>
                    <li class="bar">
                        <span class="bar__label"><?= e($row['label']) ?></span>
                        <span class="bar__track">
                            <span class="bar__fill" style="width: <?= e((string) round(($row['total'] / $serviceMax) * 100)) ?>%"></span>
                        </span>
                        <span class="bar__value"><?= e((string) $row['total']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($byOfficer !== []) : ?>
            <h3 class="label">Per petugas</h3>
            <ul class="bars">
                <?php foreach ($byOfficer as $row) : ?>
                    <li class="bar">
                        <span class="bar__label"><?= e($row['label']) ?></span>
                        <span class="bar__track">
                            <span class="bar__fill" style="width: <?= e((string) round(($row['total'] / $officerMax) * 100)) ?>%"></span>
                        </span>
                        <span class="bar__value"><?= e((string) $row['total']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if (in_array($focus, ['ringkasan', 'petugas'], true)) : ?>
    <section class="card">
        <h2 class="card__title">Petugas &amp; beban kerja</h2>

        <?php if ($officers === []) : ?>
            <p class="empty">Belum ada akun petugas pada lokasi ini.</p>
        <?php else : ?>
            <table class="table">
                <caption class="sr-only">Daftar petugas</caption>
                <thead>
                    <tr>
                        <th scope="col">Nama</th>
                        <th scope="col">Email</th>
                        <th scope="col">Antrean aktif</th>
                        <th scope="col">Selesai hari ini</th>
                        <th scope="col">Masuk terakhir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($officers as $officer) : ?>
                        <tr>
                            <th scope="row"><?= e((string) $officer['name']) ?></th>
                            <td><?= e((string) $officer['email']) ?></td>
                            <td><?= e((string) $officer['active_queues']) ?></td>
                            <td><?= e((string) $officer['finished_today']) ?></td>
                            <td><?= $officer['last_login_at'] !== null ? e(tanggal((string) $officer['last_login_at'])) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    
        <?php if ($focus === 'petugas') : ?>
            <h3 class="label">Tambah petugas</h3>
            <p class="card__text">
                Akunnya langsung aktif dan tidak perlu verifikasi email, karena
                alamatnya Anda yang isikan, bukan didaftarkan sendiri oleh
                pemiliknya. Beri tahu petugasnya sandi yang Anda buat, dan minta
                ia menggantinya setelah masuk pertama kali.
            </p>

            <form method="post" action="<?= e(url('/admin/petugas')) ?>" class="form">
                <?= csrf_field() ?>

                <?php if ($pilihanKantor !== []) : ?>
                    <label class="field">
                        <span class="field__label">Kantor</span>
                        <select name="location_id" required>
                            <option value="">— pilih kantor —</option>
                            <?php foreach ($pilihanKantor as $k) : ?>
                                <option value="<?= e((string) $k['id']) ?>"
                                    <?= old('location_id') === (string) $k['id'] ? ' selected' : '' ?>>
                                    <?= e((string) $k['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (errors('location_id')) : ?>
                            <span class="field-error"><?= e((string) errors('location_id')) ?></span>
                        <?php endif; ?>
                    </label>
                <?php else : ?>
                    <p class="side-note">
                        Petugas ditambahkan di <strong><?= e((string) ($location['name'] ?? '-')) ?></strong>.
                    </p>
                <?php endif; ?>

                <label class="field">
                    <span class="field__label">Nama petugas</span>
                    <input type="text" name="name" value="<?= e(old('name')) ?>" maxlength="120" required>
                    <?php if (errors('name')) : ?>
                        <span class="field-error"><?= e((string) errors('name')) ?></span>
                    <?php endif; ?>
                </label>

                <label class="field">
                    <span class="field__label">Email</span>
                    <input type="email" name="email" value="<?= e(old('email')) ?>" maxlength="160" required>
                    <?php if (errors('email')) : ?>
                        <span class="field-error"><?= e((string) errors('email')) ?></span>
                    <?php endif; ?>
                </label>

                <label class="field">
                    <span class="field__label">Nomor ponsel (boleh dikosongkan)</span>
                    <input type="text" name="phone" value="<?= e(old('phone')) ?>" maxlength="30">
                    <?php if (errors('phone')) : ?>
                        <span class="field-error"><?= e((string) errors('phone')) ?></span>
                    <?php endif; ?>
                </label>

                <label class="field">
                    <span class="field__label">Sandi awal (minimal 8 huruf)</span>
                    <input type="password" name="password" minlength="8" maxlength="72" required>
                    <?php if (errors('password')) : ?>
                        <span class="field-error"><?= e((string) errors('password')) ?></span>
                    <?php endif; ?>
                </label>

                <label class="field">
                    <span class="field__label">Ulangi sandi</span>
                    <input type="password" name="password_confirmation" minlength="8" maxlength="72" required>
                    <?php if (errors('password_confirmation')) : ?>
                        <span class="field-error"><?= e((string) errors('password_confirmation')) ?></span>
                    <?php endif; ?>
                </label>

                <button class="btn btn--primary" type="submit">Tambah petugas</button>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>
