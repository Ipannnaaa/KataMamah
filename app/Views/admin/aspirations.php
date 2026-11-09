<?php

/**
 * Kotak aspirasi warga, dibaca super admin.
 *
 * Tidak ada penyaring lokasi di sini, dan itu disengaja: aspirasi memang
 * tidak menempel pada kantor mana pun.
 *
 * @var array<int, array<string, mixed>> $aspirations
 * @var array<string, int> $counts
 * @var string $filter
 */

use App\Models\Aspiration;

$belumSelesai = ($counts['baru'] ?? 0) + ($counts['ditinjau'] ?? 0) + ($counts['ditindaklanjuti'] ?? 0);

?>
<header class="panel-head">
    <h1 class="panel-title">Aspirasi warga</h1>
    <p class="panel-sub">
        Masukan warga tentang layanan publik se-Kabupaten Tuban yang tidak
        menyangkut satu kantor tertentu.
        <?= e((string) $belumSelesai) ?> belum selesai.
    </p>
</header>

<nav class="tabs" aria-label="Saring menurut status">
    <a class="tab<?= $filter === '' ? ' is-active' : '' ?>" href="<?= e(url('/admin/aspirasi')) ?>">
        Semua
    </a>
    <?php foreach (Aspiration::STATUS as $nilai => $label) : ?>
        <a class="tab<?= $filter === $nilai ? ' is-active' : '' ?>"
           href="<?= e(url('/admin/aspirasi?status=' . $nilai)) ?>">
            <?= e($label) ?> (<?= e((string) ($counts[$nilai] ?? 0)) ?>)
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($aspirations === []) : ?>
    <p class="empty">Belum ada aspirasi<?= $filter !== '' ? ' dengan status ini' : '' ?>.</p>
<?php else : ?>
    <div class="stack">
        <?php foreach ($aspirations as $a) : ?>
            <article class="card report">
                <div class="report__head">
                    <span class="badge badge--<?= e(preg_replace('/[^a-z]/', '', (string) $a['status']) ?? '') ?>">
                        <?= e(Aspiration::labelStatus((string) $a['status'])) ?>
                    </span>
                    <strong class="report__kind"><?= e(Aspiration::labelTopik((string) $a['topic'])) ?></strong>
                    <span class="report__meta">
                        #<?= e((string) $a['id']) ?> ·
                        <?= e(date('d M Y, H:i', strtotime((string) $a['created_at']))) ?>
                    </span>
                </div>

                <p class="report__body"><?= nl2br(e((string) $a['message'])) ?></p>

                <p class="report__who">
                    Pengirim:
                    <?php if (!empty($a['pengirim'])) : ?>
                        <?= e((string) $a['pengirim']) ?> (punya akun)
                    <?php else : ?>
                        tanpa akun
                    <?php endif; ?>
                    <?php if (!empty($a['contact'])) : ?>
                        · kontak: <?= e((string) $a['contact']) ?>
                    <?php else : ?>
                        · tidak meninggalkan kontak
                    <?php endif; ?>
                </p>

                <?php if (!empty($a['admin_note'])) : ?>
                    <p class="report__note">
                        <strong>Catatan<?= !empty($a['penangan']) ? ' (' . e((string) $a['penangan']) . ')' : '' ?>:</strong>
                        <?= nl2br(e((string) $a['admin_note'])) ?>
                    </p>
                <?php endif; ?>

                <form class="report__form" method="post"
                      action="<?= e(url('/admin/aspirasi/' . $a['id'] . '/status')) ?>">
                    <?= csrf_field() ?>

                    <label class="field field--inline">
                        <span class="field__label">Status</span>
                        <select name="status">
                            <?php foreach (Aspiration::STATUS as $nilai => $label) : ?>
                                <option value="<?= e($nilai) ?>"<?= $a['status'] === $nilai ? ' selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="field field--inline">
                        <span class="field__label">Catatan (opsional)</span>
                        <input type="text" name="catatan" maxlength="2000"
                               value="<?= e((string) ($a['admin_note'] ?? '')) ?>"
                               placeholder="Mis. diteruskan ke Dishub pada 9 November">
                    </label>

                    <button class="btn btn--primary" type="submit">Simpan</button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
