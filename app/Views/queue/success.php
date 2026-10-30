<?php

/**
 * @var array<string, mixed> $queue
 * @var array<int, array<string, mixed>> $documents
 * @var bool $guest  true bila antrean diambil tanpa akun
 */

$guest = $guest ?? false;

?>
<section class="container section section--narrow">
    <p class="chip chip--ok">Antrean tersimpan</p>
    <h1 class="page-title">Kode pemesanan Anda</h1>
    <p class="lede">
        Simpan kode ini. Kode dapat dipakai untuk memeriksa status tanpa login, dan
        ditunjukkan ke petugas saat datang.
    </p>

    <div class="code-card">
        <div>
            <p class="eyebrow eyebrow--light">Kode</p>
            <p class="code-card__code"><?= e((string) $queue['code']) ?></p>
            <p class="code-card__meta">
                Nomor urut <strong><?= e(str_pad((string) $queue['queue_number'], 3, '0', STR_PAD_LEFT)) ?></strong>
                · <?= e(tanggal((string) $queue['queue_date'])) ?>
                <?php if (!empty($queue['slot_time'])) : ?>
                    · sesi <?= e(substr((string) $queue['slot_time'], 0, 5)) ?>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <dl class="fact-grid">
        <div><dt>Lokasi</dt><dd><?= e((string) $queue['location_name']) ?></dd></div>
        <div><dt>Layanan</dt><dd><?= e((string) $queue['service_name']) ?></dd></div>
        <div><dt>Status</dt><dd><span class="<?= e(status_class((string) $queue['status'])) ?>"><?= e(status_label((string) $queue['status'])) ?></span></dd></div>
        <div><dt>Pembayaran</dt><dd><?= e(pay_label((string) $queue['payment_status'])) ?></dd></div>
    </dl>

    <?php if ($documents !== []) : ?>
        <h2 class="section-title">Berkas terlampir</h2>
        <ul class="file-list">
            <?php foreach ($documents as $document) : ?>
                <li>
                    <a href="<?= e(url('/berkas/' . $document['id'])) ?>"><?= e((string) $document['original_name']) ?></a>
                    <small><?= e((string) round(((int) $document['size_bytes']) / 1024)) ?> KB</small>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h2 class="section-title">Langkah berikutnya</h2>
    <ol class="steps">
        <li>Permohonan masuk ke papan antrean lokasi dan menunggu diklaim petugas.</li>
        <li>Bila ada kekurangan berkas, petugas menghubungi Anda melalui chat di halaman antrean.</li>
        <li>Datang sesuai sesi, tunjukkan kode <?= e((string) $queue['code']) ?>.</li>
        <li>Setelah selesai, antrean tercatat di rekap pribadi Anda.</li>
    </ol>

    <?php if ($guest) : ?>
        <div class="note note--warn">
            <strong>Simpan kode ini sekarang</strong>
            <p>
                Antrean ini diambil tanpa akun, jadi kode di atas adalah satu-satunya cara
                membuka statusnya. Ingin riwayat tersimpan otomatis?
                <a href="<?= e(url('/daftar')) ?>">Buat akun dengan email yang sama</a> —
                antrean ini akan langsung tergabung.
            </p>
        </div>

        <p class="actions">
            <a class="btn btn--primary" href="<?= e(url('/cek-antrean?kode=' . rawurlencode((string) $queue['code']))) ?>">Lacak antrean ini</a>
            <a class="btn btn--ghost" href="<?= e(url('/daftar')) ?>">Buat akun</a>
        </p>
    <?php else : ?>
        <p class="actions">
            <a class="btn btn--primary" href="<?= e(url('/akun/antrean/' . $queue['code'])) ?>">Lihat di halaman saya</a>
            <a class="btn btn--ghost" href="<?= e(url('/cek-antrean')) ?>">Cek status via kode</a>
        </p>
    <?php endif; ?>
</section>
