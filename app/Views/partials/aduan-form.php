<?php

/**
 * Form aduan untuk satu kantor layanan.
 *
 * Terbuka tanpa akun. Yang menemukan data salah biasanya pengunjung yang baru
 * pertama datang ke sana, dan memaksa mereka mendaftar dulu berarti aduannya
 * tidak pernah masuk.
 *
 * @var array<string, mixed> $location
 */

use App\Core\Auth;
use App\Models\FacilityReport;

$perkiraan = ($location['data_source'] ?? 'perkiraan') !== 'osm';

?>
<section class="aduan" id="aduan">
    <h2 class="section-title">Ada yang tidak sesuai di sini?</h2>

    <p class="aduan__lede">
        Halaman ini disusun dari data terbuka dan belum seluruhnya dikonfirmasi ke
        instansi. Kalau Anda menemukan yang keliru — alamat, jam buka, syarat
        berkas — atau pelayanannya tidak sesuai, beri tahu kami.
    </p>

    <?php if ($perkiraan) : ?>
        <div class="note note--warn">
            <strong>Lokasi kantor ini belum terverifikasi</strong>
            <p>
                Titiknya masih perkiraan di tengah wilayah kecamatan, bukan alamat
                sebenarnya. Kalau Anda tahu lokasi tepatnya, tulis di aduan di bawah.
            </p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/lokasi/' . $location['slug'] . '/aduan')) ?>" class="card form">
        <?= csrf_field() ?>

        <label class="field">
            <span class="field__label">Jenis aduan</span>
            <select name="kategori" required>
                <?php foreach (FacilityReport::KATEGORI as $nilai => $label) : ?>
                    <option value="<?= e($nilai) ?>"<?= old('kategori') === $nilai ? ' selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (errors('kategori')) : ?>
                <span class="field-error"><?= e((string) errors('kategori')) ?></span>
            <?php endif; ?>
        </label>

        <label class="field">
            <span class="field__label">Apa yang perlu diperbaiki?</span>
            <textarea name="aduan" rows="4" required minlength="15" maxlength="2000"
                      placeholder="Contoh: alamatnya sudah pindah ke Jalan …, dan hari Jumat hanya buka sampai pukul 11.00."><?= e(old('aduan')) ?></textarea>
            <span class="side-note">Minimal 15 huruf, supaya admin punya cukup keterangan untuk menindaklanjuti.</span>
            <?php if (errors('aduan')) : ?>
                <span class="field-error"><?= e((string) errors('aduan')) ?></span>
            <?php endif; ?>
        </label>

        <label class="field">
            <span class="field__label">Kontak Anda (boleh dikosongkan)</span>
            <input type="text" name="kontak" maxlength="160"
                   value="<?= e(old('kontak', Auth::check() ? (string) Auth::name() : '')) ?>"
                   placeholder="Nomor HP atau email, kalau bersedia dihubungi">
            <span class="side-note">Aduan anonim tetap diterima. Kontak hanya dipakai bila admin perlu bertanya.</span>
            <?php if (errors('kontak')) : ?>
                <span class="field-error"><?= e((string) errors('kontak')) ?></span>
            <?php endif; ?>
        </label>

        <button class="btn btn--primary" type="submit">Kirim aduan</button>
    </form>
</section>
