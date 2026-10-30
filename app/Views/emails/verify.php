<?php

/**
 * Email verifikasi.
 *
 * Gaya ditulis inline, bukan lewat <style> atau berkas CSS: klien email
 * seperti Gmail membuang sebagian besar CSS di <head>, dan tidak ada
 * satu pun yang memuat berkas eksternal.
 *
 * @var string $nama
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
          Satu langkah lagi, <?= e($nama) ?>
        </h1>
        <p style="margin:0 0 18px;font-size:15px;line-height:1.6;color:<?= $ink ?>;">
          Akun Anda sudah dibuat, tetapi belum bisa dipakai masuk sampai alamat
          email ini dipastikan milik Anda. Tekan tombol di bawah untuk
          memastikannya.
        </p>
      </td>
    </tr>
    <tr>
      <td style="padding:0 28px 20px;">
        <a href="<?= e($tautan) ?>"
           style="display:inline-block;padding:13px 22px;background:<?= $hijau ?>;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;border-radius:10px;">
          Verifikasi email saya
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
          Meminta tautan baru membuat yang lama tidak berlaku.
          <br><br>
          Bukan Anda yang mendaftar? Abaikan saja email ini — tanpa tautan itu
          ditekan, akunnya tidak akan aktif.
        </p>
      </td>
    </tr>
  </table>
  <p style="max-width:520px;margin:14px auto 0;font-size:11.5px;line-height:1.5;text-align:center;color:<?= $muted ?>;">
    Email ini dikirim otomatis. Jangan membalasnya.
  </p>
</div>
