<?php

/**
 * Cek antrean dengan kode pemesanan (tanpa login).
 *
 * @var string $code
 * @var array<string, mixed>|null $queue
 * @var array<int, array<string, mixed>> $logs
 * @var bool $notFound
 * @var bool $limited
 */

use App\Core\View;

?>
<section class="container section section--narrow">
    <p class="eyebrow">Cek antrean</p>
    <h1 class="page-title">Masukkan kode pemesanan</h1>
    <p class="lede">
        Kode diberikan saat Anda mendaftar antrean, formatnya
        <strong><?= e((string) config('app.queue.code_prefix')) ?>-XXXXX</strong>.
        Tidak perlu masuk untuk melihat status.
    </p>

    <form method="post" action="<?= e(url('/cek-antrean')) ?>" class="card code-form">
        <?= csrf_field() ?>
        <label class="field">
            <span class="field__label">Kode pemesanan</span>
            <input
                type="text"
                name="code"
                value="<?= e($code) ?>"
                class="input-code"
                data-prefix="<?= e((string) config('app.queue.code_prefix')) ?>"
                placeholder="<?= e((string) config('app.queue.code_prefix')) ?>-XXXXX"
                maxlength="12"
                autocapitalize="characters"
                spellcheck="false"
                required
            >
        </label>
        <button class="btn btn--primary" type="submit">Cek status</button>
    </form>

    <?php if ($limited) : ?>
        <p class="flash flash--bad">
            Terlalu banyak percobaan dari jaringan ini. Coba lagi beberapa menit lagi.
        </p>
    <?php endif; ?>

    <?php if ($notFound) : ?>
        <div class="card card--bad">
            <strong class="card__title">Kode tidak ditemukan</strong>
            <p class="card__text">
                Periksa kembali huruf dan angkanya. Kode tidak memakai huruf I, L, O, dan U
                untuk menghindari kekeliruan.
            </p>
        </div>
    <?php endif; ?>

    <?php if ($queue !== null) : ?>
        <div class="card">
            <div class="result-head">
                <div class="result-number">
                    <p class="eyebrow eyebrow--light">Nomor urut</p>
                    <p class="result-number__value"><?= e(str_pad((string) $queue['number'], 3, '0', STR_PAD_LEFT)) ?></p>
                </div>
                <div>
                    <span class="<?= e(status_class((string) $queue['status'])) ?>"><?= e(status_label((string) $queue['status'])) ?></span>
                    <h2 class="side-title"><?= e((string) $queue['service_name']) ?></h2>
                    <p class="card__text">
                        <?= e((string) $queue['location_name']) ?> · <?= e((string) $queue['district_name']) ?>
                    </p>
                </div>
            </div>

            <dl class="fact-grid">
                <div><dt>Pemohon</dt><dd><?= e((string) $queue['user_masked']) ?></dd></div>
                <div><dt>Tanggal layanan</dt><dd><?= e(tanggal((string) $queue['queue_date'])) ?></dd></div>
                <div>
                    <dt>Sesi</dt>
                    <dd><?= $queue['slot_time'] !== null ? e(substr((string) $queue['slot_time'], 0, 5)) : '-' ?></dd>
                </div>
                <div><dt>Menunggu di depan</dt><dd><?= e((string) $queue['ahead']) ?> pemohon</dd></div>
            </dl>

            <?= View::partial('timeline', ['logs' => $logs, 'showActor' => false]) ?>

            <div class="note">
                <strong>Catatan privasi</strong>
                <p>
                    Nama pemohon ditampilkan sebagian dan berkas disembunyikan. Masuk dengan
                    akun pemilik antrean untuk melihat detail lengkap dan percakapan dengan petugas.
                </p>
                <p class="actions">
                    <a class="btn btn--dark" href="<?= e(url('/masuk')) ?>">Masuk untuk detail</a>
                </p>
            </div>
        </div>
    <?php endif; ?>
</section>
