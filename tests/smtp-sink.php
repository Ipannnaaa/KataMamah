<?php

declare(strict_types=1);

/**
 * Penampung SMTP untuk pengujian.
 *
 * Bukan pengganti SMTP di dalam aplikasi — aplikasi tetap mengirim lewat
 * PHPMailer seperti biasa. Yang dipalsukan di sini adalah servernya, bukan
 * pengirimnya, jadi percakapan SMTP yang sesungguhnya tetap teruji:
 * EHLO, MAIL FROM, RCPT TO, DATA, QUIT.
 *
 * Tiap email yang masuk ditulis sebagai berkas .eml di
 * storage/logs/smtp-sink/ supaya tes bisa membaca isinya.
 *
 * Jalankan:
 *     php tests/smtp-sink.php [port] [detik]
 *
 * Berhenti sendiri setelah batas waktunya, atau saat berkas
 * storage/logs/smtp-sink/STOP dibuat.
 */

$port = (int) ($argv[1] ?? 1025);
$maxDetik = (int) ($argv[2] ?? 180);

$dir = __DIR__ . '/../storage/logs/smtp-sink';

if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    fwrite(STDERR, "Tidak bisa membuat $dir\n");
    exit(1);
}

// scandir, bukan glob: path proyek memuat "[WIA]" dan glob membaca kurung
// sikunya sebagai kelas karakter, sehingga polanya tidak pernah cocok.
foreach (scandir($dir) ?: [] as $lama) {
    if ($lama !== '.' && $lama !== '..') {
        @unlink($dir . '/' . $lama);
    }
}

$server = @stream_socket_server("tcp://127.0.0.1:$port", $errno, $errstr);

if ($server === false) {
    fwrite(STDERR, "Gagal membuka port $port: $errstr ($errno)\n");
    exit(1);
}

echo "sink siap di 127.0.0.1:$port, berhenti setelah {$maxDetik}s\n";
flush();

$mulai = time();
$nomor = 0;

while (time() - $mulai < $maxDetik) {
    if (is_file($dir . '/STOP')) {
        break;
    }

    // Timeout pendek supaya berkas STOP dan batas waktu tetap diperiksa
    // walaupun tidak ada yang menyambung.
    $klien = @stream_socket_accept($server, 1);

    if ($klien === false) {
        continue;
    }

    stream_set_timeout($klien, 10);
    fwrite($klien, "220 sink.test ESMTP penampung uji\r\n");

    $dataMode = false;
    $pesan = '';
    $amplop = [];

    while (($baris = fgets($klien)) !== false) {
        if ($dataMode) {
            if (rtrim($baris, "\r\n") === '.') {
                $dataMode = false;
                $nomor++;
                $nama = sprintf('%s/%03d-%s.eml', $dir, $nomor, date('His'));
                file_put_contents($nama, implode("\n", $amplop) . "\n\n" . $pesan);
                $pesan = '';
                fwrite($klien, "250 2.0.0 Ok: antrean uji\r\n");

                continue;
            }

            // Titik ganda di awal baris adalah penyamaran SMTP; kembalikan.
            $pesan .= preg_replace('/^\.\./', '.', $baris);

            continue;
        }

        $perintah = strtoupper(substr(trim($baris), 0, 4));

        switch ($perintah) {
            case 'EHLO':
                fwrite($klien, "250-sink.test\r\n250-SIZE 10485760\r\n250 HELP\r\n");
                break;

            case 'HELO':
                fwrite($klien, "250 sink.test\r\n");
                break;

            case 'MAIL':
            case 'RCPT':
                $amplop[] = 'X-Sink-' . ucfirst(strtolower($perintah)) . ': ' . trim($baris);
                fwrite($klien, "250 2.1.0 Ok\r\n");
                break;

            case 'DATA':
                $dataMode = true;
                fwrite($klien, "354 Kirim datanya, akhiri dengan <CRLF>.<CRLF>\r\n");
                break;

            case 'RSET':
                $pesan = '';
                $amplop = [];
                fwrite($klien, "250 2.0.0 Ok\r\n");
                break;

            case 'NOOP':
                fwrite($klien, "250 2.0.0 Ok\r\n");
                break;

            case 'QUIT':
                fwrite($klien, "221 2.0.0 Sampai jumpa\r\n");
                break 2;

            default:
                fwrite($klien, "502 5.5.2 Perintah tidak dikenal penampung uji\r\n");
                break;
        }
    }

    fclose($klien);
}

fclose($server);
@unlink($dir . '/STOP');
echo "sink berhenti, $nomor email tertampung\n";
