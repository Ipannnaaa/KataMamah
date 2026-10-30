<?php

/**
 * Halaman per tempat: layanan, syarat berkas, jadwal, kontak.
 *
 * @var array<string, mixed> $location
 * @var array<int, array<string, mixed>> $services
 * @var array<int, array{date: string, label: string, left: int, quota: int, closed: bool}> $schedule
 * @var array<string, mixed> $openHours
 */

use App\Core\Auth;
use App\Core\View;
use App\Services\PhotoService;

$hariIni = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'][(int) date('N') - 1];
$jamHariIni = $openHours[$hariIni] ?? null;
$buka = is_array($jamHariIni) && count($jamHariIni) === 2;
$foto = PhotoService::url((string) ($location['cover_image'] ?? ''));

?>
<section class="container section">
    <p class="crumbs">
        <a href="<?= e(url('/peta')) ?>">Peta</a> ·
        <a href="<?= e(url('/kecamatan/' . $location['district_slug'])) ?>">Kecamatan <?= e((string) $location['district_name']) ?></a> ·
        <span><?= e((string) $location['name']) ?></span>
    </p>

    <div class="split">
        <div class="split__main">
            <?php if ($foto !== null) : ?>
                <!--
                    Sumber foto selalu ikut tampil. Gambar gedung pemerintah
                    yang tak jelas asalnya sama tidak bisa diperiksanya seperti
                    tarif tanpa dasar hukum.
                -->
                <figure class="cover">
                    <img src="<?= e($foto) ?>"
                         alt="Gedung <?= e((string) $location['name']) ?>"
                         loading="lazy" decoding="async">
                    <?php if (($location['cover_credit'] ?? '') !== '') : ?>
                        <figcaption class="cover__credit">
                            Foto: <?= e((string) $location['cover_credit']) ?>
                        </figcaption>
                    <?php endif; ?>
                </figure>
            <?php endif; ?>

            <div class="chip-row">
                <span class="chip"><?= e((string) config('map.category_labels.' . $location['category'], $location['category'])) ?></span>
                <span class="chip <?= $buka ? 'chip--ok' : 'chip--muted' ?>">
                    <?= $buka ? 'Buka hari ini · ' . e($jamHariIni[0]) . '–' . e($jamHariIni[1]) : 'Tutup hari ini' ?>
                </span>
            </div>

            <h1 class="page-title"><?= e((string) $location['name']) ?></h1>
            <p class="lede"><?= e((string) ($location['long_desc'] ?? $location['short_desc'] ?? '')) ?></p>

            <p class="actions">
                <a class="btn btn--primary" href="<?= e($mapsUrl) ?>" target="_blank" rel="noopener noreferrer">
                    Buka di Google Maps
                </a>
                <a class="btn btn--ghost" href="<?= e(url('/kecamatan/' . $location['district_slug'])) ?>">
                    Lihat di peta kecamatan
                </a>
            </p>

            <dl class="fact-grid">
                <div><dt>Alamat</dt><dd><?= e((string) ($location['address'] ?? '-')) ?></dd></div>
                <div><dt>Telepon</dt><dd><?= e((string) ($location['phone'] ?? '-')) ?></dd></div>
                <div><dt>Email</dt><dd><?= e((string) ($location['email'] ?? '-')) ?></dd></div>
                <div><dt>Kuota per hari</dt><dd><?= e((string) $location['quota_per_day']) ?> antrean</dd></div>
            </dl>

            <h2 class="section-title">Layanan &amp; berkas yang dibutuhkan</h2>

            <?php if ($services === []) : ?>
                <p class="empty">Belum ada layanan yang dipublikasikan untuk lokasi ini.</p>
            <?php else : ?>
                <div class="accordion">
                    <?php foreach ($services as $i => $service) : ?>
                        <details class="accordion__item"<?= $i === 0 ? ' open' : '' ?>>
                            <summary>
                                <span>
                                    <strong><?= e((string) $service['name']) ?></strong>
                                    <small><?= e((string) ($service['description'] ?? '')) ?></small>
                                </span>
                                <span class="accordion__meta">
                                    <strong><?= e(tarif($service['fee'])) ?></strong>
                                    <small>
                                        <?= $service['sla_days'] !== null
                                            ? 'Selesai ' . e((string) $service['sla_days']) . ' hari kerja'
                                            : 'Selesai hari yang sama' ?>
                                    </small>
                                </span>
                            </summary>

                            <div class="accordion__body">
                                <?php if (!empty($service['fee_basis'])) : ?>
                                    <!-- Dasar tarif selalu ikut: angka tanpa sumber tidak bisa
                                         diperiksa warga, dan yang bertanda "perlu dikonfirmasi"
                                         harus jelas kenapa. -->
                                    <p class="tarif-dasar">
                                        <strong>Dasar tarif:</strong> <?= e((string) $service['fee_basis']) ?>
                                    </p>
                                <?php endif; ?>

                                <p class="label">Berkas yang dibutuhkan</p>
                                <?php if (!empty($service['docs_basis'])) : ?>
                                    <p class="tarif-dasar tarif-dasar--berkas">
                                        <strong>Dasar daftar berkas:</strong> <?= e((string) $service['docs_basis']) ?>
                                    </p>
                                <?php endif; ?>
                                <ul class="req-list">
                                    <?php foreach ($service['requirements'] as $requirement) : ?>
                                        <li class="req">
                                            <span class="req__check" aria-hidden="true"></span>
                                            <span class="req__text">
                                                <strong><?= e((string) $requirement['label']) ?></strong>
                                                <?php if (!empty($requirement['note'])) : ?>
                                                    <small><?= e((string) $requirement['note']) ?></small>
                                                <?php endif; ?>
                                            </span>
                                            <span class="badge <?= (int) $requirement['is_mandatory'] === 1 ? 'badge--wajib' : 'badge--opsional' ?>">
                                                <?php if ((int) $requirement['needs_upload'] === 1) : ?>
                                                    Wajib · unggah
                                                <?php else : ?>
                                                    <?= (int) $requirement['is_mandatory'] === 1 ? 'Wajib' : 'Opsional' ?>
                                                <?php endif; ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                    <?php if ($service['requirements'] === []) : ?>
                                        <li class="req"><span class="req__text">Tidak ada berkas khusus.</span></li>
                                    <?php endif; ?>
                                </ul>

                                <p class="actions">
                                    <a class="btn btn--primary" href="<?= e(url('/lokasi/' . $location['slug'] . '/daftar')) ?>">
                                        Ambil antrean layanan ini
                                    </a>
                                    <?php if (!Auth::check()) : ?>
                                        <small class="hint">Perlu masuk sebagai warga terlebih dahulu.</small>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h2 class="section-title">Jadwal &amp; kuota 7 hari ke depan</h2>
            <table class="table">
                <caption class="sr-only">Sisa kuota antrean per tanggal</caption>
                <thead>
                    <tr><th scope="col">Tanggal</th><th scope="col">Status</th><th scope="col">Sisa kuota</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($schedule as $day) : ?>
                        <tr>
                            <th scope="row"><?= e($day['label']) ?></th>
                            <td><?= $day['closed'] ? 'Tutup' : 'Buka' ?></td>
                            <td><?= $day['closed'] ? '-' : e((string) $day['left']) . ' / ' . e((string) $day['quota']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?= View::partial('aduan-form', ['location' => $location]) ?>
        </div>

        <aside class="split__side">
            <div class="panel-dark panel-dark--compact">
                <p class="eyebrow eyebrow--light">Registrasi online</p>
                <h2 class="side-title">Ambil antrean di lokasi ini</h2>
                <p class="lede lede--light">Pilih layanan, tentukan tanggal, unggah berkas, dan dapatkan kode pemesanan.</p>
                <a class="btn btn--primary btn--block" href="<?= e(url('/lokasi/' . $location['slug'] . '/daftar')) ?>">
                    Daftar antrean
                </a>
                <p class="side-note">
                    Sudah punya kode? <a href="<?= e(url('/cek-antrean')) ?>">Cek status</a>
                </p>
            </div>

            <?php if (!Auth::check()) : ?>
                <div class="card">
                    <strong class="card__title">Tidak perlu akun</strong>
                    <p class="card__text">
                        Antrean bisa diambil langsung: cukup isi nama dan kontak pada formulir,
                        lalu simpan kode pemesanan untuk melacak statusnya.
                    </p>
                    <p class="card__text">
                        Punya akun membuat riwayat antrean, chat dengan petugas, dan berkas
                        tersimpan rapi — tapi itu pilihan, bukan syarat.
                    </p>
                    <p class="actions actions--stack">
                        <a class="btn btn--dark btn--block" href="<?= e(url('/lokasi/' . $location['slug'] . '/daftar')) ?>">Ambil antrean tanpa akun</a>
                        <a class="btn btn--ghost btn--block" href="<?= e(url('/masuk?next=/lokasi/' . $location['slug'] . '/daftar')) ?>">Masuk ke akun</a>
                    </p>
                </div>
            <?php endif; ?>

            <div class="note note--warn">
                <strong>Perhatian</strong>
                <p>
                    Berkas yang tidak lengkap membuat antrean tetap berstatus
                    <em>ditinjau</em>. Petugas akan menghubungi Anda melalui chat di halaman antrean.
                </p>
            </div>
        </aside>
    </div>
</section>
