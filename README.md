# Tuban Civic Map

Situs informasi layanan publik dan pemesanan antrean untuk Kabupaten Tuban.
Warga bisa menelusuri 132 kantor layanan lewat peta, membaca syarat berkas
beserta biaya dan dasar hukumnya, lalu mengambil antrean tanpa perlu akun.

---

## Tech stack

| Lapis | Yang dipakai |
|---|---|
| Bahasa | PHP 8.0 — tanpa framework, tanpa Composer |
| Pola | MVC buatan sendiri: router, controller, model, service, view |
| Autoload | PSR-4 sendiri di `bootstrap.php` |
| Basis data | MariaDB 10.4 / MySQL 8 lewat PDO prepared statement |
| Tampilan | HTML + CSS murni (tanpa Bootstrap/Tailwind) dan JavaScript vanilla |
| Peta | SVG digambar sendiri dari GeoJSON OpenStreetMap — tanpa Leaflet maupun Google Maps |
| Email | PHPMailer 6.9.3, disalin ke `app/Vendor` karena tidak memakai Composer |
| Server | Apache (XAMPP) atau server bawaan PHP |

## Cara menjalankan

**1 · Clone**

```bash
git clone https://github.com/Ipannnaaa/KataMamah.git
cd KataMamah
```

**2 · Install** — tidak ada `composer install` atau `npm install`; yang perlu
disiapkan hanya konfigurasi dan basis data.

```bash
cp .env.example .env
C:\xampp\mysql\bin\mysql.exe -u root < database/schema.sql
C:\xampp\php\php.exe database/seed.php
```

Untuk server bawaan PHP, isi `APP_URL=http://localhost:8080` dan biarkan
`APP_BASE_PATH=` kosong di `.env`. Nyalakan juga ekstensi `gd` di
`C:\xampp\php\php.ini` (hapus titik koma pada `;extension=gd`) agar foto
gedung yang diunggah ikut dikecilkan.

**3 · Run**

```bash
C:\xampp\php\php.exe -S localhost:8080 -t public
```

Buka <http://localhost:8080>. Akun demo bersandi `tuban12345`:
`superadmin@tcm.test`, `admin@tcm.test`, `petugas1@tcm.test`, `warga1@tcm.test`.

## Progress saat ini

**Sudah jalan.** Beranda, peta kabupaten dan per kecamatan, halaman kantor
beserta layanan dan syarat berkasnya, pencarian, pengambilan antrean tanpa
akun dengan unggah berkas, pelacakan antrean lewat kode, registrasi dan login
warga dengan verifikasi email, halaman akun, chat warga–petugas, panel petugas,
serta panel admin dan super admin lengkap dengan rekap dan penanganan aduan.
Total 28 layar di empat peran.

**Isi datanya nyata**, bukan contoh: 132 kantor layanan Kabupaten Tuban diambil
dari OpenStreetMap, dengan 631 layanan dan 1.341 syarat berkas yang masing-masing
membawa dasar hukumnya.

**Belum ada.** Foto gedung belum dipasang satu pun; 268 tarif masih bertanda
"Perlu dikonfirmasi" karena belum dikonfirmasi ke kantor terkait; pembayaran
masih diverifikasi admin secara manual, belum ada QRIS atau virtual account;
pemberitahuan lewat WhatsApp belum ada.

**Pengujian.** 43 pemeriksaan smoke dan 238 pemeriksaan alur ujung-ke-ujung,
semuanya lolos. Lihat bagian 6.

---

Penjelasan mendalam tiap bagian ada di bawah ini; spesifikasi awalnya di
[BRIEF.md](BRIEF.md), dan ringkasan pemakaian sehari-hari di [read.md](read.md).

---

## 1. Kebutuhan

| Komponen | Versi | Catatan |
|---|---|---|
| PHP | 8.0+ | diuji dengan PHP 8.0.30 (XAMPP). Tanpa ekstensi di luar bawaan: `pdo_mysql`, `fileinfo`, `json`, `mbstring`, dan `gd` (yang terakhir berkomentar di php.ini XAMPP — lihat 2d) |
| MySQL / MariaDB | MariaDB 10.4+ atau MySQL 8 | **diuji pada MariaDB 10.4.32** bawaan XAMPP. Kolom `JSON` di MariaDB adalah alias `LONGTEXT`; skema dan seluruh query berjalan normal |
| Composer | — | **tidak dipakai.** Autoloader PSR-4 sendiri ada di `bootstrap.php` |

---

## 2. Pemasangan dengan XAMPP

### a. Letakkan proyek

Taruh folder ini di mana saja, lalu arahkan web server ke **`System/public`** —
bukan ke `System/`. Semua folder lain (`app/`, `config/`, `storage/`,
`database/`) harus berada di luar jangkauan web.

**Cara cepat (tanpa vhost):** salin folder `System` ke `C:\xampp\htdocs\wia`,
lalu akses `http://localhost/wia/System/public`.

**Cara yang disarankan (vhost sendiri)** — di
`C:\xampp\apache\conf\extra\httpd-vhosts.conf`:

```apache
<VirtualHost *:80>
    ServerName wia.test
    DocumentRoot "C:/xampp/htdocs/wia/System/public"
    <Directory "C:/xampp/htdocs/wia/System/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Tambahkan `127.0.0.1 wia.test` ke `C:\Windows\System32\drivers\etc\hosts`,
aktifkan `mod_rewrite`, lalu restart Apache.

### b. Buat berkas `.env`

```bash
copy .env.example .env
```

Isi sesuai cara pemasangan:

```ini
# tanpa vhost
APP_URL=http://localhost/wia/System/public
APP_BASE_PATH=wia/System/public

