<?php

/**
 * Kotak aspirasi warga.
 *
 * Halaman ini sengaja berdiri sendiri, terpisah dari form aduan di halaman
 * kantor. Aduan menunjuk satu kantor dan ditangani admin kantor itu;
 * aspirasi berlaku se-kabupaten dan dibaca super admin. Mencampurnya akan
 * membuat warga menebak-nebak masukannya sampai ke siapa.
 */

use App\Models\Aspiration;

?>
<section class="container section section--narrow">
    <p class="eyebrow">Suara warga</p>
    <h1 class="page-title">Kotak aspirasi</h1>
    <p class="lede">
        Punya usulan layanan baru, saran perbaikan, atau masukan untuk situs ini?
        Tulis di sini. Masukan yang menyangkut satu kantor tertentu lebih baik
        dikirim lewat form aduan di halaman kantornya, supaya langsung sampai ke
        admin yang menanganinya.
    </p>

    <div class="note">
        <p>
            Tidak perlu akun dan boleh anonim. Yang paling punya masukan soal
            layanan publik justru orang yang jarang berurusan dengan birokrasi,
            dan mewajibkan mendaftar dulu berarti masukan itu tidak pernah masuk.
        </p>
        <p>
            Yang masuk dibaca super admin dan diberi status, sama seperti aduan
            fasilitas. Pengiriman dibatasi 3 kali per 15 menit per perangkat.
        </p>
    </div>

    <form method="post" action="<?= e(url('/aspirasi')) ?>" class="card form">
        <?= csrf_field() ?>

        <label class="field">
            <span class="field__label">Topik</span>
            <select name="topik" required>
                <?php foreach (Aspiration::TOPIK as $nilai => $label) : ?>
                    <option value="<?= e($nilai) ?>"<?= old('topik') === $nilai ? ' selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (errors('topik')) : ?>
                <span class="field-error"><?= e((string) errors('topik')) ?></span>
            <?php endif; ?>
        </label>

        <label class="field">
            <span class="field__label">Aspirasi Anda</span>
            <textarea name="aspirasi" rows="6" required minlength="25" maxlength="2000"
                      placeholder="Contoh: perpanjangan SIM sebaiknya bisa dilayani di kantor kecamatan, karena dari desa saya ke Samsat butuh dua jam naik angkutan."><?= e(old('aspirasi')) ?></textarea>
            <span class="side-note">
                Minimal 25 huruf. Tulis selengkap mungkin: aspirasi tidak menempel pada
                kantor mana pun, jadi keterangannya harus berdiri sendiri.
            </span>
            <?php if (errors('aspirasi')) : ?>
                <span class="field-error"><?= e((string) errors('aspirasi')) ?></span>
            <?php endif; ?>
        </label>

        <label class="field">
            <span class="field__label">Kontak Anda (boleh dikosongkan)</span>
            <input type="text" name="kontak" maxlength="160"
                   value="<?= e(old('kontak', kontak_saya())) ?>"
                   placeholder="Nomor HP atau email, kalau bersedia dihubungi">
            <span class="side-note">Aspirasi anonim tetap diterima. Kontak hanya dipakai bila perlu bertanya.</span>
            <?php if (errors('kontak')) : ?>
                <span class="field-error"><?= e((string) errors('kontak')) ?></span>
            <?php endif; ?>
        </label>

        <button class="btn btn--primary" type="submit">Kirim aspirasi</button>
    </form>
</section>
