<?php

/**
 * Halaman setelah mendaftar: tempat memasukkan kode dari email, sekaligus
 * tempat meminta kode baru.
 *
 * Alamat emailnya ikut dikirim bersama kode, bukan hanya kodenya. Kode enam
 * angka harus dicocokkan per akun, bukan dicari ke seluruh tabel. Kolomnya
 * terisi otomatis dari sesi, jadi pengguna yang baru mendaftar tidak perlu
 * mengetiknya lagi.
 *
 * @var string $email
 * @var bool $siap
 * @var int $menit
 * @var int $percobaan
 */

?>
<section class="container section section--narrow">
    <h1 class="page-title">Masukkan kode verifikasi</h1>
    <p class="lede">
        <?php if ($email !== '') : ?>
            Kode enam angka dikirim ke <strong><?= e($email) ?></strong>.
        <?php else : ?>
            Kode enam angka dikirim ke alamat email yang Anda daftarkan.
        <?php endif; ?>
        Masukkan kode itu di bawah untuk mengaktifkan akun — sebelum itu,
        akunnya belum bisa dipakai masuk.
    </p>

    <?php if (!$siap) : ?>
        <div class="note note--warn">
            <strong>SMTP belum disetel di server ini.</strong>
            Email verifikasi tidak akan terkirim sampai pengelola mengisi
            <code>MAIL_HOST</code> dan seterusnya di <code>.env</code>. Akun Anda
            sudah tercatat, tetapi belum bisa diaktifkan sendiri.
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/verifikasi/kode')) ?>" class="card form">
        <?= csrf_field() ?>

        <label class="field">
            <span class="field__label">Email</span>
            <input type="email" name="email" value="<?= e(old('email', $email)) ?>"
                   autocomplete="email" required>
            <?php if (errors('email')) : ?>
                <span class="field-error"><?= e((string) errors('email')) ?></span>
            <?php endif; ?>
        </label>

        <label class="field">
            <span class="field__label">Kode verifikasi</span>
            <input type="text" name="kode" class="kode-input" required
                   inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                   autocomplete="one-time-code" placeholder="000000">
            <span class="side-note">
                Enam angka, berlaku <?= e((string) $menit) ?> menit. Salah
                <?= e((string) $percobaan) ?> kali membuat kodenya hangus dan
                harus diminta ulang.
            </span>
            <?php if (errors('kode')) : ?>
                <span class="field-error"><?= e((string) errors('kode')) ?></span>
            <?php endif; ?>
        </label>

        <button class="btn btn--primary" type="submit">Verifikasi</button>
    </form>

    <div class="card">
        <h2 class="card__title">Emailnya belum sampai?</h2>
        <ul class="steps">
            <li>Tunggu sebentar — pengiriman kadang tertahan satu dua menit.</li>
            <li>Periksa folder <strong>spam</strong> atau <strong>promosi</strong>.</li>
            <li>Pastikan alamatnya tidak salah ketik.</li>
        </ul>
        <p class="card__text">
            Meminta kode baru membuat kode lama batal.
        </p>

        <form method="post" action="<?= e(url('/verifikasi/kirim-ulang')) ?>" class="form">
            <?= csrf_field() ?>

            <label class="field">
                <span class="field__label">Email</span>
                <input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required>
                <span class="side-note">
                    Demi keamanan, jawabannya sama saja apakah alamat itu terdaftar
                    atau tidak.
                </span>
            </label>

            <button class="btn btn--ghost" type="submit">Kirim kode baru</button>
        </form>

        <p class="side-note">
            Sudah terverifikasi? <a href="<?= e(url('/masuk')) ?>">Masuk ke akun</a>
        </p>
    </div>
</section>
