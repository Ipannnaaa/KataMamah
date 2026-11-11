<?php

/**
 * Email konfirmasi penggantian alamat, dikirim ke alamat BARU.
 *
 * Memakai kode, sama seperti emails/verify-code.php. Gaya inline karena
 * klien email membuang CSS di <head> dan tidak memuat berkas eksternal.
 *
 * @var string $nama
 * @var string $lama
 * @var string $baru
 * @var string $kode
 * @var int $menit
 */

$ink = '#13241f';
$muted = '#5c6b66';
$hijau = '#146c4a';
$garis = '#e2e6e2';
$lembut = '#f0f2f0';

?>
<div style="margin:0;padding:24px 12px;background:<?= $lembut ?>;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid <?= $garis ?>;border-radius:14px;">
    <tr>
      <td style="padding:28px 28px 8px;">
        <p style="margin:0 0 4px;font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:<?= $hijau ?>;">
          <?= e((string) config('app.name')) ?>
        </p>
        <h1 style="margin:0 0 14px;font-size:21px;line-height:1.3;color:<?= $ink ?>;">
          Konfirmasi alamat baru, <?= e($nama) ?>
        </h1>
        <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:<?= $ink ?>;">
          Ada permintaan memindahkan akun dari <strong><?= e($lama) ?></strong>
          ke alamat ini, <strong><?= e($baru) ?></strong>. Masukkan kode di bawah
          pada halaman verifikasi untuk menyelesaikannya.
        </p>
        <p style="margin:0 0 18px;font-size:15px;line-height:1.6;color:<?= $ink ?>;">
          Sampai kodenya dimasukkan, <strong><?= e($lama) ?></strong> tetap menjadi
          alamat akun itu.
        </p>
      </td>
    </tr>
    <tr>
      <td style="padding:0 28px 18px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
               style="background:<?= $lembut ?>;border:1px solid <?= $garis ?>;border-radius:12px;">
          <tr>
            <td align="center" style="padding:22px 16px;">
              <div style="font-family:Consolas,Menlo,Courier New,monospace;font-size:34px;font-weight:700;letter-spacing:.22em;color:<?= $ink ?>;">
                <?= e($kode) ?>
              </div>
              <div style="margin-top:8px;font-size:12.5px;color:<?= $muted ?>;">
                Berlaku <?= e((string) $menit) ?> menit
              </div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <td style="padding:0 28px 24px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
               style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;">
          <tr>
            <td style="padding:14px 16px;">
              <p style="margin:0;font-size:13px;line-height:1.6;color:#7c2d12;">
                <strong>Jangan berikan kode ini kepada siapa pun</strong>, termasuk
                orang yang mengaku petugas <?= e((string) config('app.name')) ?>.
                Kami tidak pernah menanyakannya lewat telepon, WhatsApp, atau pesan
                apa pun.
              </p>
            </td>
          </tr>
        </table>

        <p style="margin:18px 0 0;padding-top:16px;border-top:1px solid <?= $garis ?>;font-size:12.5px;line-height:1.6;color:<?= $muted ?>;">
          Kode ini hanya bisa dipakai sekali. Meminta kode baru membuat yang lama
          tidak berlaku.
          <br><br>
          Bukan Anda yang meminta? Abaikan email ini. Tanpa kode itu dimasukkan,
          alamat akun tidak berubah.
        </p>
      </td>
    </tr>
  </table>
  <p style="max-width:520px;margin:14px auto 0;font-size:11.5px;line-height:1.5;text-align:center;color:<?= $muted ?>;">
    Email ini dikirim otomatis. Jangan membalasnya.
  </p>
</div>
