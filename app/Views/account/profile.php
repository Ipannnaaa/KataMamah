<?php

/**
 * @var array<string, mixed> $user
 * @var array<int, array<string, mixed>> $districts
 * @var array<string, int|float> $stats
 */

?>
<section class="container section">
    <nav class="subnav" aria-label="Menu akun">
        <a href="<?= e(url('/akun')) ?>">Antrean saya</a>
        <a href="<?= e(url('/akun/rekap')) ?>">Rekap riwayat</a>
        <a class="is-active" href="<?= e(url('/akun/profil')) ?>">Profil peserta</a>
    </nav>

    <h1 class="page-title">Profil peserta</h1>
    <p class="lede">
        Data di sini dipakai untuk mengisi formulir antrean secara otomatis. Perubahan NIK
        memerlukan verifikasi ulang oleh admin.
    </p>

    <div class="split">
        <form method="post" action="<?= e(url('/akun/profil')) ?>" class="split__main stack">
            <?= csrf_field() ?>

            <fieldset class="card">
                <legend class="card__title">Data pribadi</legend>

                <label class="field">
                    <span class="field__label">Nama lengkap</span>
                    <input type="text" name="name" value="<?= e(old('name', (string) $user['name'])) ?>" maxlength="120" required>
                    <?php if (errors('name')) : ?><span class="field-error"><?= e((string) errors('name')) ?></span><?php endif; ?>
                </label>

                <div class="field-row">
                    <label class="field">
                        <span class="field__label">NIK</span>
                        <input type="text" name="nik" value="<?= e(old('nik', (string) ($user['nik'] ?? ''))) ?>" inputmode="numeric" maxlength="16">
                        <?php if (errors('nik')) : ?><span class="field-error"><?= e((string) errors('nik')) ?></span><?php endif; ?>
                    </label>

                    <label class="field">
                        <span class="field__label">Tanggal lahir</span>
                        <input type="date" name="birth_date" value="<?= e(old('birth_date', (string) ($user['birth_date'] ?? ''))) ?>">
                    </label>
                </div>

                <div class="field-row">
                    <label class="field">
                        <span class="field__label">Jenis kelamin</span>
                        <select name="gender">
                            <option value="">— pilih —</option>
                            <option value="L" <?= (string) ($user['gender'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                            <option value="P" <?= (string) ($user['gender'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
                        </select>
                    </label>

                    <label class="field">
                        <span class="field__label">Kecamatan domisili</span>
                        <select name="district_id">
                            <option value="">— pilih —</option>
                            <?php foreach ($districts as $district) : ?>
                                <option value="<?= e((string) $district['id']) ?>"
                                    <?= (int) ($user['district_id'] ?? 0) === (int) $district['id'] ? 'selected' : '' ?>>
                                    <?= e((string) $district['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="field-row">
                    <label class="field">
                        <span class="field__label">Email</span>
                        <input type="email" name="email" value="<?= e(old('email', (string) $user['email'])) ?>" maxlength="160" required>
                        <?php if (errors('email')) : ?><span class="field-error"><?= e((string) errors('email')) ?></span><?php endif; ?>
                    </label>

                    <label class="field">
                        <span class="field__label">Nomor ponsel</span>
                        <input type="tel" name="phone" value="<?= e(old('phone', (string) ($user['phone'] ?? ''))) ?>">
                        <?php if (errors('phone')) : ?><span class="field-error"><?= e((string) errors('phone')) ?></span><?php endif; ?>
                    </label>
                </div>

                <label class="field">
                    <span class="field__label">Alamat lengkap</span>
                    <textarea name="address" rows="2" maxlength="255"><?= e(old('address', (string) ($user['address'] ?? ''))) ?></textarea>
                </label>
            </fieldset>

            <fieldset class="card">
                <legend class="card__title">Keamanan akun</legend>
                <p class="card__text">Biarkan kosong bila tidak ingin mengganti sandi.</p>

                <label class="field">
                    <span class="field__label">Sandi saat ini</span>
                    <input type="password" name="password_current" autocomplete="current-password">
                    <?php if (errors('password_current')) : ?><span class="field-error"><?= e((string) errors('password_current')) ?></span><?php endif; ?>
                </label>

                <div class="field-row">
                    <label class="field">
                        <span class="field__label">Sandi baru</span>
                        <input type="password" name="password" autocomplete="new-password">
                        <?php if (errors('password')) : ?><span class="field-error"><?= e((string) errors('password')) ?></span><?php endif; ?>
                    </label>

                    <label class="field">
                        <span class="field__label">Ulangi sandi baru</span>
                        <input type="password" name="password_confirmation" autocomplete="new-password">
                        <?php if (errors('password_confirmation')) : ?><span class="field-error"><?= e((string) errors('password_confirmation')) ?></span><?php endif; ?>
                    </label>
                </div>
            </fieldset>

            <p class="actions">
                <button class="btn btn--primary" type="submit">Simpan perubahan</button>
                <a class="btn btn--ghost" href="<?= e(url('/akun')) ?>">Batal</a>
            </p>
        </form>

        <aside class="split__side">
            <div class="card">
                <p class="label">Ringkasan akun</p>
                <dl class="summary">
                    <div><dt>Peran</dt><dd><?= e((string) config('roles.list.' . $user['role'], 'Warga')) ?></dd></div>
                    <div><dt>Akun dibuat</dt><dd><?= e(tanggal((string) $user['created_at'])) ?></dd></div>
                    <div><dt>Total antrean</dt><dd><?= e((string) $stats['total']) ?></dd></div>
                    <div><dt>Antrean aktif</dt><dd><?= e((string) $stats['aktif']) ?></dd></div>
                    <div>
                        <dt>Masuk terakhir</dt>
                        <dd><?= $user['last_login_at'] !== null ? e(tanggal((string) $user['last_login_at'])) : '-' ?></dd>
                    </div>
                </dl>
            </div>
        </aside>
    </div>
</section>
