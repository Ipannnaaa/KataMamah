# Menaruh aplikasi ini di hosting

Catatan ini ditulis untuk hosting cPanel, yang dipakai Biznet lewat NEO
Hosting, dengan domain `beacon-its.com`. Langkahnya sama saja di hosting
cPanel lain, cuma nama akun dan nama basis datanya yang beda.

Aplikasi ini tidak butuh Composer, npm, atau akses root. Yang dibutuhkan cuma
PHP, MySQL, dan Apache dengan `mod_rewrite`.

## 1. Pilih versi PHP dan nyalakan ekstensinya

cPanel, menu **Select PHP Version** (di sebagian tema namanya **MultiPHP
Manager**).

Pilih PHP **8.1** atau **8.2**. Paling rendah 8.0, karena di versi itu
aplikasi ini dikembangkan dan diuji.

Centang lima ekstensi ini:

| Ekstensi | Dipakai untuk |
|---|---|
| `pdo_mysql` | semua akses basis data |
| `mbstring` | pemotongan dan perapian teks |
| `openssl` | SMTP di port 465, jadi kode verifikasi bisa terkirim |
| `gd` | mengecilkan foto gedung dan menggambar berkas hasil |
| `fileinfo` | memeriksa jenis berkas yang diunggah warga |

Kalau `gd` mati, unggah foto gagal. Kalau `openssl` mati, email kode
verifikasi tidak pernah terkirim dan halaman daftar akan bilang begitu.

## 2. Buat basis data

cPanel, menu **MySQL Databases**.

1. Buat database bernama `Katamamah`. cPanel menyimpannya dengan awalan nama
   akun, jadi nama lengkapnya jadi `beaconio_Katamamah`.
2. Buat pengguna MySQL `katamamah`, yang jadi `beaconio_katamamah`.
3. Di bagian **Add User To Database**, pasangkan pengguna itu ke database
   tadi dan beri **ALL PRIVILEGES**.

Kedua nama itu sudah tertulis di `.env.hosting`, jadi tidak perlu diketik
ulang. Perhatikan huruf besar kecilnya: nama database pakai `K` besar,
nama penggunanya `k` kecil, dan di Linux keduanya dibedakan.

## 3. Pindahkan isi basis data dari lokal

Cara paling aman adalah membawa isi basis data yang sudah Anda pakai selama
pengembangan, lengkap dengan 132 kantor, 631 layanan, petugas, dan contoh
antreannya.

Di komputer sendiri:

```bash
C:\xampp\mysql\bin\mysqldump.exe -u root --default-character-set=utf8mb4 --no-tablespaces wia > katamamah.sql
```

Lalu di hosting: buka **phpMyAdmin**, pilih dulu database `beaconio_Katamamah` di
panel kiri, masuk tab **Import**, pilih `katamamah.sql`, Import.

Kalau berkasnya kebesaran untuk diunggah, zip dulu. phpMyAdmin menerima
`.sql.zip` langsung.

Mau mulai dari kosong, dengan data contoh yang baru? Itu dikerjakan
`public/pasang.php`, lihat bagian 7. Cara itu tidak butuh akses baris
perintah sama sekali.

Abaikan `database/seed.sql`. Isinya tertinggal dari versi lama dan datanya
bukan Tuban.

## 4. Unggah berkas aplikasi

Taruh di **luar** `public_html`, misalnya di `/home/akun/katamamah`.

Cara pertama, lewat cPanel **Git Version Control**. Clone URL
`https://github.com/Ipannnaaa/KataMamah.git`, Repository Path
`/home/akun/katamamah`. Reponya publik jadi tidak perlu kunci SSH. Enaknya,
kalau nanti ada perbaikan cukup tekan Pull, tidak perlu unggah ulang.

Cara kedua, zip folder proyek di komputer sendiri, unggah lewat **File
Manager**, lalu Extract. Jangan ikutkan `.git` dan isi `storage/logs`
serta `storage/cache`.

## 5. Arahkan domain ke folder `public`

Yang boleh dibuka dari internet cuma folder `public`. Sisanya, termasuk
`.env` dan seluruh kode, harus di luar jangkauan.

cPanel, menu **Domains**, pilih `beacon-its.com`, ubah **Document Root**
menjadi `/home/akun/katamamah/public`.

Kalau Document Root domain utama tidak bisa diubah di hosting Anda, taruh
seluruh proyek di `public_html` dan buat `public_html/.htaccess` berisi:

```apache
RewriteEngine On

# Berkas sumber tidak boleh dibuka dari luar.
RewriteRule ^(app|config|routes|storage|database|tests|bootstrap\.php|\.env) - [F,L]

# Sisanya dilayani dari dalam folder public.
RewriteCond %{REQUEST_URI} !^/public/
RewriteRule ^(.*)$ public/$1 [L]
```

Cara ini jalan, tapi kode sumbernya jadi berada di dalam document root dan
keamanannya cuma bergantung pada satu aturan rewrite. Pakai cara pertama
kalau masih bisa.

## 6. Isi `.env` di server

`.env` ikut repo, tapi isinya setelan untuk komputer sendiri dan sengaja
tanpa sandi. Yang dipakai di server sudah disiapkan terpisah, `.env.hosting`,
lengkap dengan nama basis data dan nama pengguna yang benar. Di server:

```bash
cd /home/akun/katamamah && cp .env.hosting .env
```

Lalu buka `.env` lewat File Manager dan isi tiga baris yang sengaja
dikosongkan:

