<?php

/**
 * Wordmark "Tuban Civic Map" yang bisa dianimasikan.
 *
 * "Civic Map" adalah nama tetap produknya, sedangkan bagian depannya mengikuti
 * kabupaten yang sedang dipakai. Karena itu nama dipecah di sini, bukan
 * ditulis tangan: mengganti APP_WILAYAH di .env sudah cukup, dan kalau
 * APP_NAME ternyata tidak diawali nama kabupaten, seluruh nama diperlakukan
 * sebagai judul sehingga tidak ada yang hilang.
 *
 * Tiap huruf kabupaten dibungkus span sendiri supaya bisa muncul berurutan;
 * pembaca layar tetap menerima satu nama utuh lewat aria-label di pembungkus.
 *
 * @var string|null $tag  Elemen pembungkus, mis. 'h1'. Default 'span'.
 */

$nama = (string) config('app.name');
$wilayah = trim((string) config('app.wilayah'));

$kota = '';
$judul = $nama;

if ($wilayah !== '' && stripos($nama, $wilayah) === 0) {
    $kota = mb_substr($nama, 0, mb_strlen($wilayah));
    $judul = trim(mb_substr($nama, mb_strlen($wilayah)));
}

$huruf = $kota === '' ? [] : (mb_str_split($kota) ?: []);
$tag = in_array($tag ?? 'span', ['h1', 'h2', 'p', 'span'], true) ? ($tag ?? 'span') : 'span';

?>
<<?= $tag ?> class="wordmark" aria-label="<?= e($nama) ?>">
    <span class="wordmark__pin" aria-hidden="true">
        <span class="wordmark__riak"></span>
        <svg viewBox="0 0 40 52" width="34" height="44" aria-hidden="true">
            <path d="M20 1C10.6 1 3 8.6 3 18c0 12 17 33 17 33s17-21 17-33c0-9.4-7.6-17-17-17z"
                  fill="currentColor"></path>
            <circle cx="20" cy="18" r="6.5" fill="#ffffff"></circle>
        </svg>
    </span>

    <?php if ($huruf !== []) : ?>
        <span class="wordmark__kota" aria-hidden="true">
            <?php foreach ($huruf as $i => $h) : ?>
                <span class="wordmark__huruf" style="--i: <?= e((string) $i) ?>"><?= e($h) ?></span>
            <?php endforeach; ?>
        </span>
    <?php endif; ?>

    <span class="wordmark__judul" aria-hidden="true" style="--n: <?= e((string) count($huruf)) ?>">
        <?= e($judul) ?>
    </span>

    <span class="wordmark__garis" aria-hidden="true"></span>
</<?= $tag ?>>
