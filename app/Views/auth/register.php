<?php

/**
 * @var array<int, array<string, mixed>> $districts
 */

?>
<section class="container section section--narrow">
    <h1 class="page-title">Buat akun warga</h1>
    <p class="lede">Data berikut dipakai untuk mengisi formulir antrean secara otomatis.</p>

    <!--
        Syarat verifikasi disebutkan sebelum formulirnya, bukan sesudah
        dikirim: alamat yang salah ketik baru ketahuan setelah emailnya tidak
        pernah datang, dan saat itu akunnya sudah telanjur tidak bisa dipakai.
    -->
    <div class="note">
        <p>
            <strong>Email Anda akan diverifikasi.</strong>
            Setelah mendaftar, kami mengirim satu tautan ke alamat itu — akunnya
            baru bisa dipakai masuk setelah tautannya dibuka. Pastikan alamatnya
            benar dan bisa Anda akses sekarang.
        </p>
        <p>
            Tidak ingin membuat akun? Antrean juga bisa diambil
            <a href="<?= e(url('/peta')) ?>">langsung tanpa akun</a>.
        </p>
    </div>

    <form method="post" action="<?= e(url('/daftar')) ?>" class="card form">
        <?= csrf_field() ?>

        <label class="field">
            <span class="field__label">Nama lengkap</span>
            <input type="text" name="name" value="<?= e(old('name')) ?>" maxlength="120" autocomplete="name" required>
            <?php if (errors('name')) : ?><span class="field-error"><?= e((string) errors('name')) ?></span><?php endif; ?>
        </label>

        <div class="field-row">
            <label class="field">
                <span class="field__label">NIK <small>(opsional, 16 digit)</small></span>
                <input type="text" name="nik" value="<?= e(old('nik')) ?>" inputmode="numeric" maxlength="16">
                <?php if (errors('nik')) : ?><span class="field-error"><?= e((string) errors('nik')) ?></span><?php endif; ?>
            </label>

            <label class="field">
                <span class="field__label">Nomor ponsel</span>
                <input type="tel" name="phone" value="<?= e(old('phone')) ?>" autocomplete="tel">
                <?php if (errors('phone')) : ?><span class="field-error"><?= e((string) errors('phone')) ?></span><?php endif; ?>
            </label>
        </div>

        <label class="field">
            <span class="field__label">Email</span>
            <input type="email" name="email" value="<?= e(old('email')) ?>" maxlength="160" autocomplete="email" required>
            <?php if (errors('email')) : ?><span class="field-error"><?= e((string) errors('email')) ?></span><?php endif; ?>
        </label>

        <div class="field-row">
            <label class="field">
                <span class="field__label">Kata sandi <small>(minimal 8 karakter)</small></span>
                <input type="password" name="password" autocomplete="new-password" required>
                <?php if (errors('password')) : ?><span class="field-error"><?= e((string) errors('password')) ?></span><?php endif; ?>
            </label>

            <label class="field">
                <span class="field__label">Ulangi sandi</span>
                <input type="password" name="password_confirmation" autocomplete="new-password" required>
                <?php if (errors('password_confirmation')) : ?><span class="field-error"><?= e((string) errors('password_confirmation')) ?></span><?php endif; ?>
            </label>
        </div>

        <label class="field">
            <span class="field__label">Kecamatan domisili</span>
            <select name="district_id">
                <option value="">— pilih —</option>
                <?php foreach ($districts as $district) : ?>
                    <option value="<?= e((string) $district['id']) ?>" <?= old('district_id') === (string) $district['id'] ? 'selected' : '' ?>>
                        <?= e((string) $district['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="check">
            <input type="checkbox" name="setuju" value="1" required>
            <span>
                Saya menyatakan data di atas benar dan bersedia data tersebut dipakai untuk
                proses layanan.
            </span>
        </label>
        <?php if (errors('setuju')) : ?><p class="field-error"><?= e((string) errors('setuju')) ?></p><?php endif; ?>

        <button class="btn btn--primary btn--block" type="submit">Daftar sekarang</button>

        <p class="side-note">Sudah punya akun? <a href="<?= e(url('/masuk')) ?>">Masuk di sini</a></p>
    </form>
</section>
