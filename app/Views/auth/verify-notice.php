<?php

/**
 * Halaman setelah mendaftar: "periksa email Anda", sekaligus tempat
 * meminta tautan baru.
 *
 * @var string $email
 * @var bool $siap
 * @var int $jam
 */

?>
<section class="container section section--narrow">
    <h1 class="page-title">Periksa email Anda</h1>
    <p class="lede">
        <?php if ($email !== '') : ?>
            Tautan verifikasi dikirim ke <strong><?= e($email) ?></strong>.
        <?php else : ?>
            Tautan verifikasi dikirim ke alamat email yang Anda daftarkan.
        <?php endif; ?>
        Bukalah tautan itu untuk mengaktifkan akun — sebelum itu, akunnya belum
        bisa dipakai masuk.
    </p>

    <?php if (!$siap) : ?>
        <div class="note note--warn">
            <strong>SMTP belum disetel di server ini.</strong>
            Email verifikasi tidak akan terkirim sampai pengelola mengisi
            <code>MAIL_HOST</code> dan seterusnya di <code>.env</code>. Akun Anda
            sudah tercatat, tetapi belum bisa diaktifkan sendiri.
        </div>
    <?php endif; ?>

    <div class="card">
        <h2 class="card__title">Emailnya belum sampai?</h2>
        <ul class="steps">
            <li>Tunggu sebentar — pengiriman kadang tertahan satu dua menit.</li>
            <li>Periksa folder <strong>spam</strong> atau <strong>promosi</strong>.</li>
            <li>Pastikan alamatnya tidak salah ketik.</li>
        </ul>
        <p class="card__text">
            Tautan berlaku <?= e((string) $jam) ?> jam dan hanya sekali pakai.
            Meminta yang baru membuat tautan lama batal.
        </p>
    </div>

    <form method="post" action="<?= e(url('/verifikasi/kirim-ulang')) ?>" class="card form">
        <?= csrf_field() ?>

        <label class="field">
            <span class="field__label">Email</span>
            <input type="email" name="email" value="<?= e(old('email', $email)) ?>" autocomplete="email" required>
            <span class="side-note">
                Demi keamanan, jawabannya sama saja apakah alamat itu terdaftar
                atau tidak.
            </span>
            <?php if (errors('email')) : ?>
                <span class="field-error"><?= e((string) errors('email')) ?></span>
            <?php endif; ?>
        </label>

        <button class="btn btn--primary" type="submit">Kirim tautan baru</button>

        <p class="side-note">
            Sudah terverifikasi? <a href="<?= e(url('/masuk')) ?>">Masuk ke akun</a>
        </p>
    </form>
</section>
