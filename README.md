# Tuban Civic Map

Situs informasi layanan publik dan antrean online untuk Kabupaten Tuban. Warga
bisa lihat 132 kantor layanan di peta, baca syarat berkas beserta biaya dan
dasar hukumnya, lalu ambil antrean tanpa perlu bikin akun.

Isinya bukan data contoh. Semua kantor diambil dari OpenStreetMap, dan tiap
tarif membawa peraturan yang mendasarinya. Yang belum dikonfirmasi ditandai
"Perlu dikonfirmasi", bukan ditebak.

## Tech stack

| Bagian | Pakai apa |
|---|---|
| Bahasa | PHP 8.0, tanpa framework |
| Pola | MVC bikin sendiri: router, controller, model, service, view |
| Autoload | PSR-4 sendiri di `bootstrap.php`, tanpa Composer |
| Basis data | MariaDB 10.4 atau MySQL 8, lewat PDO prepared statement |
| Tampilan | HTML dan CSS biasa, tanpa Bootstrap atau Tailwind. JavaScript vanilla |
| Peta | SVG digambar sendiri dari GeoJSON OpenStreetMap, tanpa Leaflet |
| Email | PHPMailer 6.9.3, disalin ke `app/Vendor` |
| Server | Apache (XAMPP) atau server bawaan PHP |

## Cara menjalankan

Butuh XAMPP dengan PHP 8.0 ke atas. Tidak ada `composer install` atau
`npm install`.

**1. Clone**

```bash
git clone https://github.com/Ipannnaaa/KataMamah.git
cd KataMamah
```

**2. Install**

```bash
cp .env.example .env
C:\xampp\mysql\bin\mysql.exe -u root < database/schema.sql
C:\xampp\php\php.exe database/seed.php
```

Buka `.env`, isi `APP_URL=http://localhost:8080` dan biarkan `APP_BASE_PATH=`
kosong. Nyalakan juga ekstensi `gd` di `C:\xampp\php\php.ini` dengan menghapus
titik koma pada baris `;extension=gd`, supaya foto gedung yang diunggah ikut
dikecilkan.

**3. Run**

```bash
C:\xampp\php\php.exe -S localhost:8080 -t public
```

Buka <http://localhost:8080>. MySQL harus hidup, kalau mati semua halaman
memberi galat 500.

### Akun demo

Sandinya semua `tuban12345`. Ganti sebelum dipasang di server yang bisa
diakses umum.

| Peran | Email |
|---|---|
| Super admin | `superadmin@tcm.test` |
| Admin kantor | `admin@tcm.test` |
| Petugas | `petugas1@tcm.test` |
| Warga | `warga1@tcm.test` |

## Progress saat ini

**Sudah jalan.** 28 layar untuk empat peran. Beranda, peta kabupaten dan per
kecamatan, halaman kantor lengkap dengan layanan dan syarat berkasnya,
pencarian, ambil antrean tanpa akun dengan unggah berkas, lacak antrean lewat
kode, daftar dan masuk dengan verifikasi email, halaman akun, chat warga dan
petugas, panel petugas, serta panel admin dan super admin dengan rekap dan
penanganan aduan.

**Datanya nyata.** 132 kantor layanan Kabupaten Tuban dari OpenStreetMap,
dengan 631 layanan dan 1.341 syarat berkas.

**Belum ada.** Foto gedung belum dipasang satu pun. 268 tarif masih bertanda
"Perlu dikonfirmasi". Pembayaran masih diverifikasi admin secara manual, belum
ada QRIS atau virtual account. Pemberitahuan lewat WhatsApp juga belum ada.

**Pengujian.** 43 pemeriksaan smoke dan 238 pemeriksaan alur, semuanya lolos.

## Isi folder

```
app/        kode aplikasi (Core, Controllers, Models, Services, Views)
config/     pengaturan: app, mail, map, data kecamatan dan fasilitas
database/   schema.sql dan seed.php
public/     satu-satunya folder yang disajikan server web
storage/    log, cache, dan berkas unggahan warga, di luar web root
tests/      smoke.php, flow.ps1, penampung SMTP untuk uji
```

## Menguji

```bash
C:\xampp\php\php.exe tests/smoke.php
```

```bash
pwsh tests/flow.ps1
```

`smoke.php` tidak butuh basis data. `flow.ps1` menguji aplikasi yang sedang
jalan dari ujung ke ujung, jadi MySQL dan servernya harus hidup dulu. Kalau
dijalankan berkali-kali berturut-turut, bagian 9 bisa kena pembatas laju.
Kosongkan dulu isi `storage/cache/ratelimit`.

## Yang perlu diperhatikan

**Biaya layanan.** Kolom biaya yang dikosongkan berarti belum dikonfirmasi,
sedangkan diisi `0` berarti memang gratis menurut peraturan. Keduanya tampil
berbeda di halaman kantor, jadi jangan menulis 0 untuk tarif yang belum pasti.

**Verifikasi email.** Akun baru tidak bisa masuk sebelum emailnya
diverifikasi, jadi bagian `MAIL_*` di `.env` harus diisi. Untuk mencoba tanpa
SMTP sungguhan, jalankan `tests/smtp-sink.php 1025 600` lalu arahkan
`MAIL_HOST=127.0.0.1` dan `MAIL_PORT=1025`.

**Nama folder berkurung siku.** Kalau proyek ini ditaruh di dalam folder yang
namanya memuat `[` dan `]`, `glob()` membacanya sebagai pola dan mengembalikan
array kosong tanpa error. Karena itu `Config::boot()` memakai `scandir()`.
Hindari `glob()` di proyek ini, dan di PowerShell pakai `-LiteralPath`.

**Keamanan.** Semua query memakai prepared statement, output di-escape, form
POST wajib bertoken CSRF, berkas unggahan disimpan di luar web root dan tipenya
dibaca dengan `finfo`, dan query panel selalu menyaring lokasi dari sesi, bukan
dari input.

## Lisensi

Kode ini MIT, lihat [`LICENSE`](LICENSE). Dua hal punya lisensi sendiri dan
tidak ikut berubah: PHPMailer di `app/Vendor` tetap LGPL-2.1 beserta berkas
lisensinya, dan data lokasi dari OpenStreetMap tetap ODbL 1.0 dengan atribusi
yang wajib tampil di bawah peta.

Spesifikasi awal proyek ada di [BRIEF.md](BRIEF.md), ringkasan pemakaian
sehari-hari di [read.md](read.md).
