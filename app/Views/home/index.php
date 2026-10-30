<?php

/**
 * Peta isometrik dua tingkat.
 *
 * Tingkat 1: Kabupaten Tuban sebagai satu daratan, penanda per kecamatan.
 * Tingkat 2: setelah kecamatan dipilih, peta memperbesar dan memunculkan
 *            kantor-kantor layanan di kecamatan tersebut.
 *
 * Tanpa JavaScript halaman tetap berguna: seluruh penanda kecamatan adalah
 * tautan ke /kecamatan/{slug}, dan setiap kantor adalah tautan ke halamannya.
 *
 * @var array<int, array<string, mixed>> $districts
 * @var array<int, array<string, mixed>> $locations
 * @var array<string, mixed>|null $active
 * @var array<string, mixed> $scene
 * @var float $zoomScale
 */

$w = (int) $scene['width'];
$h = (int) $scene['height'];
$activeSlug = $active === null ? '' : (string) $active['slug'];

?>
<section class="container section">
    <div class="page-head">
        <div>
            <p class="eyebrow">Kabupaten <?= e((string) config('app.wilayah')) ?></p>
            <h1 class="page-title">
                <?= $active === null ? 'Peta layanan publik' : 'Kecamatan ' . e((string) $active['name']) ?>
            </h1>
            <p class="lede" id="iso-hint">
                <?= $active === null
                    ? 'Klik sebuah kecamatan untuk memperbesar dan melihat kantor layanan di dalamnya.'
                    : 'Arahkan kursor ke sebuah kantor untuk melihat layanan yang tersedia.' ?>
            </p>
        </div>
        <a class="btn btn--dark" href="<?= e(url('/cek-antrean')) ?>">Cek kode antrean</a>
    </div>

    <form class="smart-search smart-search--inline" action="<?= e(url('/cari')) ?>" method="get" role="search"
          data-endpoint="<?= e(url('/api/search')) ?>">
        <label class="sr-only" for="q">Cari layanan, kantor, atau kode antrean</label>
        <span class="smart-search__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path>
            </svg>
        </span>
        <input id="q" name="q" type="search" class="smart-search__input"
               placeholder="Cari layanan, kantor, kecamatan, atau kode antrean…"
               autocomplete="off" spellcheck="false"
               role="combobox" aria-expanded="false" aria-controls="smart-search-results" aria-autocomplete="list">
        <div id="smart-search-results" class="smart-search__results" role="listbox" hidden></div>
    </form>

    <div class="iso" id="peta" data-zoom="<?= e((string) $zoomScale) ?>" data-active="<?= e($activeSlug) ?>">

        <div class="iso__bar">
            <button type="button" class="btn btn--ghost btn--small iso__back" id="iso-back" hidden>
                <span aria-hidden="true">←</span> Seluruh kabupaten
            </button>
            <button type="button" class="btn btn--ghost btn--small iso__desa" id="iso-desa"
                    aria-pressed="false" hidden>
                Nama desa
            </button>
            <p class="iso__scope" id="iso-scope">
                <?= $active === null
                    ? e((string) count($districts)) . ' kecamatan'
                    : 'Kecamatan ' . e((string) $active['name']) ?>
            </p>
        </div>

        <div class="iso__stage" style="--iso-w: <?= e((string) $w) ?>; --iso-h: <?= e((string) $h) ?>;">
            <div class="iso__world" id="iso-world">

                <svg class="iso__svg" viewBox="0 0 <?= e((string) $w) ?> <?= e((string) $h) ?>"
                     role="img" aria-label="Peta isometrik Kabupaten <?= e((string) config('app.wilayah')) ?> digambarkan sebagai pulau, beserta kecamatan dan kantor layanannya">
                    <defs>
                        <linearGradient id="iso-sea" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#A7D7EE"></stop>
                            <stop offset="100%" stop-color="#6FB2D6"></stop>
                        </linearGradient>
                        <linearGradient id="iso-land" x1="0.1" y1="0" x2="0.6" y2="1">
                            <stop offset="0%" stop-color="#9BD186"></stop>
                            <stop offset="55%" stop-color="#7CC065"></stop>
                            <stop offset="100%" stop-color="#63A851"></stop>
                        </linearGradient>
                        <linearGradient id="iso-soil" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#A9815A"></stop>
                            <stop offset="100%" stop-color="#8A6744"></stop>
                        </linearGradient>
                        <linearGradient id="iso-rock" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#7A6250"></stop>
                            <stop offset="100%" stop-color="#5C4938"></stop>
                        </linearGradient>

                        <?php if ($scene['island'] !== []) : ?>
                            <!-- Garis pantai dihaluskan saat digambar, sedangkan batas
                                 kecamatan dan desa memakai bentuk aslinya. Tanpa kurungan
                                 ini, garis batas bisa menyeberang ke pasir dan laut. -->
                            <clipPath id="iso-darat">
                                <polygon points="<?= e($scene['island']['top']) ?>"></polygon>
                            </clipPath>
                        <?php endif; ?>
                    </defs>

                    <!-- laut -->
                    <rect width="<?= e((string) $w) ?>" height="<?= e((string) $h) ?>" fill="url(#iso-sea)"></rect>
                    <g stroke="#FFFFFF" stroke-width="3" fill="none" opacity=".45" stroke-linecap="round">
                        <path d="M90 118 q20 -8 40 0 t40 0"></path>
                        <path d="M1090 150 q20 -8 40 0 t40 0"></path>
                        <path d="M150 640 q20 -8 40 0 t40 0"></path>
                        <path d="M980 672 q20 -8 40 0 t40 0"></path>
                    </g>

                    <?php if ($scene['island'] !== []) : ?>
                        <!-- pulau: air dangkal, bayangan, batuan, tanah, pasir, rumput -->
                        <polygon points="<?= e($scene['island']['shallow']) ?>" fill="#BFE6F3" opacity=".55"></polygon>
                        <polygon points="<?= e($scene['island']['foam']) ?>" fill="#D9F0F7" opacity=".7"></polygon>
                        <ellipse cx="<?= e((string) $scene['island']['shadow']['cx']) ?>"
                                 cy="<?= e((string) $scene['island']['shadow']['cy']) ?>"
                                 rx="<?= e((string) $scene['island']['shadow']['rx']) ?>"
                                 ry="<?= e((string) $scene['island']['shadow']['ry']) ?>"
                                 fill="#3C7FA0" opacity=".28"></ellipse>
                        <polygon points="<?= e($scene['island']['rock']) ?>" fill="url(#iso-rock)"></polygon>
                        <polygon points="<?= e($scene['island']['soil']) ?>" fill="url(#iso-soil)"></polygon>
                        <polygon points="<?= e($scene['island']['sand']) ?>" fill="#EEDFB4"></polygon>
                        <polygon points="<?= e($scene['island']['top']) ?>" fill="url(#iso-land)"
                                 stroke="#5E9F4C" stroke-width="1.5" stroke-linejoin="round"></polygon>
                    <?php endif; ?>

                    <!-- batas desa/kelurahan, muncul saat kecamatan dibuka -->
                    <?php foreach ($scene['districts'] as $tile) : ?>
                        <?php if ($tile['villages'] === []) : ?>
                            <?php continue; ?>
                        <?php endif; ?>
                        <g class="iso-villages" data-district="<?= e($tile['slug']) ?>" clip-path="url(#iso-darat)">
                            <?php foreach ($tile['villages'] as $village) : ?>
                                <polygon class="iso-village" points="<?= e($village['cell']) ?>"></polygon>
                            <?php endforeach; ?>
                            <?php foreach ($tile['villages'] as $village) : ?>
                                <text class="iso-village__name" x="<?= e((string) $village['lx']) ?>"
                                      y="<?= e((string) $village['ly']) ?>" text-anchor="middle"><?= e($village['name']) ?></text>
                            <?php endforeach; ?>
                        </g>
                    <?php endforeach; ?>

                    <!-- batas antar kecamatan -->
                    <g class="iso-cells" clip-path="url(#iso-darat)" stroke="<?= e((string) config('map.border_color', '#F2F7F0')) ?>"
                       stroke-width="<?= e((string) config('map.border_width', 1.6)) ?>"
                       stroke-linejoin="round">
                        <?php foreach ($scene['districts'] as $tile) : ?>
                            <?php if ($tile['cell'] === '') : ?>
                                <?php continue; ?>
                            <?php endif; ?>
                            <polygon class="iso-cell" data-district="<?= e($tile['slug']) ?>"
                                     points="<?= e($tile['cell']) ?>"></polygon>
                        <?php endforeach; ?>
                    </g>
                </svg>

                <!-- tingkat 1: penanda kecamatan -->
                <?php foreach ($scene['districts'] as $tile) : ?>
                    <a class="iso-kec"
                       href="<?= e(url('/kecamatan/' . $tile['slug'])) ?>"
                       data-district="<?= e($tile['slug']) ?>"
                       data-name="<?= e($tile['name']) ?>"
                       data-places="<?= e((string) $tile['places']) ?>"
                       data-zoom="<?= e((string) $tile['zoom']) ?>"
                       style="left: <?= e((string) $tile['px']) ?>%; top: <?= e((string) $tile['py']) ?>%;">
                        <span class="iso-kec__pin" aria-hidden="true">
                            <svg viewBox="0 0 32 40" width="26" height="33">
                                <path d="M16 39C16 39 29 24.5 29 14.5A13 13 0 1 0 3 14.5C3 24.5 16 39 16 39Z" fill="#0E6B5C"></path>
                                <circle cx="16" cy="14.5" r="5.5" fill="#FFFFFF"></circle>
                            </svg>
                        </span>
                        <span class="iso-kec__name"><?= e($tile['name']) ?></span>
                        <span class="iso-kec__count"><?= e((string) $tile['places']) ?> kantor</span>
                    </a>
                <?php endforeach; ?>

                <!-- tingkat 2: kantor layanan per kecamatan -->
                <?php foreach ($scene['landmarks'] as $mark) : ?>
                    <a class="iso-mark<?= $mark['dense'] ? ' is-dense' : '' ?>"
                       href="<?= e($mark['url']) ?>"
                       data-district="<?= e($mark['district_slug']) ?>"
                       data-name="<?= e($mark['name']) ?>"
                       data-category="<?= e($mark['category']) ?>"
                       data-category-label="<?= e($mark['category_label']) ?>"
                       data-color="<?= e($mark['color']) ?>"
                       data-desc="<?= e($mark['short_desc']) ?>"
                       data-address="<?= e($mark['address']) ?>"
                       data-photo="<?= e($mark['photo']) ?>"
                       data-quota="<?= e((string) $mark['quota_left']) ?>"
                       data-services="<?= e(implode('|', $mark['services'])) ?>"
                       data-service-count="<?= e((string) $mark['service_count']) ?>"
                       data-maps="<?= e($mark['maps_url']) ?>"
                       style="left: <?= e((string) $mark['px']) ?>%; top: <?= e((string) $mark['py']) ?>%;">
                        <span class="iso-pin" style="--pin: <?= e($mark['color']) ?>" aria-hidden="true">
                            <svg class="iso-pin__shape" viewBox="0 0 40 52" width="38" height="49">
                                <path d="M20 51C20 51 37 30.2 37 19A17 17 0 1 0 3 19C3 30.2 20 51 20 51Z"
                                      fill="<?= e($mark['color']) ?>"></path>
                                <path d="M20 2a17 17 0 0 1 14.6 25.7A17 17 0 0 0 20 4.6Z" fill="#FFFFFF" opacity=".22"></path>
                                <circle cx="20" cy="18.8" r="12.4" fill="#FFFFFF"></circle>
                            </svg>
                            <span class="iso-pin__icon"><?= $mark['icon'] ?></span>
                        </span>
                        <span class="iso-mark__name"><?= e($mark['name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- kartu pratinjau saat kursor menyentuh kantor -->
        <div class="iso-preview" id="iso-preview" hidden></div>
    </div>

    <?php
    // Keterangan warna dan ikon, hanya untuk kategori yang benar-benar ada.
    $kategori = [];

    foreach ($scene['landmarks'] as $mark) {
        $kategori[$mark['category']] = ['label' => $mark['category_label'], 'color' => $mark['color'], 'icon' => $mark['icon']];
    }

    ksort($kategori);
    ?>
    <!-- Legenda sekaligus penyaring: menekan satu jenis memunculkan seluruh
         fasilitas jenis itu di satu kabupaten, bukan per kecamatan. -->
    <ul class="iso-legend" id="iso-legend" aria-label="Saring peta menurut jenis fasilitas">
        <li>
            <button type="button" class="iso-legend__btn is-all" data-filter="" aria-pressed="true">
                Semua jenis
            </button>
        </li>
        <?php foreach ($kategori as $slug => $item) : ?>
            <li>
                <button type="button" class="iso-legend__btn" data-filter="<?= e((string) $slug) ?>"
                        aria-pressed="false">
                    <span class="iso-legend__dot" style="--pin: <?= e($item['color']) ?>"><?= $item['icon'] ?></span>
                    <?= e($item['label']) ?>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>

    <p class="iso__note">
        Batas wilayah mengikuti data
        <a href="https://www.openstreetmap.org/relation/9676649" target="_blank" rel="noopener noreferrer">OpenStreetMap</a>
        (© kontributor OpenStreetMap, ODbL), disederhanakan untuk tampilan.
        Batas antar kecamatan memakai batas administratif asli dari OpenStreetMap.
        Letak kantor diproyeksikan dari koordinat aslinya, lalu dirapikan agar pin
        tidak saling menumpuk — tombol <strong>Go · Google Maps</strong> pada setiap
        kantor menunjuk ke koordinat sebenarnya. Sebagian kantor belum terpetakan di
        OpenStreetMap dan ditandai sebagai perkiraan di halamannya masing-masing.
    </p>
</section>

<section class="container section">
    <div class="section-head">
        <h2 class="section-title">
            <?= $active === null ? 'Kantor layanan di Kabupaten ' . e((string) config('app.wilayah'))
                : 'Kantor layanan di Kecamatan ' . e((string) $active['name']) ?>
        </h2>
        <p class="section-sub">Setiap kantor membuka deskripsi, daftar layanan, dan syarat berkas.</p>
    </div>

    <?php if ($active === null) : ?>
        <div class="chip-row">
            <?php foreach ($districts as $district) : ?>
                <a class="chip" href="<?= e(url('/kecamatan/' . $district['slug'])) ?>">
                    <?= e((string) $district['name']) ?> · <?= e((string) $district['places']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <p class="actions">
            <a class="btn btn--ghost" href="<?= e(url('/peta')) ?>">Lihat seluruh kabupaten</a>
        </p>
    <?php endif; ?>

    <?php if ($locations === []) : ?>
        <p class="empty">Belum ada kantor layanan aktif di wilayah ini.</p>
    <?php else : ?>
        <div class="card-grid">
            <?php foreach ($locations as $location) : ?>
                <a class="card card--link" href="<?= e(url('/lokasi/' . $location['slug'])) ?>">
                    <span class="card__top">
                        <span class="chip"><?= e((string) config('map.category_labels.' . $location['category'], $location['category'])) ?></span>
                        <span class="card__meta"><?= e((string) $location['quota_left']) ?> kuota</span>
                    </span>
                    <strong class="card__title"><?= e((string) $location['name']) ?></strong>
                    <span class="card__text"><?= e((string) ($location['short_desc'] ?? '')) ?></span>
                    <span class="card__foot"><?= e((string) $location['district_name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