# dengan vhost
APP_URL=http://wia.test
APP_BASE_PATH=
```

`APP_BASE_PATH` adalah sub-folder yang dipotong dari path request. Salah isi di
sini membuat semua route jadi 404.

### c. Impor basis data

```bash
C:\xampp\mysql\bin\mysql.exe -u root < database/schema.sql
C:\xampp\php\php.exe database/seed.php
```

- `schema.sql` membuat database `wia` beserta 14 tabel.
- `seed.php` mengisi **20 kecamatan Kabupaten Tuban**, 132 kantor layanan,
  631 layanan beserta 1.341 syarat berkasnya, 8 akun demo, dan sekitar 45
  antrean contoh yang tersebar 30 hari terakhir. Tambahkan `--fresh` untuk
  menimpa isi yang sudah ada.

**Data mana yang nyata.** Daftar kantor di `config/fasilitas.php` adalah
**133 fasilitas layanan publik sungguhan di Kabupaten Tuban**, disaring dari
247 POI OpenStreetMap di dalam batas kabupaten — Polsek per kecamatan,
Puskesmas, Pengadilan Negeri, Kejaksaan Negeri, kantor pos, pasar, balai desa,
dan dinas-dinas. Warung, ekspedisi swasta, dan papan nama RT/RW dibuang.
Tiap fasilitas ditempatkan ke kecamatannya lewat titik-dalam-poligon terhadap
batas asli di `config/kecamatan.php`.

Yang **asli**: nama, kategori, koordinat, dan alamat jalan (76 dari 133 punya
alamat di OSM), serta 11 nomor telepon.

Yang **belum**, dan masih harus dikonfirmasi ke instansi:

- **Tarif layanan** — 631 layanan di 22 kategori, masing-masing membawa
  **dasar tarifnya** di kolom `services.fee_basis`. Angka hanya diisi bila
  ditetapkan peraturan nasional yang bisa disebut: SKCK Rp30.000 dan tarif
  STNK/TNKB (PP 76/2020), nikah di luar KUA Rp600.000 (PP 48/2014),
  pengecekan sertipikat Rp50.000 (PP 128/2015). Dokumen kependudukan
  bernilai 0 karena UU 24/2013 Pasal 79A memang melarang pungutan.
  Rinciannya: **347 gratis menurut peraturan, 16 berbayar dengan angka
  resmi, 268 bertanda "Perlu dikonfirmasi"** — tarif Perda Kabupaten
  Tuban, panjar perkara per SK ketua pengadilan, atau yang besarnya
  bergantung objek. Kolom `fee` kini boleh `NULL`: **`NULL` berbeda dengan
  `0`**, dan halaman layanan menampilkannya sebagai "Perlu dikonfirmasi",
  bukan "Gratis". Menampilkan keduanya sama pernah membuat halaman ini
  menjanjikan SKCK dan perpanjangan STNK sebagai cuma-cuma.
  Periksa ulang bila peraturan PNBP-nya direvisi.
- **Syarat berkas** — 1.341 baris, dan tiap layanan membawa
  `services.docs_basis`: dari mana daftar berkasnya berasal. Yang punya
  dasar peraturan menyebutkannya (Permendagri 108/2019 untuk adminduk,
  Perkap 18/2014 untuk SKCK, PMA 20/2019 untuk nikah, Perpol 7/2021 untuk
  kendaraan, PP 24/1997 dan PP 128/2015 untuk pertanahan); sisanya
  mengaku **"susunan umum; tiap kantor dapat meminta berkas tambahan"**.
  850 dari 1.341 berkas (63%) juga punya catatan praktis di kolom `note`,
  mis. "Bawa dokumen aslinya untuk dicocokkan petugas". Catatan sengaja
  dibatasi pada hal yang pasti benar di mana pun — masa berlaku surat
  pengantar dan jumlah rangkap berbeda antar kantor, jadi tidak dicantumkan.
  Daftar berkas yang salah membuat warga bolak-balik, jadi lebih baik
  terus terang belum dikonfirmasi daripada terlihat pasti padahal tidak.
- **Telepon dan email** — hanya diisi bila OSM punya; sisanya `NULL`, bukan
  nomor karangan. Kolom kosong lebih aman daripada nomor palsu di kantor
  pemerintah sungguhan: ada orang yang benar-benar meneleponnya.
- **10 kantor bertanda `perkiraan`** (kolom `locations.data_source`) — sembilan
  kecamatan yang kantornya belum terpetakan di OSM, ditambah Dukcapil Tuban
  yang dipakai seluruh skenario contoh. Titiknya hanya tengah wilayah
  kecamatan, bukan alamat sebenarnya, dan halaman kantornya menyatakan itu
  secara terbuka.
- **Foto gedung** sudah bisa diunggah lewat panel admin, tetapi belum ada
  satu pun yang terpasang: tidak ada sumber terbuka yang bisa dipakai, jadi
  fotonya harus dipotret atau diminta ke instansinya.

Cara memperbarui: ambil ulang POI dari Overpass, saring, lalu tulis ulang
`config/fasilitas.php`. Tidak ada kode yang perlu diubah — `database/seed.php`
membaca berkas itu apa adanya.

**Batas wilayah dan batas kecamatan: dua-duanya data asli.** Garis pantai dan
batas luar kabupaten pada `config/map.php` berasal dari **OpenStreetMap
relasi 9676649**, diambil lewat Nominatim lalu disederhanakan Douglas–Peucker
dari 9.103 titik menjadi 147 titik (toleransi 0,0029°). Batas antar kecamatan
di `config/kecamatan.php` memakai relasi `admin_level=6` lewat Overpass (20
relasi, 828 titik), dan batas desa di `config/desa.php` memakai
`admin_level=7` (163 relasi, 2.703 titik; enam kecamatan belum lengkap).
Data © kontributor OpenStreetMap, lisensi
**ODbL 1.0** — atribusinya sudah tampil di bawah peta dan wajib dipertahankan
bila aplikasi ini dipublikasikan. Empat poligon kecil lepas pantai tidak
disertakan.

**Batas kecamatan juga data asli.** `config/kecamatan.php` berisi poligon 20
kecamatan dari OpenStreetMap (relasi `admin_level=6` di dalam Kabupaten
Tuban), diambil lewat Overpass API; potongan way disambung menjadi cincin
tertutup lalu disederhanakan ke sekitar 40 titik per kecamatan (dari 454–3.450
titik asli). Sama-sama ODbL dan wajib atribusi. Bila berkas itu dikosongkan,
`MapScene` otomatis kembali memakai poligon Thiessen sebagai cadangan.

**Batas desa/kelurahan** ada di `config/desa.php`, juga dari OpenStreetMap
(`admin_level=7`), dikelompokkan ke kecamatan induknya lewat uji
titik-dalam-poligon. Garisnya muncul sebagai tingkat ketiga ketika sebuah
kecamatan dibuka. **Cakupannya belum lengkap**: OSM baru memuat 163 dari
sekitar 311 desa/kelurahan. Sepuluh kecamatan sudah utuh (Bancar, Grabagan,
Jenu, Kerek, Merakurak, Palang, Semanding, Tambakboyo, Tuban, Widang sebagian);
enam kecamatan belum punya data sama sekali — Kenduruan, Montong, Parengan,
Rengel, Senori, Singgahan — dan tetap tampil normal, hanya tanpa garis desa.

Yang masih perkiraan hanyalah **koordinat kantor layanan**: titiknya ditata
agar mudah dibaca, bukan posisi sebenarnya — tombol Go · Google Maps yang
menunjuk koordinat asli.

Scene peta di-cache di `storage/cache/data` (lihat `Core\Cache`) karena
perhitungan poligonnya berat: 987 ms menjadi 140 ms. Cache dikosongkan
otomatis setiap kali `seed.php` dijalankan.

`database/seed.sql` dan `database/seed-demo.php` adalah seeder lama berisi
contoh Kulon Progo. Keduanya sudah tidak dipakai dan boleh dihapus.

### d. Izin folder

`storage/logs`, `storage/cache`, `storage/uploads`, dan
`public/uploads/lokasi` harus bisa ditulis PHP. Di Windows biasanya sudah
otomatis.

Mengimpor ulang `schema.sql` mengosongkan tabel `queue_documents` tetapi
tidak menyentuh berkasnya, jadi `storage/uploads` menyimpan sisa dari tiap
seed sebelumnya. Kosongkan isinya bila ingin bersih — folder dan
`.gitkeep`-nya dipertahankan:

```bash
find storage/uploads -type f ! -name .gitkeep -delete
```

Dua folder unggahan itu berbeda maksud dan tidak boleh ditukar:

| | isi | letak | disajikan oleh |
|---|---|---|---|
| `storage/uploads` | berkas persyaratan milik warga | **di luar** web root | `FileController`, setelah hak akses diperiksa |
| `public/uploads/lokasi` | foto gedung kantor | **di dalam** web root | server web langsung |

Foto gedung memang untuk dilihat siapa saja, jadi melewatkannya ke PHP tiap
kali halaman dibuka hanya memperlambat tanpa menambah keamanan. Penggantinya:
tipe berkas dibaca dari isinya (`finfo`) lalu dicocokkan dengan `getimagesize`,
nama berkas diacak dan ekstensinya diambil dari daftar yang diizinkan — jadi
tidak ada cara menaruh `.php` di sana — dan `public/uploads/.htaccess`
mematikan seluruh handler skrip sekaligus menolak berkas selain
`.jpg/.jpeg/.png/.webp`.

**Ekstensi `gd` wajib dinyalakan** — di XAMPP bawaan baris `;extension=gd`
masih berkomentar. Hapus titik komanya di `C:\xampp\php\php.ini`, lalu
jalankan ulang servernya. Dengan `gd` aktif, tiap foto yang diunggah
dikodekan ulang: lebarnya dipangkas ke 1600 px dan apa pun yang menumpang di
dalam berkas — EXIF, komentar, data yang disisipkan setelah penanda akhir
gambar — ikut hilang. Bagian tembus pandang PNG dan WebP tetap utuh.

Tanpa `gd` aplikasi tetap jalan: foto disimpan apa adanya setelah lolos
pemeriksaan tipe dan ukuran. Yang hilang hanya pengecilan dan pembersihan
isinya, jadi berkas 3 MB dari kamera ponsel akan dikirim utuh ke tiap
pengunjung.

### e. Email verifikasi (SMTP)

Akun baru **tidak bisa masuk sebelum emailnya diverifikasi**, jadi pengiriman
email bukan pelengkap — tanpa SMTP yang jalan, pendaftaran warga buntu.

Pengirimannya memakai **PHPMailer 6.9.3**, disalin apa adanya ke
`app/Vendor/PHPMailer` karena proyek ini tidak memakai Composer; autoloader di
`bootstrap.php` memetakan namespace `PHPMailer\PHPMailer\` ke folder itu.
Lisensinya LGPL-2.1 dan berkas `LICENSE`-nya ikut disalin — jangan dihapus.

Isi bagian `MAIL_*` di `.env` (contohnya ada di `.env.example`):

| | Gmail | Penampung uji lokal |
|---|---|---|
| `MAIL_HOST` | `smtp.gmail.com` | `127.0.0.1` |
| `MAIL_PORT` | `587` | `1025` |
| `MAIL_ENCRYPTION` | `tls` | `none` |
| `MAIL_AUTH` | `true` | `false` |
| `MAIL_USERNAME` | alamat Gmail Anda | kosong |
| `MAIL_PASSWORD` | **App Password** 16 huruf, bukan sandi akun | kosong |

Tidak ada "driver log" atau penampung palsu di dalam aplikasi. Email yang
hanya tercatat di berkas log membuat fitur ini tampak jalan padahal warga
tidak pernah menerima apa pun. Bila `MAIL_HOST` kosong, aplikasi
mengatakannya terus terang di halaman verifikasi dan di pesan galat.

**Untuk pengembangan dan pengujian** tersedia penampung SMTP di
`tests/smtp-sink.php`: server SMTP sungguhan yang sangat sederhana, menulis
tiap email yang diterimanya ke `storage/logs/smtp-sink/*.eml`. Yang
dipalsukan hanya servernya, bukan pengirimnya — percakapan SMTP-nya tetap
diuji. `tests/flow.ps1` menyalakan dan mematikannya sendiri;
`tests/read-sink.php` membaca email terakhir (`tautan`, `kepada`, `isi`,
`jumlah`).

```bash
C:\xampp\php\php.exe tests/smtp-sink.php 1025 600
```

**Akun petugas dan admin** dibuat seeder dengan `is_verified = 1`. Akun staf
yang ditambahkan belakangan harus mengikuti, kalau tidak pemiliknya tidak
bisa masuk sama sekali.

### f. Tanpa Apache (opsional)

Untuk mencoba cepat, server bawaan PHP sudah cukup:

```bash
C:\xampp\php\php.exe -S localhost:8080 -t public public/index.php
```

Isi `.env` dengan `APP_URL=http://localhost:8080` dan `APP_BASE_PATH=` kosong.
`public/index.php` mengenali SAPI `cli-server` dan menyajikan berkas statis
sendiri, sesuatu yang di Apache ditangani `.htaccess`.

---

## 3. Akun demo

Sandi semuanya **`tuban12345`** — hanya untuk lokal. Hapus atau ganti sebelum
dipasang di server nyata.

| Peran | Email | Masuk ke |
|---|---|---|
| Super admin | `superadmin@tcm.test` | `/admin` |
| Admin lokasi (Dukcapil Tuban) | `admin@tcm.test` | `/admin` |
| Petugas | `petugas1@tcm.test` · `petugas2@tcm.test` · `petugas3@tcm.test` | `/petugas` |
| Warga | `warga1@tcm.test` · `warga2@tcm.test` · `warga3@tcm.test` | `/akun` |

Kode antrean berawalan `TCM-` dan diacak setiap kali seeder dijalankan; ambil
satu contohnya lewat smart search atau dari panel admin. Kode lama berawalan
`WIA-` tetap dikenali pencarian.

**Super admin tidak terikat satu kantor** — `users.location_id` NULL. Semua
halaman panelnya karena itu mencakup seluruh kabupaten, sedangkan admin
lokasi hanya melihat kantornya. Lingkup itu dihitung sekali di
`AdminController::scope()`: NULL berarti tanpa batas lokasi, dan seluruh
query rekap serta daftar menerimanya sebagai `?int`. Jangan mengecornya ke
`0` — nilai itu akan dicari sebagai `location_id = 0` dan panelnya kembali
kosong di semua halaman.

---

## 4. Struktur

```
System/
├─ public/              # satu-satunya folder yang diekspos web server
│  ├─ index.php         # front controller
│  ├─ .htaccess         # rewrite ke index.php
│  ├─ assets/css|js|img
│  └─ uploads/lokasi/    # foto gedung (publik, .htaccess menolak skrip)
├─ app/
│  ├─ Vendor/PHPMailer/ # PHPMailer 6.9.3 (LGPL-2.1), disalin tanpa Composer
│  ├─ Core/             # Router, Request, Response, View, DB, Auth, Csrf,
│  │                    # Validator, Session, Config, ErrorHandler,
│  │                    # RateLimiter, CodeGenerator, Controller, helpers
│  ├─ Controllers/      # Landing, Home (peta), Page, Location, Search, Auth,
│  │                    # Verification, Track, Queue, Account, Officer,
│  │                    # Admin, Api, File
│  ├─ Models/           # District, Location, Service, Queue, Message,
│  │                    # Payment, User, EmailVerification  (tanpa ORM)
│  ├─ Services/         # QueueService, RecapService, ChatService,
│  │                    # UploadService, PhotoService, MailService,
│  │                    # VerificationService, SearchService, MapScene
│  ├─ Middleware/       # Auth, Role, Csrf
│  └─ Views/            # layouts, partials, dan view per halaman
├─ config/              # app.php, mail.php, database.php, roles.php, map.php
├─ routes/web.php       # seluruh route + pendaftaran middleware
├─ database/            # schema.sql, seed.sql, seed-demo.php
├─ storage/             # logs, cache, uploads (berkas warga, di luar web root)
├─ tests/              # smoke.php, flow.ps1, smtp-sink.php, read-sink.php
├─ .env.example
└─ BRIEF.md             # spesifikasi lengkap
```

Alur satu request: `public/index.php` → `Session::start()` →
`Request::capture()` → `routes/web.php` → middleware (`csrf` → `auth` → `role`)
→ controller → `View::render()` → `Response::send()`.

---

## 5. Yang sudah jalan dan yang belum

**Sudah ada dan terhubung:**

- **Halaman pembuka** di `/` bergaya ilustrasi: langit bergradasi, panorama
  Kulon Progo sebagai SVG (mercusuar Glagah, Menoreh, bandara, masjid, kantor
  bupati, pasar), dan smart search di tengah. Peta pindah ke `/peta`.
- **Peta isometrik dua tingkat, buatan sendiri.** Tingkat pertama menampilkan
  Kabupaten Tuban sebagai **satu pulau** yang mengapung di laut: siluetnya
  memakai batas wilayah asli dari OpenStreetMap, diperbesar
  otomatis sampai memenuhi kanvas. Khusus untuk digambar, garis pantainya
  dihaluskan lebih dulu lewat setelan di `config/map.php`:
  `shore_simplify` (15) membuang tonjolan di bawah 15 px — meander Bengawan
  Solo di selatan yang pada ukuran tampil hanya terbaca sebagai duri;
  lalu `shore_smooth` (3) membulatkan sudutnya (Chaikin). Tersedia juga
  `shore_relax` (pelemasan Laplacian) yang melandaikan teluk dan tanjung
  besar — sengaja dimatikan (0) karena pada 6 siluetnya jadi terlalu bulat
  dan tidak lagi terbaca sebagai Tuban; naikkan bila ingin lebih membulat.
  Data batas aslinya sendiri tidak pernah ikut berubah, dan
  batas kecamatan/desa dikurung (`clipPath`) ke pulau yang tergambar supaya
  garisnya tidak menyeberang ke pasir. Pulau digambar berlapis (air dangkal,
  bayangan, batuan, tanah, pasir, rumput) supaya terasa tebal. Pulau dibagi
  **20 bidang kecamatan dengan garis batas**, memakai batas asli tiap
  kecamatan dari OpenStreetMap (poligon Thiessen hanya dipakai bila data
  itu tidak ada).
  Penanda kecamatan duduk di **titik berat bidangnya**, bukan di koordinat
  pusat mentah. Memilih satu kecamatan memperbesar peta **dan memusatkannya**
  ke bidang itu — tingkat perbesarannya dihitung per kecamatan dari luas
  bidangnya — lalu memunculkan kantor-kantor layanannya sebagai **pin ala
  peta dengan ikon per jenis layanan** — rumah
  sakit, kepolisian, pemadam kebakaran, pemerintahan, dukcapil, samsat, pasar,
  perizinan, pendidikan. Mengarahkan kursor ke sebuah pin memunculkan kartu
  pratinjau berisi ikon gedung, nama, alamat, daftar layanan, sisa kuota,
  tombol **Go · Google Maps** ke koordinat sebenarnya, dan tautan ke halaman
  lengkap. Bersamaan dengan itu muncul **tingkat ketiga**: batas
  desa/kelurahan di dalam kecamatan tersebut, sebagai garis tipis samar.
  Nama desa disembunyikan dan baru tampil lewat tombol **Nama desa** di
  pojok peta, karena 19 label sekaligus membuat satu kecamatan sulit dibaca. Pin **dijamin berada di dalam garis wilayah kecamatannya**: bidang
  yang lega ditata melingkar, bidang sempit atau memanjang disebar mengikuti
  bentuknya (farthest-point sampling di dalam poligon). Ukuran pin tetap
  38 px di layar berapa pun perbesarannya, seperti penanda peta sungguhan.
  Di bawah peta ada legenda warna dan ikon. Tanpa penyedia tile,
  tanpa API key, dan tetap tergambar penuh tanpa JavaScript — setiap penanda
  adalah tautan biasa. Bentuk pulau, sebaran, tingkat perbesaran, warna, dan
  ikon per kategori disetel lewat `config/map.php` dan `Services/Icons`.
- **Smart search** satu kotak untuk layanan, kantor, kecamatan, dan kode
  antrean sekaligus; saran muncul saat mengetik, dan `/cari` tetap bekerja
  tanpa JavaScript.
- **Antrean tanpa akun**: warga mengisi identitas di formulir dan langsung
  menerima kode. Sistem membuat akun tamu tanpa sandi yang otomatis tergabung
  bila orang yang sama mendaftar dengan email itu.
- Router dengan parameter `{slug}`, middleware CSRF / auth / peran, 404 & 405.
- **Unggah berkas** dari warga (syarat antrean) dan dari admin (foto
  gedung) memakai kontrol berkas asli peramban yang ditata lewat
  `::file-selector-button`, bukan label palsu + JavaScript: formulir
  antrean harus tetap bisa dipakai tanpa JS, dan teks tombolnya memang
  milik peramban sehingga ikut bahasa penggunanya.
- Autentikasi sesi: login, registrasi warga, logout, rate limit 5 percobaan
  per 15 menit, pencatatan ke `login_attempts`, rehash sandi otomatis.
- **Mengganti email** lewat profil tidak langsung berlaku. Alamat baru
  disimpan di `users.pending_email` dan tautan konfirmasinya dikirim ke
  sana; alamat lama tetap menjadi alamat akun sampai tautan itu dibuka.
  Tanpa itu, akun tetap bertanda terverifikasi untuk alamat yang belum
  pernah dibuktikan, dan satu salah ketik memutus satu-satunya jalan masuk
  pemiliknya.
- **Verifikasi email wajib** lewat PHPMailer: akun baru tidak bisa masuk
  sampai tautannya dibuka. Yang disimpan di basis data hanya SHA-256 token,
  bukan tokennya — basis data yang bocor tidak sekaligus menyerahkan tautan
  yang masih berlaku. Tautan berlaku 24 jam, sekali pakai, dan meminta yang
  baru membatalkan yang lama. Membukanya tidak langsung memberi sesi: tautan
  itu melintasi kotak surat, jadi sandinya diminta sekali lagi. Halaman kirim
  ulang menjawab sama persis untuk alamat yang terdaftar dan yang tidak,
  supaya tidak bisa dipakai memeriksa siapa yang punya akun di sini.
- Pengambilan antrean lengkap: kuota harian dikunci dengan `SELECT … FOR UPDATE`,
  nomor urut per lokasi per tanggal, kode `WIA-XXXXX`, unggah berkas, tagihan
  otomatis bila layanan berbiaya.
- **Isian antrean diperiksa di sisi server, bukan hanya di formulir.** Hari
  tutup, sesi di luar enam pilihan, layanan milik kantor lain, dan lampiran
  atas nama syarat layanan lain semuanya ditolak. Formulir boleh menandai
  dan menonaktifkan pilihan, tetapi permintaan POST bisa menyebut apa saja.
- **`payment_status` punya nilai `belum_pasti`** untuk layanan yang tarifnya
  `NULL`. Sebelumnya tarif yang belum dikonfirmasi jatuh ke `tidak_perlu`,
  dan warga diberi tahu antreannya tidak perlu bayar — janji yang tidak bisa
  ditepati aplikasi ini.
- Mesin status dengan **empat** lapis pemeriksaan: transisi sah, peran
  berwenang, kepemilikan antrean, dan status lama ikut jadi syarat
  `UPDATE`-nya. Yang terakhir mencegah dua petugas merebut satu antrean —
  `$queue` dibaca saat halaman dibuka, dan yang menekan "Terima"
  belakangan dulu menimpa `officer_id` milik yang pertama tanpa pesan
  apa pun. Semua perubahan tercatat di `queue_status_logs`.
- Papan antrean petugas (terima, ubah status, catatan penanganan), panel admin
  (landmark, layanan + syarat berkas, foto gedung, antrean, assign petugas,
  pembayaran, rekap, export CSV), halaman warga, chat polling, cek antrean
  publik dengan nama dimasking.
- **Foto gedung per kantor**: diunggah admin beserta keterangan sumbernya,
  tampil di halaman kantor dan di kartu pratinjau peta. Sumber foto wajib
  diisi — gambar gedung pemerintah yang tak jelas asalnya sama tidak bisa
  diperiksanya seperti tarif tanpa dasar hukum. Admin kecamatan hanya bisa
  mengubah foto kantor di wilayahnya sendiri.
- Enam endpoint JSON di `/api/*`, termasuk GeoJSON landmark untuk peta.
- **Transisi antar halaman** lewat View Transitions lintas dokumen
  (`@view-transition` di `anim.css`). Kepala, menu samping panel, dan kaki
  halaman diberi `view-transition-name` sehingga diam di tempat,
  sedangkan isi halaman **digeser menyamping**: yang lama keluar ke kiri
  (`--dur-page-out`, 380 ms), yang baru masuk dari kanan
  (`--dur-page-in`, 760 ms, dengan jeda `--dur-page-gap` 130 ms). Yang
  keluar sengaja lebih cepat daripada yang masuk supaya tidak terasa dua
  lembar bertabrakan. Ketiga angkanya token tersendiri, bukan `--dur`
  umum, karena tempo perpindahan halaman perlu disetel terpisah dari
  animasi komponen.

  Arahnya mengikuti jenis navigasi: maju menggeser ke kiri, tombol
  kembali membalikkannya. Penentunya skrip inline di `<head>`
  (`partials/arah-transisi.php`), **bukan** `ui.js` — event `pagereveal`
  menyala sangat awal di dokumen baru dan kerap mendahului modul yang
  dimuat di akhir `<body>`, sehingga arahnya telat terpasang. Pada
  navigasi kembali yang dipulihkan dari bfcache, peramban tidak
  menjalankan transisi sama sekali, jadi tidak ada yang perlu dibalik.

  Peramban tanpa dukungan (Firefox, Safari lama) mendapat animasi masuk
  biasa, dipicu kelas `tanpa-transisi` yang dipasang skrip `<head>` yang
  sama. Versi pertama memakai `:active-view-transition` untuk itu dan
  **salah**: pseudo-class-nya hilang begitu transisi selesai, aturan
  animasinya kembali berlaku saat itu juga, dan isi halaman bergerak
  untuk kedua kalinya — terlihat seperti halaman berkedip dua kali.
- **Wordmark "Tuban Civic Map" beranimasi** di halaman pembuka
  (`app/Views/partials/wordmark.php`). "Civic Map" adalah nama tetap
  produknya, bagian depannya mengikuti `APP_WILAYAH`, jadi pindah kabupaten
  tidak perlu menyentuh markup. Pin ditancapkan, riaknya menyebar, nama
  kabupaten muncul huruf demi huruf, lalu sebuah rute tipis ditarik di
  bawahnya, ditahan, dan semuanya luruh dalam urutan yang sama sebelum
  siklus 6 detik berikutnya. Semua bagian memakai satu durasi siklus yang
  sama — itu syaratnya agar pentahapan tetap utuh, karena
  `animation-delay` hanya berlaku pada putaran pertama. Menyentuh
  wordmark menghentikannya sementara. Pembaca layar tetap menerima satu
  nama utuh lewat `aria-label`.
- **Aduan per fasilitas.** Tiap halaman kantor punya form aduan: data tidak
  cocok, pelayanan tidak sesuai, fasilitas rusak, atau lainnya. Sengaja
  **tanpa perlu akun** — yang menemukan data salah biasanya pengunjung yang
  baru pertama datang ke sana, dan memaksa mereka mendaftar dulu berarti
  aduan itu tidak pernah masuk; pengamannya pembatas laju 5 aduan per 15
  menit per IP dan panjang minimal 15 huruf. Kontak boleh dikosongkan.
  Admin lokasi melihat aduan kantornya di `/admin/aduan`, superadmin
  melihat seluruh kabupaten, dan statusnya bergerak baru → ditinjau →
  ditindaklanjuti → selesai / ditolak dengan catatan admin.
- **Legenda peta sekaligus penyaring.** Menekan satu jenis fasilitas
  memunculkan seluruh fasilitas jenis itu di satu kabupaten sekaligus —
  pertanyaannya bukan "apa saja di kecamatan ini" melainkan "di mana saja
  puskesmas". Mode ini dan perbesaran per kecamatan saling meniadakan,
  karena keduanya menentukan pin mana yang terlihat.
- **Layar antara "Mengalihkan…"** setelah aksi berhasil: selesai daftar
  antrean, login, dan registrasi. Tujuannya disimpan di sesi, bukan di
  query string, jadi halaman ini tidak bisa dipakai melempar pengunjung ke
  alamat luar; membuka `/mengalihkan` tanpa aksi sebelumnya hanya kembali
  ke halaman depan. Pola POST → redirect → GET tetap utuh karena
  `/mengalihkan` adalah GET biasa. Perpindahannya memakai
  `<meta http-equiv="refresh">` sehingga tetap bekerja tanpa JavaScript,
  dan bilah tunggunya mengambil durasi dari `app.redirect_delay` (2 detik)
  sehingga panjang bilah selalu sama dengan sisa waktu sebenarnya.
  `Session::keepFlash()` meneruskan pesan sukses dan kode antrean melewati
  layar ini supaya tidak habis di tengah jalan. Tampilannya kartu yang
  muncul seperti popup, memuat ulang wordmark Civic Map dengan siklus
  dipercepat jadi 4 detik — dalam 2 detik yang terlihat persis bagian
  awalnya, pin ditancapkan lalu nama tersusun, sehingga jedanya terasa
  seperti aplikasi sedang menyiapkan sesuatu.
- **Halaman error untuk 400, 403, 404, 405, 419, 429, 500, dan 503.**
  Teksnya di `config/errors.php` dan dibaca lewat `error_info()`, jadi
  judul `<title>`, judul halaman, dan keterangannya selalu sama. Pesan
  spesifik dari controller (mis. "Antrean tidak ada di lokasi Anda")
  tampil sebagai keterangan tambahan. Kode yang tidak terdaftar jatuh ke
  entri `default`, dan `ErrorHandler` kini memakai halaman yang sama untuk
  500 — bukan lagi HTML darurat.

**Belum dikerjakan:**

- **Polish CSS lanjutan.** `anim.css` kini berisi sebelas bagian: token
  easing/durasi, `prefers-reduced-motion`, transisi antar halaman,
  wordmark, dan halaman error. Tiga bagian lama masih menganggur karena
  elemennya belum ada — `.reveal` (tidak dipakai view mana pun),
  `.typing` (indikator mengetik belum dibuat), dan `.is-loading`
  (semua halaman dirender server, tidak ada state memuat). Dua bagian
  lain, `.map-popup` dan `.map-panel`, adalah sisa peta MapLibre lama
  dan sudah tidak punya elemen sama sekali.
- **Verifikasi email** belum ada. Kolom `users.is_verified` sudah tersedia dan
  akun tamu dibuat dengan nilai 0, jadi tinggal menambahkan pengiriman tautan
  verifikasi — diputuskan memakai **PHPMailer**. Karena proyek ini tanpa
  Composer, pustakanya perlu ditaruh manual dan autoloader di
  `bootstrap.php` diajari namespace `PHPMailer\PHPMailer`. Sampai itu
  ada, siapa pun bisa memakai email orang lain saat mengambil antrean
  sebagai tamu.
- **Klien yang tidak mengikuti `<meta http-equiv="refresh">`** berhenti di
  layar "Mengalihkan…" dan tidak pernah sampai ke tujuan. Peramban
  mengikutinya, tetapi skrip seperti `Invoke-WebRequest` dan sebagian
  crawler tidak — `tests/flow.ps1` karena itu punya fungsi `Follow`.
- Verifikasi berkas oleh admin baru tersimpan sebagai kolom `verified`, belum
  ada tombolnya.
- Pembayaran masih verifikasi manual admin; integrasi QRIS/VA belum ada
  (tabel `payments` sudah siap).
- Email yang dikirim baru satu jenis: tautan verifikasi. Pemberitahuan
  perubahan status antrean, pengingat jadwal, dan WhatsApp belum ada.
- SSE untuk chat dan unit test di luar `tests/smoke.php` juga belum ada.

---

## 6. Memeriksa pekerjaan

**Tanpa basis data** — 42 pemeriksaan: routing, render view, escaping,
generator kode, validator, CSRF, helper, rate limiter.

```bash
C:\xampp\php\php.exe tests/smoke.php
```

**Dari ujung ke ujung** — 90 pemeriksaan lewat HTTP terhadap aplikasi yang
benar-benar berjalan. Butuh MySQL hidup, data sudah diimpor, dan server
menyala (langkah 2e).

```bash
pwsh tests/flow.ps1
```

Yang dicakup `flow.ps1`: login warga/petugas/admin, penolakan sandi salah,
halaman lokasi dan syarat berkas, pengambilan antrean **termasuk unggah PDF**,
penolakan berkas wajib yang kosong, penolakan token CSRF palsu, terbitnya kode
pemesanan, cek antrean publik beserta masking nama, penolakan peran
(warga → `/petugas` dan `/admin` = 403), petugas menerima antrean, penolakan
petugas lain atas antrean yang bukan miliknya, penolakan transisi status tak
sah **beserta bukti status tidak berubah**, penyelesaian antrean, chat dua arah
plus penolakan pihak ketiga, panel admin, rekap, export CSV, kontrol akses
berkas unggahan, dan dua endpoint API. Ditambah: pengambilan antrean sebagai
tamu beserta penolakan identitas kosong, klaim akun lewat email tamu yang sama,
smart search untuk keempat jenis hasil, dan halaman pembuka serta peta
isometrik yang tetap utuh tanpa JavaScript. Terakhir: unggah foto gedung
lewat panel admin — penolakan berkas non-gambar, penolakan unggahan tanpa
keterangan sumber, penolakan admin kecamatan lain, foto yang tampil di
halaman kantor beserta sumbernya, penghapusannya kembali, serta pengecilan
foto 3000 px ke 1600 px berikut hilangnya muatan yang menumpang di dalam
berkas gambar. Dan terakhir **verifikasi email dari ujung ke ujung**: email
yang benar-benar melintasi SMTP ke penampung uji, penolakan masuk sebelum
terverifikasi, tautan sekali pakai, token karangan, serta jawaban kirim
ulang yang sama persis untuk alamat terdaftar dan tidak terdaftar. Dua
bagian terakhir menjaga agar panel admin tidak merusak data saat menyimpan
ulang layanan (tarif yang belum dikonfirmasi, dasar hukum, catatan tiap
berkas) dan agar input unggah berkas tetap bergaya sendiri. Dua bagian
terakhir lagi: panel superadmin harus terisi di semua halaman, dan kategori
landmark tidak boleh berubah sendiri saat disimpan ulang. Dua yang paling
akhir: antrean tidak bisa direbut petugas lain dari salinan halaman yang
sudah basi, dan penggantian alamat email lewat profil harus dikonfirmasi
dulu di alamat barunya. Tiga bagian penutup menguji alur antrean dari sisi
permintaan POST, bukan tampilan: hari tutup, sesi di luar daftar, lampiran
atas nama syarat layanan lain, berkas yang tertinggal setelah penolakan,
dan tarif yang belum pasti.

Menjalankan `flow.ps1` berkali-kali berturut-turut akan menabrak pembatas
laju `/cek-antrean` (10 percobaan per 10 menit) dan tiga pemeriksaan di
bagian 9 ikut gagal. Itu bukan regresi; kosongkan dulu:

```powershell
Remove-Item -LiteralPath "C:\Users\ivanm\OneDrive\Desktop\[WIA]\System\storage\cache\ratelimit\*.json"
```

Status terakhir: **smoke 43/43, flow 238/238, lint 104 berkas proyek 0 error**
(di luar 3 berkas PHPMailer, yang juga bersih), dengan 20/20 penanda
kecamatan dan 84/84 pin kantor berada di dalam bidangnya, pada
PHP 8.0.30 + MariaDB 10.4.32.

Periksa sintaks seluruh berkas:

```powershell
Get-ChildItem -LiteralPath . -Recurse -Filter *.php | ForEach-Object { C:\xampp\php\php.exe -l $_.FullName }
```

**Yang masih belum diuji otomatis:** perilaku JavaScript di browser (peta,
polling chat, filter berkas per layanan) — sudah dicek manual sekali, belum ada
tes otomatisnya; serta kunci kuota `FOR UPDATE` di bawah beban bersamaan.

---

## 7. Catatan teknis

- **Nama folder bertanda kurung siku.** Folder proyek ini bernama `[WIA]`, dan
  `glob()` menafsirkan `[WIA]` sebagai character class sehingga mengembalikan
  array kosong tanpa error. Karena itu `Config::boot()` memakai `scandir()`.
  Hindari `glob()` di seluruh proyek ini. Hal yang sama berlaku di PowerShell:
  pakai `-LiteralPath`, bukan `-Path`, untuk menunjuk folder ini.
- **Halaman publik tidak boleh menampilkan nama pelaku.** Partial `timeline`
  menerima `showActor`; halaman cek antrean mengirim `false` supaya riwayat
  status tidak membocorkan nama yang sudah dimasking di kartu hasil.
- **Aksi yang gagal tetapi bukan soal isian form** (transisi status tak sah,
  antrean milik petugas lain) memakai `Controller::failure()` agar alasannya
  tampil sebagai flash. `backWithErrors()` hanya untuk form dengan field yang
  benar-benar dirender.
- **Keamanan.** Semua query memakai prepared statement dengan
  `PDO::ATTR_EMULATE_PREPARES => false`; output di-escape lewat `e()`; form POST
  wajib bertoken CSRF; query panel selalu memfilter `location_id` dari sesi,
  bukan dari input; berkas unggahan disimpan di luar web root dan disajikan
  `FileController` setelah hak akses diperiksa; tipe berkas dibaca dari isinya
  dengan `finfo`, bukan dari ekstensi.
- **Zona waktu** diset `Asia/Jakarta` di `config/app.php`.
- Kode antrean memakai Crockford base32 tanpa huruf I, L, O, dan U agar tidak
  tertukar saat didikte.

---

## 8. Lisensi

Kode proyek ini berlisensi **MIT** — lihat [`LICENSE`](LICENSE). Siapa pun boleh
memakai, mengubah, dan mendistribusikannya, asalkan pemberitahuan hak ciptanya
ikut disertakan.

MIT itu berlaku untuk kode yang ditulis di proyek ini saja. Tiga hal di dalam
repo ini punya lisensinya sendiri dan **tidak** berubah karena ada berkas
`LICENSE` di akar:

- **PHPMailer** di `app/Vendor/PHPMailer` berlisensi **LGPL-2.1**, dan berkas
  `LICENSE`-nya ikut disalin di folder itu — jangan dihapus. LGPL mengizinkan
  pemakaian seperti ini, yaitu disalin apa adanya tanpa diubah, selama
  lisensinya tetap menyertainya. Bila suatu saat PHPMailer-nya Anda modifikasi,
  hasil modifikasi itu tetap LGPL, bukan MIT.
- **Data lokasi** di `config/` berasal dari OpenStreetMap: © kontributor
  OpenStreetMap, berlisensi **ODbL 1.0**. Atribusinya wajib tampil dan sudah
  dirender di bawah peta — jangan dihapus dari tampilan.
- **Dasar hukum** tarif dan syarat berkas yang dikutip di basis data adalah
  peraturan pemerintah. Kutipannya rujukan, bukan karya proyek ini.

Akun demo di `database/seed.php` beserta sandi `tuban12345` adalah data contoh,
bukan bagian dari lisensi apa pun. Ganti sebelum dipasang di server yang bisa
diakses umum.
