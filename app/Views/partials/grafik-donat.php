<?php

/**
 * Grafik donat untuk komposisi status.
 *
 * Potongannya dibuat dari stroke-dasharray pada lingkaran, bukan dari path
 * busur: tidak perlu hitung sudut, dan tebal cincinnya tetap sama persis di
 * semua potongan.
 *
 * @var array<int, array{nama: string, nilai: int, warna: string}> $bagian
 * @var string $judul
 * @var string $id
 */

$bagian = array_values($bagian ?? []);
$judul = $judul ?? 'Komposisi';
$id = $id ?? 'donat';

$total = 0;

foreach ($bagian as $b) {
    $total += max(0, (int) $b['nilai']);
}

$jari = 54.0;
$keliling = 2 * M_PI * $jari;
$mulai = 0.0;

?>
<figure class="donat" id="<?= e($id) ?>">
    <figcaption class="grafik__judul"><?= e($judul) ?></figcaption>

    <div class="donat__isi">
        <svg class="donat__kanvas" viewBox="0 0 140 140" role="img"
             aria-label="<?= e($judul . ', total ' . $total . ' antrean. Rinciannya ada pada daftar di sebelahnya.') ?>">
            <circle class="donat__alas" cx="70" cy="70" r="<?= $jari ?>"/>

            <?php foreach ($bagian as $b) : ?>
                <?php
                $nilai = max(0, (int) $b['nilai']);

                if ($total === 0 || $nilai === 0) {
                    continue;
                }

                $panjang = $keliling * $nilai / $total;
                ?>
                <circle class="donat__potong" cx="70" cy="70" r="<?= $jari ?>"
                        stroke="<?= e($b['warna']) ?>"
                        stroke-dasharray="<?= round($panjang, 2) ?> <?= round($keliling - $panjang, 2) ?>"
                        stroke-dashoffset="<?= round(-$mulai, 2) ?>"
                        data-judul="<?= e($b['nama']) ?>"
                        data-isi="<?= e($nilai . ' antrean|' . round($nilai / $total * 100) . ' persen') ?>"/>
                <?php $mulai += $panjang; ?>
            <?php endforeach; ?>

            <text x="70" y="66" class="donat__jumlah" text-anchor="middle"><?= e((string) $total) ?></text>
            <text x="70" y="84" class="donat__satuan" text-anchor="middle">antrean</text>
        </svg>

        <ul class="donat__daftar">
            <?php foreach ($bagian as $b) : ?>
                <?php $nilai = max(0, (int) $b['nilai']); ?>
                <li>
                    <span class="donat__tanda" style="background: <?= e($b['warna']) ?>" aria-hidden="true"></span>
                    <span class="donat__nama"><?= e($b['nama']) ?></span>
                    <span class="donat__nilai">
                        <?= e((string) $nilai) ?>
                        <small><?= e($total === 0 ? '0%' : round($nilai / $total * 100) . '%') ?></small>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</figure>