| Baris | Diisi dengan |
|---|---|
| `DB_PASS` | sandi pengguna MySQL yang dibuat di bagian 2 |
| `MAIL_PASSWORD` | sandi mailbox `katamamah@beacon-its.com` |
| `INSTALL_KEY` | karangan acak minimal 16 karakter, dipakai di bagian 7 |

Ketiganya tidak pernah ditulis ke repositori, karena repositori ini publik.

Dua setelan di `.env.hosting` yang penting dan sudah benar di sana, jangan
diubah balik:

- `APP_DEBUG=false`. Kalau `true`, pesan galat beserta potongan kode ikut
  tampil ke pengunjung.
- `APP_HTTPS=true`. Ini yang menandai cookie sesi sebagai secure. Jangan
  aktif sebelum SSL benar benar jalan, karena cookie secure tidak dikirim
  lewat http dan Anda tidak akan bisa masuk. Kalau SSL-nya belum siap, isi
  `false` dulu, lalu ubah setelah bagian 9 selesai.

Kalau tadi mengunggah lewat Git Version Control, ada satu akibatnya. `.env`
di server sudah Anda isi, padahal berkas yang sama juga dilacak Git. Begitu
menekan Pull, Git menolak jalan karena ada perubahan lokal yang bakal
tertimpa. Beresnya sekali saja, lewat Terminal cPanel:

```bash
cd /home/akun/katamamah && git update-index --skip-worktree .env
```

Setelah itu Git berhenti memperhatikan `.env` di server, dan Pull berikutnya
jalan tanpa mengusik sandi yang sudah Anda isi. Kalau hosting Anda tidak
punya Terminal, unggah lewat zip saja, jangan lewat Git.

## 7. Bikin tabel dan isinya tanpa baris perintah

Lewati bagian ini kalau tadi sudah mengimpor dump dari bagian 3. Basis
datanya sudah lengkap.

Isi basis data dibuat oleh `database/seed.php`, yang memang skrip baris
perintah, padahal hosting bersama sering tidak memberi akses ke sana. Jalan
pintasnya `public/pasang.php`, halaman yang mengerjakan hal yang sama lewat
peramban.

Halaman itu mati total selama `INSTALL_KEY` di `.env` kosong, dan menjawab
404 seperti berkas yang tidak ada. Jadi isi dulu barisnya, lalu buka:

```
https://beacon-its.com/pasang.php
```

Kuncinya dimasukkan lewat kolom di halaman itu, bukan lewat URL, supaya
tidak ikut tercatat di log akses server. Setelah masuk ada dua tombol,
dijalankan berurutan: **Buat tabel**, lalu **Isi data**.

Begitu selesai, **hapus `public/pasang.php`** lewat File Manager, atau
kosongkan lagi `INSTALL_KEY`. Selama keduanya masih ada, siapa pun yang tahu
kuncinya bisa mengosongkan basis data Anda.

## 8. Izin folder

Empat folder ini harus bisa ditulis PHP:

```
storage/logs
storage/cache
storage/uploads
public/uploads/lokasi
```

Folder `755`, berkas `644`. Di cPanel, PHP berjalan sebagai akun Anda
sendiri, jadi `755` sudah cukup. Jangan dibuat `777`.

## 9. Pasang SSL

cPanel, menu **SSL/TLS Status**, tekan **Run AutoSSL**. Tunggu sampai
sertifikat untuk `beacon-its.com` terbit, buka `https://beacon-its.com` untuk
memastikan, baru ubah `APP_HTTPS=true` di `.env`.

Setelah itu nyalakan **Force HTTPS Redirect** di menu Domains, supaya yang
masuk lewat http ikut dialihkan.

## 10. Email

Mailbox `katamamah@beacon-its.com` dan SMTP di port 465 sudah dipakai selama
pengembangan dan sertifikatnya cocok dengan nama host, jadi setelan di atas
bisa langsung dipakai.

Kalau ternyata koneksi ke port 465 ditolak dari dalam server sendiri, ganti
ke SMTP lokal:

```
MAIL_HOST=localhost
MAIL_PORT=25
MAIL_ENCRYPTION=none
MAIL_AUTH=false
```

Satu hal yang perlu diurus terpisah: domain `beacon-its.com` belum punya
catatan MX sendiri. Email keluar tidak terpengaruh, tapi email masuk cuma
mengandalkan cadangan ke catatan A. Kalau nanti ada balasan warga yang tidak
sampai, tambahkan MX lewat **Zone Editor**.

## 11. Uji setelah hidup

- Halaman depan terbuka dan petanya muncul.
- `/peta` dan `/aspirasi` terbuka.
- Daftar akun baru, lalu pastikan email kode verifikasi masuk dan kodenya
  diterima.
- Masuk sebagai petugas, ubah status satu permohonan, lampirkan berkas hasil.
- Unggah foto gedung dari panel admin. Ini sekaligus menguji `gd` dan izin
  folder.
- Buka `https://beacon-its.com/.env`. Harus gagal. Kalau isinya malah
  terbaca, document root-nya belum benar.

## 12. Sebelum dibuka ke umum

- Ganti sandi semua akun contoh. Seeder memakai `tuban12345` untuk semuanya,
  termasuk `superadmin@tcm.test`.
- Hapus `public/pasang.php`, atau kosongkan `INSTALL_KEY` di `.env`.
- Hapus akun contoh yang tidak dipakai.
- Pastikan sandi yang Anda isi di `.env` server tidak pernah ikut
  ter-commit. `.env` di repo harus tetap kosong di dua baris sandi itu.
- Data kantor dan layanan berasal dari OpenStreetMap. Atribusi
  "© OpenStreetMap contributors" di bawah peta harus tetap kelihatan, itu
  syarat lisensi ODbL.
