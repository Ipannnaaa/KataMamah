<?php

/**
 * Grafik garis untuk deret harian.
 *
 * SVG-nya digambar di server, sama seperti peta: tanpa JavaScript grafiknya
 * tetap tampil utuh dan tetap terbaca. chart.js hanya menambahkan tooltip
 * dan tombol untuk menyembunyikan seri.
 *
 * Kolom tak terlihat dipasang di tiap titik sebagai sasaran hover. Titik
 * lingkarannya terlalu kecil untuk dibidik dengan nyaman, apalagi di layar
 * sentuh.
 *
 * @var array<int, array<string, mixed>> $titik  baris dengan kunci 'tanggal'
 * @var array<int, array{kunci: string, nama: string, warna: string}> $deret
 * @var string $id
 * @var string $judul
 */

$titik = array_values($titik ?? []);
$deret = $deret ?? [];
$id = $id ?? 'grafik';
$judul = $judul ?? 'Grafik';

$n = count($titik);

if ($n === 0 || $deret === []) {
    return;
}

$kiri = 46.0;
$kanan = 706.0;
$atasY = 18.0;
$bawah = 198.0;

/* ------------------------------------------------------- skala sumbu Y */

$maks = 0;

foreach ($titik as $t) {
    foreach ($deret as $d) {
        $maks = max($maks, (int) ($t[$d['kunci']] ?? 0));
    }
}

// Dibulatkan ke angka bulat terdekat supaya empat garis bantunya jatuh di
// bilangan yang enak dibaca, bukan di 3,75 atau 7,5.
$maks = max(1, $maks);
$kasar = $maks / 4;
$pangkat = 10 ** max(0, (int) floor(log10(max($kasar, 1))));
$langkah = $pangkat;

foreach ([1, 2, 2.5, 5, 10] as $kelipatan) {
    $langkah = $kelipatan * $pangkat;

    if ($langkah >= $kasar) {
        break;
    }
}

$puncak = max(4, (int) ceil($langkah * 4));

$x = static function (int $i) use ($n, $kiri, $kanan): float {
    return $n <= 1 ? ($kiri + $kanan) / 2 : $kiri + ($kanan - $kiri) * $i / ($n - 1);
};

$y = static function (float $nilai) use ($puncak, $atasY, $bawah): float {
    return $bawah - ($bawah - $atasY) * ($nilai / $puncak);
};

$lompat = max(1, (int) ceil($n / 7));

?>
<figure class="grafik" id="<?= e($id) ?>">
    <figcaption class="grafik__kepala">
        <span class="grafik__judul"><?= e($judul) ?></span>
        <span class="grafik__kunci">
            <?php foreach ($deret as $d) : ?>
                <button type="button" class="kunci" aria-pressed="true"
                        data-deret="<?= e($d['kunci']) ?>"
                        style="--warna: <?= e($d['warna']) ?>">
                    <span class="kunci__titik" aria-hidden="true"></span><?= e($d['nama']) ?>
                </button>
            <?php endforeach; ?>
        </span>
    </figcaption>

    <svg class="grafik__kanvas" viewBox="0 0 720 232" role="img"
         aria-label="<?= e($judul . ', ' . $n . ' hari terakhir. Rinciannya ada pada tabel di bawah.') ?>">
        <?php for ($g = 0; $g <= 4; $g++) : ?>
            <?php $gy = $bawah - ($bawah - $atasY) * ($g / 4); ?>
            <line x1="<?= $kiri ?>" y1="<?= round($gy, 1) ?>" x2="<?= $kanan ?>" y2="<?= round($gy, 1) ?>"
                  class="grafik__garis-bantu"/>
            <text x="<?= $kiri - 10 ?>" y="<?= round($gy + 4, 1) ?>" class="grafik__angka" text-anchor="end">
                <?= e((string) (int) round($puncak * $g / 4)) ?>
            </text>
        <?php endfor; ?>

        <?php foreach ($deret as $urut => $d) : ?>
            <?php
            $koordinat = [];

            foreach ($titik as $i => $t) {
                $koordinat[] = round($x($i), 1) . ',' . round($y((float) ($t[$d['kunci']] ?? 0)), 1);
            }

            $polyline = implode(' ', $koordinat);
            ?>
            <g class="grafik__deret" data-deret="<?= e($d['kunci']) ?>" style="--warna: <?= e($d['warna']) ?>">
                <?php if ($urut === 0 && $n > 1) : ?>
                    <path class="grafik__area"
                          d="M<?= round($x(0), 1) ?>,<?= $bawah ?> L<?= str_replace(' ', ' L', $polyline) ?> L<?= round($x($n - 1), 1) ?>,<?= $bawah ?> Z"/>
                <?php endif; ?>
                <polyline class="grafik__jalur" points="<?= e($polyline) ?>"/>
                <?php foreach ($titik as $i => $t) : ?>
                    <circle class="grafik__titik" r="3"
                            cx="<?= round($x($i), 1) ?>"
                            cy="<?= round($y((float) ($t[$d['kunci']] ?? 0)), 1) ?>"/>
                <?php endforeach; ?>
            </g>
        <?php endforeach; ?>

        <line x1="<?= $kiri ?>" y1="<?= $bawah ?>" x2="<?= $kanan ?>" y2="<?= $bawah ?>" class="grafik__sumbu"/>

        <?php foreach ($titik as $i => $t) : ?>
            <?php if ($i % $lompat === 0 || $i === $n - 1) : ?>
                <text x="<?= round($x($i), 1) ?>" y="<?= $bawah + 20 ?>" class="grafik__angka" text-anchor="middle">
                    <?= e(tanggal_pendek((string) $t['tanggal'])) ?>
                </text>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php
        $lebarKolom = $n <= 1 ? ($kanan - $kiri) : ($kanan - $kiri) / ($n - 1);
        ?>
        <?php foreach ($titik as $i => $t) : ?>
            <?php
            $baris = [];

            // Tiap baris diawali kunci serinya supaya tooltip ikut
            // menyembunyikan baris milik seri yang sedang dimatikan.
            // Awalan ":" berarti baris itu selalu tampil.
            foreach ($deret as $d) {
                $baris[] = $d['kunci'] . ':' . $d['nama'] . ' ' . (int) ($t[$d['kunci']] ?? 0);
            }

            if (array_key_exists('tunggu', $t)) {
                $baris[] = ':' . ($t['tunggu'] === null
                    ? 'Belum ada yang diterima'
                    : 'Rata-rata tunggu ' . (int) $t['tunggu'] . ' menit');
            }
            ?>
            <rect class="grafik__sasaran"
                  x="<?= round($x($i) - $lebarKolom / 2, 1) ?>" y="<?= $atasY ?>"
                  width="<?= round($lebarKolom, 1) ?>" height="<?= $bawah - $atasY ?>"
                  data-judul="<?= e(tanggal((string) $t['tanggal'])) ?>"
                  data-isi="<?= e(implode('|', $baris)) ?>"/>
        <?php endforeach; ?>
    </svg>
</figure>
