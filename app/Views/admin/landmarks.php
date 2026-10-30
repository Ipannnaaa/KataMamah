<?php

/**
 * Kelola landmark + layanan + syarat berkas.
 *
 * @var array<int, array<string, mixed>> $landmarks
 * @var array<int, array<string, mixed>> $districts
 * @var array<string, mixed>|null $selected
 * @var array<int, array<string, mixed>> $services
 */

use App\Services\PhotoService;

$selected = $selected ?? [];
$foto = PhotoService::url((string) ($selected['cover_image'] ?? ''));

// Superadmin tidak terikat satu kantor, jadi daftarnya seluruh kabupaten —
// di situ nama kecamatan perlu ikut, karena banyak kantor bernama mirip.
$seKabupaten = ($location ?? null) === null;

?>
<div class="page-head">
    <div>
        <h1 class="page-title">Landmark &amp; deskripsi</h1>
        <p class="lede">Landmark yang aktif langsung muncul di peta 3D halaman utama.</p>
    </div>
</div>

<div class="split">
    <aside class="split__side split__side--first">
        <div class="card">
            <div class="card__head">
                <p class="label">Daftar landmark</p>
                <span class="card__meta"><?= e((string) count($landmarks)) ?> terdaftar</span>
            </div>
            <ul class="landmark-list">
                <?php foreach ($landmarks as $landmark) : ?>
                    <li class="landmark<?= (int) ($selected['id'] ?? 0) === (int) $landmark['id'] ? ' is-active' : '' ?>">
                        <a href="<?= e(url('/admin/landmark/' . $landmark['id'] . '/deskripsi')) ?>">
                            <strong><?= e((string) $landmark['name']) ?></strong>
                            <small>
                                <?php if ($seKabupaten) : ?>
                                    <?= e((string) $landmark['district_name']) ?> ·
                                <?php endif; ?>
                                <?= e((string) $landmark['category']) ?>
                                · kuota <?= e((string) $landmark['quota_per_day']) ?>
                                · <?= (int) $landmark['is_active'] === 1 ? 'aktif' : 'nonaktif' ?>
                                · <?= ($landmark['cover_image'] ?? '') !== '' ? 'berfoto' : 'tanpa foto' ?>
                            </small>
                        </a>
                        <form method="post" action="<?= e(url('/admin/landmark/' . $landmark['id'] . '/nonaktif')) ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn--small btn--quiet" type="submit">
                                <?= (int) $landmark['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>
                            </button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </aside>

    <div class="split__main stack">
        <form method="post" action="<?= e(url('/admin/landmark/simpan')) ?>" class="card form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e((string) ($selected['id'] ?? '')) ?>">

            <div class="card__head">
                <h2 class="card__title">
                    <?= $selected === [] ? 'Tambah landmark' : 'Ubah ' . e((string) $selected['name']) ?>
                </h2>
                <label class="check check--inline">
                    <input type="checkbox" name="is_active" value="1" <?= (int) ($selected['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <span>Aktif di peta</span>
                </label>
            </div>

            <label class="field">
                <span class="field__label">Nama landmark</span>
                <input type="text" name="name" value="<?= e(old('name', (string) ($selected['name'] ?? ''))) ?>" maxlength="150" required>
                <?php if (errors('name')) : ?><span class="field-error"><?= e((string) errors('name')) ?></span><?php endif; ?>
            </label>

            <div class="field-row">
                <label class="field">
                    <span class="field__label">Kategori</span>
                    <!--
                        Daftarnya dari config('map.category_labels'), bukan ditulis
                        ulang di sini. Versi sebelumnya hanya memuat 9 dari 24
                        kategori: untuk kantor di luar daftar itu tidak ada opsi
                        yang cocok, peramban memilih opsi pertama, dan menekan
                        "Simpan landmark" diam-diam mengubah perpustakaan, dinas,
                        kantor pos, dan 31 kantor lain menjadi kantor kecamatan.
                    -->
                    <select name="category" required>
                        <?php foreach ((array) config('map.category_labels', []) as $category => $labelKategori) : ?>
                            <option value="<?= e((string) $category) ?>" <?= (string) ($selected['category'] ?? '') === (string) $category ? 'selected' : '' ?>>
                                <?= e((string) $labelKategori) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="field">
                    <span class="field__label">Kecamatan</span>
                    <select name="district_id" required>
                        <?php foreach ($districts as $district) : ?>
                            <option value="<?= e((string) $district['id']) ?>"
                                <?= (int) ($selected['district_id'] ?? 0) === (int) $district['id'] ? 'selected' : '' ?>>
                                <?= e((string) $district['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="field-row">
                <label class="field">
                    <span class="field__label">Longitude</span>
                    <input type="text" name="lng" value="<?= e(old('lng', (string) ($selected['lng'] ?? ''))) ?>" required>
                </label>

                <label class="field">
                    <span class="field__label">Latitude</span>
                    <input type="text" name="lat" value="<?= e(old('lat', (string) ($selected['lat'] ?? ''))) ?>" required>
                </label>

                <label class="field">
                    <span class="field__label">Kuota per hari</span>
                    <input type="number" name="quota_per_day" min="1" max="1000"
                           value="<?= e(old('quota_per_day', (string) ($selected['quota_per_day'] ?? '50'))) ?>" required>
                    <?php if (errors('quota_per_day')) : ?><span class="field-error"><?= e((string) errors('quota_per_day')) ?></span><?php endif; ?>
                </label>
            </div>

            <div class="field-row">
                <label class="field">
                    <span class="field__label">Alamat</span>
                    <input type="text" name="address" value="<?= e(old('address', (string) ($selected['address'] ?? ''))) ?>" maxlength="255">
                </label>

                <label class="field">
                    <span class="field__label">Telepon</span>
                    <input type="text" name="phone" value="<?= e(old('phone', (string) ($selected['phone'] ?? ''))) ?>" maxlength="40">
                </label>

                <label class="field">
                    <span class="field__label">Email</span>
                    <input type="email" name="email" value="<?= e(old('email', (string) ($selected['email'] ?? ''))) ?>" maxlength="120">
                </label>
            </div>

            <label class="field">
                <span class="field__label">Deskripsi singkat <small>(muncul di popup peta)</small></span>
                <textarea name="short_desc" rows="2" maxlength="255"><?= e(old('short_desc', (string) ($selected['short_desc'] ?? ''))) ?></textarea>
            </label>

            <label class="field">
                <span class="field__label">Deskripsi lengkap <small>(halaman lokasi)</small></span>
                <textarea name="long_desc" rows="4"><?= e(old('long_desc', (string) ($selected['long_desc'] ?? ''))) ?></textarea>
            </label>

            <p class="actions">
                <button class="btn btn--primary" type="submit">Simpan landmark</button>
                <a class="btn btn--ghost" href="<?= e(url('/admin/landmark')) ?>">Batal</a>
            </p>
        </form>

        <?php if ($selected !== []) : ?>
            <section class="card">
                <h2 class="card__title">Foto gedung</h2>
                <p class="card__text">
                    Satu foto tampak depan gedung, supaya warga mengenali tempatnya sebelum
                    berangkat. Maksimal <?= e((string) round(((int) config('app.photo.max_bytes')) / 1048576, 1)) ?> MB,
                    lebar minimal <?= e((string) config('app.photo.min_width')) ?> px, format JPG, PNG, atau WebP.
                </p>

                <div class="photo-box">
                    <div class="photo-box__preview">
                        <?php if ($foto !== null) : ?>
                            <img src="<?= e($foto) ?>" alt="Foto <?= e((string) $selected['name']) ?>" loading="lazy">
                        <?php else : ?>
                            <p class="photo-box__empty">Belum berfoto</p>
                        <?php endif; ?>
                    </div>

                    <form method="post" enctype="multipart/form-data"
                          action="<?= e(url('/admin/landmark/' . $selected['id'] . '/foto')) ?>"
                          class="photo-box__form">
                        <?= csrf_field() ?>

                        <label class="field">
                            <span class="field__label">
                                <?= $foto === null ? 'Pilih foto' : 'Ganti foto' ?>
                                <small>(kosongkan bila hanya ingin memperbaiki sumbernya)</small>
                            </span>
                            <input type="file" name="foto" accept="image/jpeg,image/png,image/webp">
                        </label>

                        <label class="field">
                            <span class="field__label">Sumber foto</span>
                            <input type="text" name="cover_credit" maxlength="160"
                                   placeholder="mis. Dokumentasi Humas Pemkab Tuban, 2026"
                                   value="<?= e(old('cover_credit', (string) ($selected['cover_credit'] ?? ''))) ?>">
                            <span class="side-note">
                                Wajib diisi dan ikut tampil di halaman kantor. Jangan mengunggah
                                foto milik orang lain tanpa izin — sebutkan pemotretnya.
                            </span>
                            <?php if (errors('cover_credit')) : ?>
                                <span class="field-error"><?= e((string) errors('cover_credit')) ?></span>
                            <?php endif; ?>
                        </label>

                        <div class="actions">
                            <button class="btn btn--primary" type="submit">Simpan foto</button>
                        </div>
                    </form>
                </div>

                <?php if ($foto !== null) : ?>
                    <form method="post" action="<?= e(url('/admin/landmark/' . $selected['id'] . '/foto')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="hapus" value="1">
                        <button class="btn btn--small btn--quiet" type="submit">Hapus foto ini</button>
                    </form>
                <?php endif; ?>
            </section>

            <section class="card">
                <h2 class="card__title">Layanan &amp; syarat berkas</h2>
                <p class="card__text">
                    Syarat berkas disimpan ulang setiap kali layanan disimpan, sesuai urutan
                    baris di formulir.
                </p>

                <?php foreach ($services as $service) : ?>
                    <form method="post" action="<?= e(url('/admin/layanan/simpan')) ?>" class="service-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= e((string) $service['id']) ?>">
                        <input type="hidden" name="location_id" value="<?= e((string) $selected['id']) ?>">

                        <div class="field-row">
                            <label class="field">
                                <span class="field__label">Nama layanan</span>
                                <input type="text" name="name" value="<?= e((string) $service['name']) ?>" maxlength="150" required>
                            </label>
                            <label class="field">
                                <span class="field__label">Biaya (Rp) <small>(kosong = belum dikonfirmasi)</small></span>
                                <!--
                                    Kosong dan 0 berbeda artinya, jadi NULL tidak boleh
                                    dicetak sebagai "0". Sebelumnya kolom ini memakai
                                    (int) $service['fee'], sehingga tarif yang belum
                                    dikonfirmasi tampil 0 dan ikut tersimpan sebagai
                                    "Gratis" begitu layanannya disimpan ulang.
                                -->
                                <input type="number" name="fee" min="0" step="500"
                                       value="<?= $service['fee'] === null ? '' : e((string) (float) $service['fee']) ?>">
                                <span class="side-note">Isi 0 bila layanannya memang gratis menurut peraturan.</span>
                            </label>
                            <label class="field">
                                <span class="field__label">SLA (hari kerja)</span>
                                <input type="number" name="sla_days" min="0" max="90" value="<?= e((string) ($service['sla_days'] ?? '')) ?>">
                            </label>
                        </div>

                        <div class="field-row">
                            <label class="field">
                                <span class="field__label">Dasar tarif</span>
                                <input type="text" name="fee_basis" maxlength="180"
                                       value="<?= e((string) ($service['fee_basis'] ?? '')) ?>">
                            </label>
                            <label class="field">
                                <span class="field__label">Dasar daftar berkas</span>
                                <input type="text" name="docs_basis" maxlength="180"
                                       value="<?= e((string) ($service['docs_basis'] ?? '')) ?>">
                            </label>
                        </div>

                        <label class="field">
                            <span class="field__label">Deskripsi layanan</span>
                            <textarea name="description" rows="2"><?= e((string) ($service['description'] ?? '')) ?></textarea>
                        </label>

                        <p class="label">Syarat berkas</p>
                        <?php
                        // Indeks baris harus sejajar dengan urutan req_label[] agar
                        // centang "wajib"/"unggah" menempel pada baris yang benar.
                        $row = 0;
                        ?>
                        <?php foreach ($service['requirements'] as $requirement) : ?>
                            <div class="req-row">
                                <input type="text" name="req_label[]" value="<?= e((string) $requirement['label']) ?>" maxlength="180">
                                <label class="check check--inline">
                                    <input type="checkbox" name="req_wajib[<?= e((string) $row) ?>]" value="1"
                                        <?= (int) $requirement['is_mandatory'] === 1 ? 'checked' : '' ?>>
                                    <span>Wajib</span>
                                </label>
                                <label class="check check--inline">
                                    <input type="checkbox" name="req_unggah[<?= e((string) $row) ?>]" value="1"
                                        <?= (int) $requirement['needs_upload'] === 1 ? 'checked' : '' ?>>
                                    <span>Unggah</span>
                                </label>
                                <!--
                                    Catatan ikut dikirim. Tanpa kolom ini, syncRequirements
                                    menulis ulang seluruh baris tanpa note dan catatan
                                    praktis tiap berkas hilang begitu layanannya disimpan.
                                -->
                                <input class="req-row__note" type="text" name="req_note[<?= e((string) $row) ?>]"
                                       maxlength="255" placeholder="Catatan untuk warga (opsional)"
                                       value="<?= e((string) ($requirement['note'] ?? '')) ?>">
                            </div>
                            <?php $row++; ?>
                        <?php endforeach; ?>

                        <div class="req-row">
                            <input type="text" name="req_label[]" value="" maxlength="180" placeholder="Tambah syarat berkas baru…">
                            <label class="check check--inline">
                                <input type="checkbox" name="req_wajib[<?= e((string) $row) ?>]" value="1">
                                <span>Wajib</span>
                            </label>
                            <label class="check check--inline">
                                <input type="checkbox" name="req_unggah[<?= e((string) $row) ?>]" value="1">
                                <span>Unggah</span>
                            </label>
                            <input class="req-row__note" type="text" name="req_note[<?= e((string) $row) ?>]"
                                   maxlength="255" placeholder="Catatan untuk warga (opsional)">
                        </div>

                        <div class="actions">
                            <button class="btn btn--dark" type="submit">Simpan layanan</button>
                        </div>
                    </form>

                    <form method="post" action="<?= e(url('/admin/layanan/' . $service['id'] . '/hapus')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <button class="btn btn--small btn--quiet" type="submit">Nonaktifkan layanan ini</button>
                    </form>
                <?php endforeach; ?>

                <form method="post" action="<?= e(url('/admin/layanan/simpan')) ?>" class="service-form service-form--new">
                    <?= csrf_field() ?>
                    <input type="hidden" name="location_id" value="<?= e((string) $selected['id']) ?>">
                    <p class="label">Tambah layanan baru</p>
                    <div class="field-row">
                        <label class="field">
                            <span class="field__label">Nama layanan</span>
                            <input type="text" name="name" maxlength="150" required>
                        </label>
                        <label class="field">
                            <span class="field__label">Biaya (Rp)</span>
                            <input type="number" name="fee" min="0" step="500" value="0">
                        </label>
                        <label class="field">
                            <span class="field__label">SLA (hari kerja)</span>
                            <input type="number" name="sla_days" min="0" max="90">
                        </label>
                    </div>
                    <div class="req-row">
                        <input type="text" name="req_label[]" maxlength="180" placeholder="Syarat berkas pertama…">
                        <label class="check check--inline">
                            <input type="checkbox" name="req_wajib[0]" value="1" checked>
                            <span>Wajib</span>
                        </label>
                        <label class="check check--inline">
                            <input type="checkbox" name="req_unggah[0]" value="1">
                            <span>Unggah</span>
                        </label>
                    </div>
                    <button class="btn btn--primary" type="submit">Tambah layanan</button>
                </form>
            </section>
        <?php endif; ?>
    </div>
</div>
