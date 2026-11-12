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

1. Buat database bernama `katamamah`. cPanel menyimpannya dengan awalan nama
   akun, jadi nama sebenarnya jadi semacam `akun_katamamah`. Catat nama
   lengkapnya, itu yang dipakai di `.env`.
2. Buat user MySQL baru dengan sandi yang kuat.
3. Di bagian **Add User To Database**, pasangkan user itu ke database tadi
   dan beri **ALL PRIVILEGES**.

## 3. Pindahkan isi basis data dari lokal

Cara paling aman adalah membawa isi basis data yang sudah Anda pakai selama
pengembangan, lengkap dengan 132 kantor, 631 layanan, petugas, dan contoh
antreannya.

Di komputer sendiri:

```bash
C:\xampp\mysql\bin\mysqldump.exe -u root --default-character-set=utf8mb4 --no-tablespaces wia > katamamah.sql
```

Lalu di hosting: buka **phpMyAdmin**, pilih dulu database `akun_katamamah` di
panel kiri, masuk tab **Import**, pilih `katamamah.sql`, Import.

Kalau berkasnya kebesaran untuk diunggah, zip dulu. phpMyAdmin menerima
`.sql.zip` langsung.

Mau mulai dari kosong? Impor `database/schema.sql` dengan cara yang sama,
tabelnya akan terbentuk tapi tanpa isi. Datanya lalu dibuat oleh
`database/seed.php`, yang perlu PHP dari baris perintah. Kalau hosting Anda
tidak punya Terminal, pakai **Cron Jobs**: buat cron sekali jalan dengan
perintah `/usr/local/bin/php /home/akun/katamamah/database/seed.php`, lalu
hapus cron-nya setelah selesai. Hati hati, `seed.php` mengosongkan tabel
lebih dulu, jadi jangan dijalankan di basis data yang sudah ada isinya.

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
tanpa sandi. Yang ada di server, `/home/akun/katamamah/.env`, diubah jadi
seperti ini:

```
APP_NAME="Tuban Civic Map"
APP_SHORT_NAME=TCM
APP_WILAYAH=Tuban

APP_URL=https://beacon-its.com
APP_BASE_PATH=

APP_DEBUG=false
APP_HTTPS=true

DB_HOST=localhost
DB_PORT=3306
DB_NAME=akun_katamamah
DB_USER=akun_katamamah
DB_PASS=sandi basis data

MAIL_HOST=beacon-its.com
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_AUTH=true
MAIL_USERNAME=katamamah@beacon-its.com
MAIL_PASSWORD=sandi mailbox
MAIL_FROM=katamamah@beacon-its.com
MAIL_FROM_NAME="Tuban Civic Map"
MAIL_TIMEOUT=10
MAIL_DEBUG=0
MAIL_CODE_TTL=15
MAIL_CODE_ATTEMPTS=5
```

Tiga baris yang wajib beda dari lokal:

- `APP_DEBUG=false`. Kalau dibiarkan `true`, pesan galat beserta potongan
  kode ikut tampil ke pengunjung.
- `APP_HTTPS=true`. Ini yang menandai cookie sesi sebagai secure. Jangan
  dinyalakan sebelum SSL benar benar aktif, karena cookie secure tidak
  dikirim lewat http dan Anda tidak akan bisa masuk.
- `APP_BASE_PATH=` dibiarkan kosong, karena domain sudah menunjuk langsung ke
  folder `public`.

Dua nilai yang cuma ada di server dan tidak pernah ditulis ke repo:
`DB_PASS` dan `MAIL_PASSWORD`.

Kalau tadi mengunggah lewat Git Version Control, ada satu akibatnya. `.env`
di server sudah Anda ubah, padahal berkas yang sama juga dilacak Git. Begitu
menekan Pull, Git menolak jalan karena ada perubahan lokal yang bakal
tertimpa. Beresnya sekali saja, lewat Terminal cPanel:

```bash
cd /home/akun/katamamah && git update-index --skip-worktree .env
```

Setelah itu Git berhenti memperhatikan `.env` di server, dan Pull berikutnya
jalan tanpa mengusik sandi yang sudah Anda isi. Kalau hosting Anda tidak
punya Terminal, unggah lewat zip saja, jangan lewat Git.

## 7. Izin folder

Empat folder ini harus bisa ditulis PHP:

```
storage/logs
storage/cache
storage/uploads
public/uploads/lokasi
```

Folder `755`, berkas `644`. Di cPanel, PHP berjalan sebagai akun Anda
sendiri, jadi `755` sudah cukup. Jangan dibuat `777`.

## 8. Pasang SSL

cPanel, menu **SSL/TLS Status**, tekan **Run AutoSSL**. Tunggu sampai
sertifikat untuk `beacon-its.com` terbit, buka `https://beacon-its.com` untuk
memastikan, baru ubah `APP_HTTPS=true` di `.env`.

Setelah itu nyalakan **Force HTTPS Redirect** di menu Domains, supaya yang
masuk lewat http ikut dialihkan.

## 9. Email

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

## 10. Uji setelah hidup

- Halaman depan terbuka dan petanya muncul.
- `/peta` dan `/aspirasi` terbuka.
- Daftar akun baru, lalu pastikan email kode verifikasi masuk dan kodenya
  diterima.
- Masuk sebagai petugas, ubah status satu permohonan, lampirkan berkas hasil.
- Unggah foto gedung dari panel admin. Ini sekaligus menguji `gd` dan izin
  folder.
- Buka `https://beacon-its.com/.env`. Harus gagal. Kalau isinya malah
  terbaca, document root-nya belum benar.

## 11. Sebelum dibuka ke umum

- Ganti sandi semua akun contoh. Seeder memakai `tuban12345` untuk semuanya,
  termasuk `superadmin@tcm.test`.
- Hapus akun contoh yang tidak dipakai.
- Pastikan sandi yang Anda isi di `.env` server tidak pernah ikut
  ter-commit. `.env` di repo harus tetap kosong di dua baris sandi itu.
- Data kantor dan layanan berasal dari OpenStreetMap. Atribusi
  "© OpenStreetMap contributors" di bawah peta harus tetap kelihatan, itu
  syarat lisensi ODbL.
