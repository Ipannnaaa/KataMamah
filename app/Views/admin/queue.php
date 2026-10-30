<?php

/**
 * Detail satu antrean di panel admin: data warga, berkas, assign petugas.
 *
 * @var array<string, mixed> $queue
 * @var array<int, array<string, mixed>> $logs
 * @var array<int, array<string, mixed>> $documents
 * @var array<int, array<string, mixed>> $officers
 */

use App\Core\View;

?>
<p class="crumbs">
    <a href="<?= e(url('/admin/antrean')) ?>">Informasi antrean</a> · <span><?= e((string) $queue['code']) ?></span>
</p>

<div class="page-head">
    <div>
        <span class="<?= e(status_class((string) $queue['status'])) ?>"><?= e(status_label((string) $queue['status'])) ?></span>
        <h1 class="page-title"><?= e((string) $queue['service_name']) ?></h1>
        <p class="lede">
            Kode <strong><?= e((string) $queue['code']) ?></strong>
            · nomor <?= e(str_pad((string) $queue['queue_number'], 3, '0', STR_PAD_LEFT)) ?>
            · <?= e(tanggal((string) $queue['queue_date'])) ?>
        </p>
    </div>
</div>

<div class="split">
    <div class="split__main stack">
        <section class="card">
            <h2 class="card__title">Data pemohon</h2>
            <dl class="fact-grid">
                <div><dt>Nama</dt><dd><?= e((string) $queue['user_name']) ?></dd></div>
                <div><dt>NIK</dt><dd><?= e((string) ($queue['user_nik'] ?? '-')) ?></dd></div>
                <div><dt>Telepon</dt><dd><?= e((string) ($queue['user_phone'] ?? '-')) ?></dd></div>
                <div><dt>Pembayaran</dt><dd><?= e(pay_label((string) $queue['payment_status'])) ?></dd></div>
            </dl>

            <?php if (!empty($queue['note_user'])) : ?>
                <p class="label">Catatan warga</p>
                <p class="card__quote"><?= e((string) $queue['note_user']) ?></p>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2 class="card__title">Berkas unggahan</h2>
            <?php if ($documents === []) : ?>
                <p class="empty">Tidak ada berkas pada antrean ini.</p>
            <?php else : ?>
                <ul class="file-list">
                    <?php foreach ($documents as $document) : ?>
                        <li>
                            <a href="<?= e(url('/berkas/' . $document['id'])) ?>" target="_blank" rel="noopener">
                                <?= e((string) $document['original_name']) ?>
                            </a>
                            <small>
                                <?= e((string) ($document['requirement_label'] ?? 'Berkas tambahan')) ?>
                                · <?= e((string) round(((int) $document['size_bytes']) / 1024)) ?> KB
                                · <?= (int) $document['verified'] === 1 ? 'terverifikasi' : 'menunggu verifikasi' ?>
                            </small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2 class="card__title">Deskripsi penanganan</h2>
            <p class="card__text">
                <?= !empty($queue['note_officer'])
                    ? e((string) $queue['note_officer'])
                    : 'Petugas belum menulis catatan penanganan.' ?>
            </p>
        </section>

        <form method="post" action="<?= e(url('/admin/antrean/' . $queue['code'] . '/assign')) ?>" class="card form">
            <?= csrf_field() ?>
            <h2 class="card__title">Assign petugas</h2>
            <p class="card__text">
                Menugaskan petugas pada antrean berstatus <em>baru</em> sekaligus mengubah
                statusnya menjadi <em>ditinjau</em>.
            </p>
            <label class="field">
                <span class="field__label">Petugas</span>
                <select name="officer_id" required>
                    <option value="">— pilih petugas —</option>
                    <?php foreach ($officers as $officer) : ?>
                        <option value="<?= e((string) $officer['id']) ?>"
                            <?= (int) ($queue['officer_id'] ?? 0) === (int) $officer['id'] ? 'selected' : '' ?>>
                            <?= e((string) $officer['name']) ?> — <?= e((string) $officer['active_queues']) ?> aktif
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (errors('officer_id')) : ?>
                    <span class="field-error"><?= e((string) errors('officer_id')) ?></span>
                <?php endif; ?>
            </label>
            <button class="btn btn--primary" type="submit">Tugaskan</button>
        </form>
    </div>

    <aside class="split__side">
        <div class="card">
            <p class="label">Riwayat status</p>
            <?= View::partial('timeline', ['logs' => $logs]) ?>
        </div>
    </aside>
</div>
