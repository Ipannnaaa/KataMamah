<?php

/**
 * Formulir registrasi antrean online.
 *
 * @var array<string, mixed> $location
 * @var array<int, array<string, mixed>> $services
 * @var array<int, array{date: string, label: string, left: int, quota: int, closed: bool}> $dates
 * @var array<string, string> $slots
 * @var array<string, mixed>|null $user   null = pemohon belum masuk (tamu)
 * @var array<int, array<string, mixed>> $districts
 */

$tamu = $user === null;

?>
<section class="container section">
    <p class="crumbs">
        <a href="<?= e(url('/lokasi/' . $location['slug'])) ?>"><?= e((string) $location['name']) ?></a> ·
        <span>Daftar antrean</span>
    </p>

    <h1 class="page-title">Daftar antrean online</h1>
    <p class="lede">
        <?php if ($tamu) : ?>
            Tidak perlu akun. Isi identitas dan pilih jadwalnya, lalu simpan kode pemesanan
            yang muncul. Nomor urut ditentukan saat permohonan tersimpan.
        <?php else : ?>
            Nomor urut ditentukan saat permohonan tersimpan, bukan saat halaman ini dibuka.
        <?php endif; ?>
    </p>

    <form method="post" action="<?= e(url('/lokasi/' . $location['slug'] . '/daftar')) ?>" enctype="multipart/form-data" class="split" id="form-antrean">
        <?= csrf_field() ?>

        <div class="split__main stack">
            <?php if ($tamu) : ?>
                <fieldset class="card">
                    <legend class="card__title">Data pemohon</legend>
                    <p class="card__text">
                        Dipakai petugas untuk menghubungi Anda bila ada berkas yang kurang.
                        Jika nanti Anda membuat akun dengan email yang sama, antrean ini
                        otomatis tergabung ke akun tersebut.
                    </p>

                    <label class="field">
                        <span class="field__label">Nama lengkap</span>
                        <input type="text" name="nama" value="<?= e(old('nama')) ?>" maxlength="120" autocomplete="name" required>
                        <?php if (errors('nama')) : ?><span class="field-error"><?= e((string) errors('nama')) ?></span><?php endif; ?>
                    </label>

                    <div class="field-row">
                        <label class="field">
                            <span class="field__label">Email</span>
                            <input type="email" name="email" value="<?= e(old('email')) ?>" maxlength="160" autocomplete="email" required>
                            <?php if (errors('email')) : ?><span class="field-error"><?= e((string) errors('email')) ?></span><?php endif; ?>
                        </label>

                        <label class="field">
                            <span class="field__label">Nomor ponsel</span>
                            <input type="tel" name="telepon" value="<?= e(old('telepon')) ?>" autocomplete="tel" required>
                            <?php if (errors('telepon')) : ?><span class="field-error"><?= e((string) errors('telepon')) ?></span><?php endif; ?>
                        </label>
                    </div>

                    <div class="field-row">
                        <label class="field">
                            <span class="field__label">NIK <small>(opsional, 16 digit)</small></span>
                            <input type="text" name="nik" value="<?= e(old('nik')) ?>" inputmode="numeric" maxlength="16">
                            <?php if (errors('nik')) : ?><span class="field-error"><?= e((string) errors('nik')) ?></span><?php endif; ?>
                        </label>

                        <label class="field">
                            <span class="field__label">Kecamatan domisili <small>(opsional)</small></span>
                            <select name="district_id">
                                <option value="">— pilih —</option>
                                <?php foreach ($districts as $district) : ?>
                                    <option value="<?= e((string) $district['id']) ?>" <?= old('district_id') === (string) $district['id'] ? 'selected' : '' ?>>
                                        <?= e((string) $district['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </fieldset>
            <?php endif; ?>

            <fieldset class="card">
                <legend class="card__title">1 · Pilih layanan</legend>
                <?php if (errors('service_id')) : ?>
                    <p class="field-error"><?= e((string) errors('service_id')) ?></p>
                <?php endif; ?>

                <?php foreach ($services as $i => $service) : ?>
                    <label class="choice">
                        <input
                            type="radio"
                            name="service_id"
                            value="<?= e((string) $service['id']) ?>"
                            data-requirements="<?= e((string) count($service['requirements'])) ?>"
                            <?= old('service_id', (string) ($i === 0 ? $service['id'] : '')) === (string) $service['id'] ? 'checked' : '' ?>
                            required
                        >
                        <span class="choice__text">
                            <strong><?= e((string) $service['name']) ?></strong>
                            <small>
                                <?= $service['sla_days'] !== null ? 'Selesai ' . e((string) $service['sla_days']) . ' hari kerja' : 'Selesai hari yang sama' ?>
                                · <?= e((string) count($service['requirements'])) ?> berkas
                            </small>
                        </span>
                        <span class="choice__meta">
                            <?= e(tarif($service['fee'])) ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <fieldset class="card">
                <legend class="card__title">2 · Tanggal &amp; sesi</legend>
                <?php if (errors('queue_date')) : ?>
                    <p class="field-error"><?= e((string) errors('queue_date')) ?></p>
                <?php endif; ?>

                <div class="date-grid">
                    <?php foreach ($dates as $i => $day) : ?>
                        <label class="date<?= $day['closed'] || $day['left'] < 1 ? ' is-off' : '' ?>">
                            <input
                                type="radio"
                                name="queue_date"
                                value="<?= e($day['date']) ?>"
                                <?= $day['closed'] || $day['left'] < 1 ? 'disabled' : '' ?>
                                <?= old('queue_date') === $day['date'] ? 'checked' : '' ?>
                                required
                            >
                            <span class="date__dow"><?= e(hari($day['date'])) ?></span>
                            <span class="date__num"><?= e(date('j', (int) strtotime($day['date']))) ?></span>
                            <span class="date__left">
                                <?= $day['closed'] ? 'Tutup' : e((string) $day['left']) . ' sisa' ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <p class="label">Sesi waktu</p>
                <?php if (errors('slot_time')) : ?>
                    <p class="field-error"><?= e((string) errors('slot_time')) ?></p>
                <?php endif; ?>
                <div class="pill-row">
                    <?php foreach ($slots as $value => $label) : ?>
                        <label class="pill">
                            <input type="radio" name="slot_time" value="<?= e((string) $value) ?>"
                                <?= old('slot_time') === (string) $value ? 'checked' : '' ?> required>
                            <span><?= e((string) $label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset class="card">
                <legend class="card__title">3 · Berkas persyaratan</legend>
                <p class="card__text">
                    Centang berkas yang sudah disiapkan. Berkas bertanda <strong>unggah</strong>
                    wajib dilampirkan sekarang (PDF, JPG, atau PNG, maksimal 3 MB).
                </p>
                <?php if (errors('berkas')) : ?>
                    <p class="field-error"><?= e((string) errors('berkas')) ?></p>
                <?php endif; ?>

                <?php foreach ($services as $service) : ?>
                    <div class="req-group" data-for-service="<?= e((string) $service['id']) ?>">
                        <p class="label"><?= e((string) $service['name']) ?></p>
                        <ul class="req-list">
                            <?php foreach ($service['requirements'] as $requirement) : ?>
                                <li class="req">
                                    <?php if ((int) $requirement['needs_upload'] === 1) : ?>
                                        <label class="req__text" for="berkas-<?= e((string) $requirement['id']) ?>">
                                            <strong><?= e((string) $requirement['label']) ?></strong>
                                            <?php if (!empty($requirement['note'])) : ?>
                                                <small><?= e((string) $requirement['note']) ?></small>
                                            <?php endif; ?>
                                        </label>
                                        <!--
                                            Lencana ditaruh sebelum input, bukan sesudahnya: baris
                                            syarat adalah kisi dua kolom — label dan lencana berbagi
                                            baris pertama, lalu input berkas melebar penuh di
                                            bawahnya. Urutan DOM-lah yang menentukan penempatan itu.
                                        -->
                                        <span class="badge badge--unggah">Unggah</span>
                                        <input
                                            class="req__file"
                                            type="file"
                                            id="berkas-<?= e((string) $requirement['id']) ?>"
                                            name="berkas[<?= e((string) $requirement['id']) ?>]"
                                            accept=".pdf,.jpg,.jpeg,.png"
                                        >
                                    <?php else : ?>
                                        <label class="req__text req__text--check">
                                            <input class="req__check" type="checkbox" name="siap[<?= e((string) $requirement['id']) ?>]" value="1">
                                            <span class="req__label">
                                                <strong><?= e((string) $requirement['label']) ?></strong>
                                                <?php if (!empty($requirement['note'])) : ?>
                                                    <small><?= e((string) $requirement['note']) ?></small>
                                                <?php endif; ?>
                                            </span>
                                        </label>
                                        <span class="badge <?= (int) $requirement['is_mandatory'] === 1 ? 'badge--wajib' : 'badge--opsional' ?>">
                                            <?= (int) $requirement['is_mandatory'] === 1 ? 'Wajib' : 'Opsional' ?>
                                        </span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </fieldset>

            <fieldset class="card">
                <legend class="card__title">4 · Catatan untuk petugas</legend>
                <label class="field">
                    <span class="field__label">Opsional, tetapi membantu petugas menyiapkan dokumen lebih cepat</span>
                    <textarea name="note_user" rows="4" maxlength="2000"><?= e(old('note_user')) ?></textarea>
                </label>
            </fieldset>
        </div>

        <aside class="split__side">
            <div class="card">
                <p class="label">Ringkasan permohonan</p>
                <dl class="summary">
                    <div><dt>Lokasi</dt><dd><?= e((string) $location['name']) ?></dd></div>
                    <div><dt>Kecamatan</dt><dd><?= e((string) $location['district_name']) ?></dd></div>
                    <div>
                        <dt>Pemohon</dt>
                        <dd><?= $tamu ? 'Diisi pada formulir' : e((string) $user['name']) ?></dd>
                    </div>
                    <div><dt>Kuota hari ini</dt><dd><?= e((string) ($dates[0]['left'] ?? 0)) ?> tersisa</dd></div>
                </dl>
                <button class="btn btn--primary btn--block" type="submit">Kirim permohonan</button>
                <p class="side-note">
                    Dengan mengirim, Anda menyatakan data yang diisi benar dan dapat
                    dipertanggungjawabkan.
                </p>
            </div>

            <div class="note">
                <strong>Kuota berjalan</strong>
                <p>
                    Bila kuota tanggal pilihan habis sebelum permohonan terkirim, sistem
                    menolak dan meminta Anda memilih tanggal lain.
                </p>
            </div>
        </aside>
    </form>
</section>
