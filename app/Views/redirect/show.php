<?php

/**
 * Layar antara "Mengalihkan…".
 *
 * Perpindahannya sendiri diurus <meta http-equiv="refresh"> yang dipasang
 * layout lewat $head, jadi halaman ini tetap bekerja tanpa JavaScript.
 * Tautan di bawah untuk yang tidak mau menunggu.
 *
 * Wordmark dipakai ulang di sini, bukan ikon sendiri: siklusnya 6 detik dan
 * layar ini hanya hidup 2 detik, jadi yang terlihat persis bagian awalnya —
 * pin ditancapkan lalu nama muncul. Itu yang membuat jedanya terasa seperti
 * aplikasi sedang menyiapkan sesuatu, bukan seperti halaman tersangkut.
 *
 * @var string $tujuan
 * @var string $judul
 * @var string $ket
 * @var int $detik
 */

use App\Core\View;

?>
<section class="container section alih">
    <div class="alih__kartu">
        <?= View::partial('wordmark') ?>

        <h1 class="alih__judul"><?= e($judul) ?></h1>

        <?php if ($ket !== '') : ?>
            <p class="alih__ket"><?= e($ket) ?></p>
        <?php endif; ?>

        <!-- Bilah ini bukan hiasan: panjangnya mengikuti sisa waktu sebenarnya,
             jadi pengunjung tahu persis kapan halaman berpindah. -->
        <div class="alih__bar" role="progressbar" aria-label="Mengalihkan halaman"
             style="--tunda: <?= e((string) $detik) ?>s">
            <span class="alih__bar__isi"></span>
        </div>

        <p class="alih__status" aria-live="polite">Mengalihkan dalam <?= e((string) $detik) ?> detik…</p>

        <p class="alih__lanjut">
            <a class="btn btn--ghost" href="<?= e($tujuan) ?>">Lanjutkan sekarang</a>
        </p>
    </div>
</section>
