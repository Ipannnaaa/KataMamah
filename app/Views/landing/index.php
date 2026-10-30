<?php

/**
 * Isi halaman pembuka: judul, smart search, dan dua tombol utama.
 *
 * @var array<string, int> $stats
 */

?>
<div class="hero">
    <?= \App\Core\View::partial('wordmark', ['tag' => 'h1']) ?>

    <p class="hero__lede">
        Jelajahi layanan publik Kabupaten <?= e((string) config('app.wilayah')) ?>: telusuri kantor
        layanan lewat peta per kecamatan, baca syarat berkasnya, lalu ambil antrean
        tanpa perlu menunggu di tempat.
    </p>

    <!-- Smart search: satu kotak untuk layanan, lokasi, kecamatan, dan kode antrean -->
    <form class="smart-search" action="<?= e(url('/cari')) ?>" method="get" role="search"
          data-endpoint="<?= e(url('/api/search')) ?>">
        <label class="sr-only" for="q">Cari layanan, lokasi, atau kode antrean</label>
        <span class="smart-search__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="7"></circle>
                <path d="M20 20l-3.5-3.5"></path>
            </svg>
        </span>
        <input
            id="q"
            name="q"
            type="search"
            class="smart-search__input"
            placeholder="Cari layanan, kantor, kecamatan, atau kode antrean…"
            autocomplete="off"
            spellcheck="false"
            role="combobox"
            aria-expanded="false"
            aria-controls="smart-search-results"
            aria-autocomplete="list"
        >
        <button class="btn btn--primary smart-search__submit" type="submit">Cari</button>

        <div id="smart-search-results" class="smart-search__results" role="listbox" hidden></div>
    </form>

    <p class="hero__examples">
        Coba: <button type="button" class="chip chip--try" data-q="akta kelahiran">akta kelahiran</button>
        <button type="button" class="chip chip--try" data-q="jatirogo">jatirogo</button>
        <button type="button" class="chip chip--try" data-q="SKCK">SKCK</button>
    </p>

    <div class="hero__actions">
        <span class="tipwrap">
            <a class="btn btn--primary btn--hero" href="<?= e(url('/peta')) ?>">
                <span class="btn__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 4L3 7v13l6-3 6 3 6-3V4l-6 3-6-3z"></path>
                        <path d="M9 4v13M15 7v13"></path>
                    </svg>
                </span>
                PETA LAYANAN
            </a>
            <span class="tip">Peta isometrik 20 kecamatan Tuban</span>
        </span>

        <span class="tipwrap">
            <a class="btn btn--outline btn--hero" href="<?= e(url('/cek-antrean')) ?>">
                <span class="btn__icon btn__icon--light" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 7v5l3 2"></path>
                        <circle cx="12" cy="12" r="9"></circle>
                    </svg>
                </span>
                CEK ANTREAN
            </a>
            <span class="tip">Lacak status dengan kode pemesanan</span>
        </span>
    </div>

    <p class="hero__pill">
        <a href="<?= e(url('/tentang')) ?>">Tentang aplikasi ini <span aria-hidden="true">→</span></a>
    </p>

    <dl class="hero__stats">
        <div><dt>Kecamatan</dt><dd><?= e((string) ($stats['districts'] ?? 0)) ?></dd></div>
        <div><dt>Kantor layanan</dt><dd><?= e((string) ($stats['locations'] ?? 0)) ?></dd></div>
        <div><dt>Jenis layanan</dt><dd><?= e((string) ($stats['services'] ?? 0)) ?></dd></div>
        <div><dt>Antrean hari ini</dt><dd><?= e((string) ($stats['queues_today'] ?? 0)) ?></dd></div>
    </dl>
</div>
