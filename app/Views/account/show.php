<?php

/**
 * Detail satu antrean milik warga.
 *
 * @var array<string, mixed> $queue
 * @var array<int, array<string, mixed>> $logs
 * @var array<int, array<string, mixed>> $documents
 * @var bool $chatOpen
 */

use App\Core\View;

$bisaBatal = in_array((string) $queue['status'], ['baru', 'ditinjau'], true);

?>
<section class="container section">
    <p class="crumbs">
        <a href="<?= e(url('/akun')) ?>">Antrean saya</a> · <span><?= e((string) $queue['code']) ?></span>
    </p>

    <div class="page-head">
        <div>
            <span class="<?= e(status_class((string) $queue['status'])) ?>"><?= e(status_label((string) $queue['status'])) ?></span>
            <h1 class="page-title"><?= e((string) $queue['service_name']) ?></h1>
            <p class="lede">
                <?= e((string) $queue['location_name']) ?> · Kode <strong><?= e((string) $queue['code']) ?></strong>
                · Nomor <?= e(str_pad((string) $queue['queue_number'], 3, '0', STR_PAD_LEFT)) ?>
            </p>
        </div>
        <p class="actions">
            <?php if ($chatOpen) : ?>
                <a class="btn btn--dark" href="<?= e(url('/akun/antrean/' . $queue['code'] . '/chat')) ?>">Chat petugas</a>
            <?php endif; ?>
        </p>
    </div>

    <div class="split">
        <div class="split__main stack">
            <dl class="fact-grid">
                <div><dt>Tanggal layanan</dt><dd><?= e(tanggal((string) $queue['queue_date'])) ?></dd></div>
                <div>
                    <dt>Sesi</dt>
                    <dd><?= $queue['slot_time'] !== null ? e(substr((string) $queue['slot_time'], 0, 5)) : '-' ?></dd>
                </div>
                <div><dt>Petugas</dt><dd><?= e((string) ($queue['officer_name'] ?? 'Belum ditugaskan')) ?></dd></div>
                <div><dt>Pembayaran</dt><dd><?= e(pay_label((string) $queue['payment_status'])) ?></dd></div>
            </dl>

            <?php if (!empty($queue['note_officer'])) : ?>
                <div class="note">
                    <strong>Catatan petugas</strong>
                    <p><?= e((string) $queue['note_officer']) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($queue['note_user'])) : ?>
                <div class="card">
                    <p class="label">Catatan Anda saat mendaftar</p>
                    <p class="card__text"><?= e((string) $queue['note_user']) ?></p>
                </div>
            <?php endif; ?>

            <div class="card">
                <p class="label">Berkas terlampir</p>
                <?php if ($documents === []) : ?>
                    <p class="card__text">Tidak ada berkas yang diunggah untuk antrean ini.</p>
                <?php else : ?>
                    <ul class="file-list">
                        <?php foreach ($documents as $document) : ?>
                            <li>
                                <a href="<?= e(url('/berkas/' . $document['id'])) ?>"><?= e((string) $document['original_name']) ?></a>
                                <small>
                                    <?= e((string) ($document['requirement_label'] ?? 'Berkas tambahan')) ?>
                                    · <?= (int) $document['verified'] === 1 ? 'terverifikasi' : 'menunggu verifikasi' ?>
                                </small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <?php if ($bisaBatal) : ?>
                <form method="post" action="<?= e(url('/akun/antrean/' . $queue['code'] . '/batal')) ?>" class="card form">
                    <?= csrf_field() ?>
                    <p class="label">Batalkan antrean</p>
                    <p class="card__text">
                        Pembatalan hanya bisa dilakukan sebelum antrean ditangani. Nomor urut
                        Anda akan dilepas dan kuota dikembalikan.
                    </p>
                    <label class="field">
                        <span class="field__label">Alasan (opsional)</span>
                        <input type="text" name="alasan" maxlength="200">
                    </label>
                    <button class="btn btn--danger" type="submit">Batalkan antrean ini</button>
                </form>
            <?php endif; ?>
        </div>

        <aside class="split__side">
            <div class="card">
                <p class="label">Riwayat status</p>
                <?= View::partial('timeline', ['logs' => $logs]) ?>
            </div>
        </aside>
    </div>
</section>
