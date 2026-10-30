<?php

declare(strict_types=1);

/**
 * Membaca email terakhir yang ditampung tests/smtp-sink.php.
 *
 * Badan email dikodekan base64 oleh PHPMailer, jadi mencari tautan dengan
 * regex pada berkas mentah tidak akan menemukan apa-apa — berkasnya diurai
 * di sini supaya skrip uji tidak perlu tahu soal MIME.
 *
 * Jalankan:
 *     php tests/read-sink.php tautan   # cetak URL verifikasi terakhir
 *     php tests/read-sink.php kepada   # cetak alamat penerima
 *     php tests/read-sink.php jumlah   # cetak banyaknya email tertampung
 *     php tests/read-sink.php isi      # cetak badan teksnya
 */

$dir = __DIR__ . '/../storage/logs/smtp-sink';
$apa = (string) ($argv[1] ?? 'tautan');

/*
 * scandir, bukan glob.
 *
 * Path proyek ini memuat tanda kurung siku ("[WIA]"), dan glob membacanya
 * sebagai kelas karakter — polanya lalu tidak pernah cocok dengan folder
 * yang sesungguhnya dan daftarnya selalu kosong.
 */
$berkas = [];

foreach (scandir($dir) ?: [] as $nama) {
    if (str_ends_with($nama, '.eml')) {
        $berkas[] = $dir . '/' . $nama;
    }
}

sort($berkas);

if ($apa === 'jumlah') {
    echo count($berkas), "\n";
    exit(0);
}

if ($berkas === []) {
    fwrite(STDERR, "tidak ada email tertampung\n");
    exit(1);
}

$mentah = (string) file_get_contents((string) end($berkas));

if ($apa === 'kepada') {
    preg_match('/^X-Sink-Rcpt: RCPT TO:<([^>]*)>/mi', $mentah, $m);
    echo ($m[1] ?? ''), "\n";
    exit(0);
}

/*
 * Ambil bagian text/plain, lalu kembalikan dari base64 bila perlu.
 * Cukup bagian pertama: isi HTML dan teksnya membawa tautan yang sama.
 */
$isi = '';

foreach (preg_split('/\r?\n--/', $mentah) ?: [] as $bagian) {
    if (stripos($bagian, 'Content-Type: text/plain') === false) {
        continue;
    }

    $pisah = preg_split('/\r?\n\r?\n/', $bagian, 2);
    $badan = trim($pisah[1] ?? '');

    $isi = stripos($bagian, 'Content-Transfer-Encoding: base64') !== false
        ? (string) base64_decode(preg_replace('/\s+/', '', $badan) ?? '', true)
        : $badan;

    break;
}

if ($apa === 'isi') {
    echo $isi, "\n";
    exit(0);
}

if (preg_match('#https?://\S*/verifikasi/[0-9a-f]{64}#', $isi, $m) !== 1) {
    fwrite(STDERR, "tautan verifikasi tidak ditemukan di email terakhir\n");
    exit(1);
}

echo $m[0], "\n";
