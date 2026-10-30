<?php

/**
 * Riwayat status satu antrean.
 *
 * @var array<int, array<string, mixed>> $logs
 * @var bool $showActor  Halaman publik mengirim false: nama pelaku tidak boleh
 *                       tampil di sana, sama seperti nama pemohon yang dimasking.
 */

$logs = $logs ?? [];
$showActor = $showActor ?? true;

?>
<ol class="timeline">
    <?php foreach ($logs as $log) : ?>
        <li class="timeline__item">
            <span class="timeline__dot" aria-hidden="true"></span>
            <div>
                <p class="timeline__title"><?= e(status_label((string) $log['to_status'])) ?></p>
                <p class="timeline__meta">
                    <?= e(tanggal((string) $log['created_at'])) ?> · <?= e(jam((string) $log['created_at'])) ?>
                    <?php if ($showActor && !empty($log['actor_name'])) : ?>
                        · <?= e((string) $log['actor_name']) ?>
                    <?php endif; ?>
                </p>
                <?php if (!empty($log['note'])) : ?>
                    <p class="timeline__note"><?= e((string) $log['note']) ?></p>
                <?php endif; ?>
            </div>
        </li>
    <?php endforeach; ?>
    <?php if ($logs === []) : ?>
        <li class="timeline__item"><p class="timeline__meta">Belum ada riwayat status.</p></li>
    <?php endif; ?>
</ol>
