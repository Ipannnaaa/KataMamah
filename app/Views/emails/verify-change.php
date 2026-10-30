<?php

/**
 * Email konfirmasi penggantian alamat, dikirim ke alamat BARU.
 *
 * Gaya inline seperti emails/verify.php — klien email membuang CSS di
 * <head> dan tidak memuat berkas eksternal.
 *
 * @var string $nama
 * @var string $lama
 * @var string $baru
 * @var string $tautan
 * @var int $jam
 */

$ink = '#13241f';
$muted = '#5c6b66';
$hijau = '#146c4a';
$garis = '#e2e6e2';

?>
<div style="margin:0;padding:24px 12px;background:#f0f2f0;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
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
          ke alamat ini. Tekan tombol di bawah bila itu memang Anda.
        </p>
        <p style="margin:0 0 18px;font-size:14px;line-height:1.6;color:<?= $muted ?>;">
          Sampai tautannya dibuka, <strong><?= e($lama) ?></strong> tetap menjadi
          alamat akun Anda dan tetap bisa dipakai masuk.
        </p>
      </td>
    </tr>
    <tr>
      <td style="padding:0 28px 20px;">
        <a href="<?= e($tautan) ?>"
           style="display:inline-block;padding:13px 22px;background:<?= $hijau ?>;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;border-radius:10px;">
          Pakai alamat ini
        </a>
      </td>
    </tr>
    <tr>
      <td style="padding:0 28px 24px;">
        <p style="margin:0 0 14px;font-size:13px;line-height:1.6;color:<?= $muted ?>;">
          Tombolnya tidak bisa ditekan? Salin alamat ini ke peramban:
        </p>
        <p style="margin:0 0 18px;font-size:12.5px;line-height:1.5;word-break:break-all;">
          <a href="<?= e($tautan) ?>" style="color:<?= $hijau ?>;"><?= e($tautan) ?></a>
        </p>
        <p style="margin:0;padding-top:16px;border-top:1px solid <?= $garis ?>;font-size:12.5px;line-height:1.6;color:<?= $muted ?>;">
          Tautan ini berlaku <?= e((string) $jam) ?> jam dan hanya bisa dipakai sekali.
          <br><br>
          Bukan Anda yang meminta? Abaikan email ini — tanpa tautan itu ditekan,
          alamat akunnya tidak berubah.
        </p>
      </td>
    </tr>
  </table>
</div>
