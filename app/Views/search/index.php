<?php

/**
 * Hasil smart search tanpa JavaScript.
 *
 * @var string $term
 * @var array<int, array<string, string>> $results
 */

?>
<section class="container section section--narrow">
    <p class="eyebrow">Pencarian</p>
    <h1 class="page-title">Cari layanan, kantor, atau antrean</h1>

    <form class="smart-search smart-search--page" action="<?= e(url('/cari')) ?>" method="get" role="search"
          data-endpoint="<?= e(url('/api/search')) ?>">
        <label class="sr-only" for="q">Kata kunci</label>
        <span class="smart-search__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="7"></circle>
                <path d="M20 20l-3.5-3.5"></path>
            </svg>
        </span>
        <input id="q" name="q" type="search" class="smart-search__input" value="<?= e($term) ?>"
               placeholder="Layanan, kantor, kecamatan, atau kode antrean…"
               autocomplete="off" spellcheck="false"
               role="combobox" aria-expanded="false" aria-controls="smart-search-results" aria-autocomplete="list">
        <button class="btn btn--primary smart-search__submit" type="submit">Cari</button>
        <div id="smart-search-results" class="smart-search__results" role="listbox" hidden></div>
    </form>

    <?php if ($term === '') : ?>
        <p class="lede">
            Ketik nama layanan (misalnya <em>akta kelahiran</em>), nama kantor, nama kecamatan,
            atau kode pemesanan seperti <em><?= e((string) config('app.queue.code_prefix')) ?>-7KQ4M</em>.
        </p>
    <?php elseif ($results === []) : ?>
        <div class="card">
            <strong class="card__title">Tidak ada yang cocok dengan “<?= e($term) ?>”</strong>
            <p class="card__text">
                Coba kata yang lebih umum, misalnya <em>akta</em>, <em>KTP</em>, atau nama kecamatan.
                Kode pemesanan terdiri dari lima karakter setelah
                <em><?= e((string) config('app.queue.code_prefix')) ?>-</em>.
            </p>
            <p class="actions">
                <a class="btn btn--ghost" href="<?= e(url('/peta')) ?>">Buka peta layanan</a>
                <a class="btn btn--ghost" href="<?= e(url('/cek-antrean')) ?>">Cek antrean</a>
            </p>
        </div>
    <?php else : ?>
        <p class="section-sub"><?= e((string) count($results)) ?> hasil untuk “<?= e($term) ?>”</p>

        <ul class="result-list">
            <?php foreach ($results as $item) : ?>
                <li>
                    <a class="result" href="<?= e($item['url']) ?>">
                        <span class="result__type result__type--<?= e($item['type']) ?>"><?= e($item['label']) ?></span>
                        <span class="result__body">
                            <strong><?= e($item['title']) ?></strong>
                            <small><?= e($item['subtitle']) ?></small>
                        </span>
                        <span class="result__badge"><?= e($item['badge']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
