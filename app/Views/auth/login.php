<?php

/**
 * @var string $next
 */

use App\Core\Session;

// Diisi AuthController saat sandinya benar tetapi emailnya belum
// terverifikasi. Formulir kirim ulang muncul di sini, bukan di halaman
// lain, supaya alamatnya tidak perlu dibawa lewat query string.
$belumVerif = (string) Session::getFlash('email_belum_verif', '');

?>
<section class="container section section--narrow">
    <h1 class="page-title">Masuk ke akun Anda</h1>
    <p class="lede">
        Warga, petugas, dan admin memakai formulir yang sama. Hak akses mengikuti peran akun.
    </p>

    <form method="post" action="<?= e(url('/masuk')) ?>" class="card form">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">

        <label class="field">
            <span class="field__label">Email</span>
            <input type="email" name="email" value="<?= e(old('email')) ?>" autocomplete="email" required>
            <?php if (errors('email')) : ?>
                <span class="field-error"><?= e((string) errors('email')) ?></span>
            <?php endif; ?>
        </label>

        <label class="field">
            <span class="field__label">Kata sandi</span>
            <input type="password" name="password" autocomplete="current-password" required>
            <?php if (errors('password')) : ?>
                <span class="field-error"><?= e((string) errors('password')) ?></span>
            <?php endif; ?>
        </label>

        <button class="btn btn--primary btn--block" type="submit">Masuk</button>

        <p class="side-note">
            Belum punya akun? <a href="<?= e(url('/daftar')) ?>">Daftar sebagai warga</a>
        </p>
    </form>

    <?php if ($belumVerif !== '') : ?>
        <form method="post" action="<?= e(url('/verifikasi/kirim-ulang')) ?>" class="card form" id="kirim-ulang">
            <?= csrf_field() ?>
            <h2 class="card__title">Belum menerima emailnya?</h2>
            <p class="card__text">
                Tautan verifikasi berlaku
                <?= e((string) max(1, (int) config('mail.verification.ttl_hours', 24))) ?> jam.
                Kalau sudah lewat atau emailnya tidak pernah sampai, minta yang baru di sini —
                tautan lama otomatis batal.
            </p>

            <label class="field">
                <span class="field__label">Email</span>
                <input type="email" name="email" value="<?= e($belumVerif) ?>" autocomplete="email" required>
            </label>

            <button class="btn btn--dark" type="submit">Kirim tautan baru</button>
        </form>
    <?php endif; ?>
</section>
