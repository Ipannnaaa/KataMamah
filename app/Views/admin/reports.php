<?php

/**
 * Daftar aduan warga tentang kantor layanan.
 *
 * @var array<int, array<string, mixed>> $reports
 * @var array<string, int> $counts
 * @var string $filter
 * @var array<string, mixed>|null $location
 */

use App\Models\FacilityReport;

$belumSelesai = ($counts['baru'] ?? 0) + ($counts['ditinjau'] ?? 0) + ($counts['ditindaklanjuti'] ?? 0);

?>
<header class="panel-head">
    <h1 class="panel-title">Aduan fasilitas</h1>
    <p class="panel-sub">
        <?php if ($location !== null) : ?>
            Aduan warga tentang <?= e((string) $location['name']) ?>.
        <?php else : ?>
            Aduan warga dari seluruh kantor layanan di Kabupaten Tuban.
        <?php endif; ?>
        <?= e((string) $belumSelesai) ?> belum selesai.
    </p>
</header>

<nav class="tabs" aria-label="Saring menurut status">
    <a class="tab<?= $filter === '' ? ' is-active' : '' ?>" href="<?= e(url('/admin/aduan')) ?>">
        Semua
    </a>
    <?php foreach (FacilityReport::STATUS as $nilai => $label) : ?>
        <a class="tab<?= $filter === $nilai ? ' is-active' : '' ?>"
           href="<?= e(url('/admin/aduan?status=' . $nilai)) ?>">
            <?= e($label) ?> (<?= e((string) ($counts[$nilai] ?? 0)) ?>)
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($reports === []) : ?>
    <p class="empty">Belum ada aduan<?= $filter !== '' ? ' dengan status ini' : '' ?>.</p>
<?php else : ?>
    <div class="stack">
        <?php foreach ($reports as $r) : ?>
            <article class="card report">
                <div class="report__head">
                    <span class="badge badge--<?= e(preg_replace('/[^a-z]/', '', (string) $r['status']) ?? '') ?>">
                        <?= e(FacilityReport::labelStatus((string) $r['status'])) ?>
                    </span>
                    <strong class="report__kind"><?= e(FacilityReport::labelKategori((string) $r['category'])) ?></strong>
                    <span class="report__meta">
                        #<?= e((string) $r['id']) ?> ·
                        <?= e(date('d M Y, H:i', strtotime((string) $r['created_at']))) ?>
                    </span>
                </div>

                <?php if (!empty($r['lokasi'])) : ?>
                    <p class="report__where">
                        <a href="<?= e(url('/lokasi/' . $r['lokasi_slug'])) ?>"><?= e((string) $r['lokasi']) ?></a>
                    </p>
                <?php endif; ?>

                <p class="report__body"><?= nl2br(e((string) $r['message'])) ?></p>

                <p class="report__who">
                    Pelapor:
                    <?php if (!empty($r['pelapor'])) : ?>
                        <?= e((string) $r['pelapor']) ?> (punya akun)
                    <?php else : ?>
                        tanpa akun
                    <?php endif; ?>
                    <?php if (!empty($r['contact'])) : ?>
                        · kontak: <?= e((string) $r['contact']) ?>
                    <?php else : ?>
                        · tidak meninggalkan kontak
                    <?php endif; ?>
                </p>

                <?php if (!empty($r['admin_note'])) : ?>
                    <p class="report__note">
                        <strong>Catatan admin<?= !empty($r['penangan']) ? ' (' . e((string) $r['penangan']) . ')' : '' ?>:</strong>
                        <?= nl2br(e((string) $r['admin_note'])) ?>
                    </p>
                <?php endif; ?>

                <form class="report__form" method="post"
                      action="<?= e(url('/admin/aduan/' . $r['id'] . '/status')) ?>">
                    <?= csrf_field() ?>

                    <label class="field field--inline">
                        <span class="field__label">Status</span>
                        <select name="status">
                            <?php foreach (FacilityReport::STATUS as $nilai => $label) : ?>
                                <option value="<?= e($nilai) ?>"<?= $r['status'] === $nilai ? ' selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="field field--inline">
                        <span class="field__label">Catatan (opsional)</span>
                        <input type="text" name="catatan" maxlength="2000"
                               value="<?= e((string) ($r['admin_note'] ?? '')) ?>"
                               placeholder="Mis. alamat sudah diperbaiki pada 2 November">
                    </label>

                    <button class="btn btn--primary" type="submit">Simpan</button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
