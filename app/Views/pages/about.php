<?php

/**
 * @var array<string, mixed> $stats
 */

?>
<section class="container section section--narrow">
    <p class="eyebrow">About us</p>
    <h1 class="page-title">Tentang <?= e((string) config('app.name')) ?></h1>
    <p class="lede">
        <?= e((string) config('app.name')) ?> adalah portal informasi dan antrean layanan
        publik di Kabupaten <?= e((string) config('app.wilayah')) ?>. Warga dapat menelusuri
        kantor layanan lewat peta per kecamatan, membaca syarat berkasnya, lalu mendaftar
        antrean secara online tanpa perlu membuat akun.
    </p>

    <div class="stat-grid">
        <div class="stat">
            <strong><?= e((string) ($stats['districts'] ?? 0)) ?></strong>
            <span>Kecamatan terhubung</span>
        </div>
        <div class="stat">
            <strong><?= e((string) ($stats['locations'] ?? 0)) ?></strong>
            <span>Landmark kantor layanan</span>
        </div>
        <div class="stat">
            <strong><?= e((string) ($stats['services'] ?? 0)) ?></strong>
            <span>Jenis layanan terdaftar</span>
        </div>
        <div class="stat">
            <strong><?= e((string) ($stats['queues'] ?? 0)) ?></strong>
            <span>Antrean tercatat</span>
        </div>
    </div>

    <h2 class="section-title">Bagaimana alurnya</h2>
    <ol class="steps">
        <li><strong>Pilih lokasi</strong> dari peta atau daftar landmark per kecamatan.</li>
        <li><strong>Baca syarat berkas</strong> pada layanan yang Anda tuju.</li>
        <li><strong>Daftar antrean</strong> dengan akun warga, pilih tanggal dan sesi.</li>
        <li><strong>Simpan kode pemesanan</strong>, pantau statusnya, dan datang sesuai sesi.</li>
    </ol>

    <h2 class="section-title">Status antrean</h2>
    <dl class="definition">
        <?php foreach ((array) config('roles.status', []) as $key => $label) : ?>
            <dt><span class="<?= e(status_class((string) $key)) ?>"><?= e((string) $label) ?></span></dt>
            <dd>
                <?php
                $penjelasan = [
                    'baru' => 'Permohonan masuk dan menunggu diklaim petugas.',
                    'ditinjau' => 'Petugas sedang memeriksa kelengkapan berkas.',
                    'ditangani' => 'Permohonan sedang diproses.',
                    'selesai' => 'Permohonan selesai dan tercatat di rekap.',
                    'dibatalkan' => 'Dibatalkan oleh warga atau admin, dengan alasan tercatat.',
                ];
                echo e($penjelasan[$key] ?? '');
                ?>
            </dd>
        <?php endforeach; ?>
    </dl>

    <h2 class="section-title">Kontak</h2>
    <p class="lede">
        Pertanyaan tentang satu layanan sebaiknya diajukan langsung ke lokasi yang
        bersangkutan; kontaknya tercantum di halaman masing-masing lokasi.
    </p>
    <p class="actions">
        <a class="btn btn--primary" href="<?= e(url('/peta')) ?>">Buka peta</a>
        <a class="btn btn--ghost" href="<?= e(url('/cek-antrean')) ?>">Cek antrean</a>
    </p>
</section>
