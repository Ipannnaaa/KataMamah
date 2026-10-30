<?php

/**
 * Percakapan satu antrean. Dipakai warga maupun petugas.
 *
 * @var array<string, mixed> $queue
 * @var array<int, array<string, mixed>> $messages
 * @var array<int, array<string, mixed>> $threads
 * @var bool $open
 * @var array<string, mixed> $me
 * @var string $layoutRole
 */

$isUser = ($layoutRole ?? 'user') === 'user';
$backUrl = $isUser ? '/akun/antrean/' . $queue['code'] : '/petugas?tab=saya';

// Dipakai chat.js sebagai titik awal polling (?after=lastId).
$lastId = 0;

foreach ($messages as $message) {
    $lastId = max($lastId, (int) $message['id']);
}

?>
<section class="<?= $isUser ? 'container section' : 'section' ?>">
    <div class="split">
        <?php if ($threads !== []) : ?>
            <aside class="split__side split__side--first">
                <div class="card">
                    <p class="label">Percakapan antrean</p>
                    <ul class="thread-list">
                        <?php foreach ($threads as $thread) : ?>
                            <li>
                                <a
                                    class="thread<?= (int) $thread['id'] === (int) $queue['id'] ? ' is-active' : '' ?>"
                                    href="<?= e(url('/akun/antrean/' . $thread['code'] . '/chat')) ?>"
                                >
                                    <span class="thread__head">
                                        <strong><?= e((string) $thread['code']) ?></strong>
                                        <span class="<?= e(status_class((string) $thread['status'])) ?>">
                                            <?= e(status_label((string) $thread['status'])) ?>
                                        </span>
                                        <?php if ((int) $thread['unread'] > 0) : ?>
                                            <span class="dot-count"><?= e((string) $thread['unread']) ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="thread__service"><?= e((string) $thread['service_name']) ?></span>
                                    <span class="thread__last"><?= e((string) ($thread['last_body'] ?? 'Belum ada pesan')) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </aside>
        <?php endif; ?>

        <div class="split__main">
            <div class="chat" data-queue="<?= e((string) $queue['code']) ?>"
                 data-endpoint="<?= e(url('/api/chat/' . $queue['code'])) ?>"
                 data-token="<?= e(App\Core\Csrf::token()) ?>">
                <header class="chat__head">
                    <div>
                        <strong>
                            <?= $isUser
                                ? e((string) ($queue['officer_name'] ?? 'Menunggu petugas'))
                                : e((string) $queue['user_name']) ?>
                        </strong>
                        <p class="chat__meta">
                            Antrean <strong><?= e((string) $queue['code']) ?></strong>
                            · <?= e((string) $queue['service_name']) ?>
                            · nomor <?= e(str_pad((string) $queue['queue_number'], 3, '0', STR_PAD_LEFT)) ?>
                        </p>
                    </div>
                    <span class="<?= e(status_class((string) $queue['status'])) ?>"><?= e(status_label((string) $queue['status'])) ?></span>
                    <a class="btn btn--ghost" href="<?= e(url($backUrl)) ?>">Detail antrean</a>
                </header>

                <ol class="chat__list" id="chat-list" aria-live="polite" data-last-id="<?= e((string) $lastId) ?>">
                    <?php foreach ($messages as $message) : ?>
                        <?php $mine = (int) $message['sender_id'] === (int) $me['id']; ?>
                        <li class="bubble<?= $mine ? ' bubble--mine' : '' ?>">
                            <span class="bubble__who">
                                <?= e((string) $message['sender_name']) ?>
                                · <?= e((string) config('roles.list.' . $message['sender_role'], (string) $message['sender_role'])) ?>
                            </span>
                            <span class="bubble__body"><?= nl2br(e((string) $message['body'])) ?></span>
                            <span class="bubble__time"><?= e(jam((string) $message['created_at'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                    <?php if ($messages === []) : ?>
                        <li class="chat__empty">Belum ada pesan pada antrean ini.</li>
                    <?php endif; ?>
                </ol>

                <?php if ($open) : ?>
                    <form class="chat__form" id="chat-form">
                        <label class="field">
                            <span class="sr-only">Tulis pesan</span>
                            <textarea name="body" rows="2" maxlength="4000" placeholder="Tulis pesan…" required></textarea>
                        </label>
                        <button class="btn btn--primary" type="submit">Kirim</button>
                    </form>
                <?php else : ?>
                    <p class="chat__closed">
                        Percakapan belum terbuka. Chat aktif setelah antrean berstatus
                        <em>ditinjau</em>, dan ditutup tujuh hari setelah selesai.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
