# Tuban Civic Map — Peta & Antrean Layanan Publik
## Brief Teknis untuk Build-up PHP

> Status: draft v1 · Target: web app PHP + MySQL
> Catatan: detail animasi & polish CSS akan ditambahkan menyusul (lihat §13).

---

## 1. Ringkasan

WIA adalah portal layanan publik tingkat kabupaten/kota. Warga membuka peta 3D daerah,
memilih landmark (kantor layanan) per kecamatan, membaca layanan & syarat berkas resmi,
lalu mendaftar antrean online. Petugas menerima dan memproses antrean, admin per lokasi
mengelola landmark, antrean, pembayaran, dan rekap.

**Tiga nilai utama**
1. Transparansi — warga tahu syarat berkas sebelum datang.
2. Antrean tanpa menunggu di tempat — booking online + kode pemesanan.
3. Jejak terukur — rekap harian/mingguan/bulanan/tahunan per lokasi & per petugas.

---

## 2. Aktor & Hak Akses (RBAC)

| Role | Kode | Cakupan | Kemampuan inti |
|---|---|---|---|
| Tamu | `guest` | publik | peta 3D, info lokasi, baca layanan, cek antrean via kode |
| Pengguna | `user` | akun sendiri | ambil antrean, chat, rekap pribadi, profil |
| Petugas | `officer` | 1 lokasi | terima antrean, ubah status antrean miliknya, chat, rekap |
| Admin lokasi | `admin` | 1 lokasi | landmark, deskripsi, antrean, pembayaran, assign petugas, rekap |
| Super admin | `superadmin` | semua lokasi | kelola kecamatan/lokasi/akun admin, audit log |

Aturan: `officer` hanya melihat antrean yang di-assign ke dirinya **atau** masih `baru`
di lokasinya. `admin` tidak bisa melihat data lokasi lain. Semua query lokasi
difilter `location_id` dari sesi (bukan dari input request).

---

## 3. Sitemap & Routing

Front controller tunggal: `public/index.php` → router sederhana (regex) → controller.

### Publik
| Route | Method | Controller@action | Keterangan |
|---|---|---|---|
| `/` | GET | `LandingController@index` | halaman pembuka bergaya ilustrasi + smart search |
| `/peta` | GET | `HomeController@index` | peta isometrik + panel kecamatan |
| `/kecamatan/{slug}` | GET | `HomeController@district` | peta dengan satu kecamatan disorot |
| `/lokasi/{slug}` | GET | `LocationController@show` | halaman per tempat: layanan, berkas, jam, kontak |
| `/lokasi/{slug}/daftar` | GET/POST | `QueueController@create` | registrasi antrean online, **tanpa perlu login** |
| `/cari` | GET | `SearchController@page` | hasil smart search (tanpa JavaScript) |
| `/tentang` | GET | `PageController@about` | About us |
| `/cek-antrean` | GET/POST | `TrackController@index` | search kode pemesanan |
| `/masuk` | GET/POST | `AuthController@login` | login |
| `/daftar` | GET/POST | `AuthController@register` | registrasi akun |
| `/keluar` | POST | `AuthController@logout` | logout |

### Pengguna (`role=user`)
| Route | Keterangan |
|---|---|
| `/akun` | dashboard: antrean aktif + status |
| `/akun/antrean` | daftar semua antrean yang diambil |
| `/akun/antrean/{code}` | detail antrean + timeline status |
| `/akun/antrean/{code}/chat` | chat dengan petugas penerima |
| `/akun/rekap` | rekap riwayat pribadi (filter periode) |
| `/akun/profil` | data pribadi (NIK, nama, alamat, kontak, dokumen) |

### Petugas (`role=officer`)
| Route | Keterangan |
|---|---|
| `/petugas` | papan antrean lokasi: tab Baru / Milik Saya / Selesai |
| `/petugas/antrean/{code}/terima` (POST) | klaim antrean → status `ditinjau`, `officer_id` terisi |
| `/petugas/antrean/{code}/status` (POST) | ubah status (hanya antrean miliknya) |
| `/petugas/antrean/{code}/chat` | chat dengan warga |
| `/petugas/rekap?periode=hari\|minggu\|bulan\|tahun` | rekap kinerja |

### Admin lokasi (`role=admin`)
| Route | Keterangan |
|---|---|
| `/admin` | ringkasan: antrean hari ini, pembayaran, beban petugas |
| `/admin/landmark` | CRUD landmark (koordinat, ikon, kategori) |
| `/admin/landmark/{id}/deskripsi` | CRUD deskripsi & layanan & syarat berkas |
| `/admin/antrean` | tabel antrean + filter + detail + deskripsi per antrean |
| `/admin/antrean/{code}/assign` (POST) | assign petugas |
| `/admin/pembayaran` | daftar tagihan/pembayaran per antrean |
| `/admin/rekap` | rekap per hari + export CSV |
| `/admin/petugas` | kelola akun petugas lokasi |

### API internal (JSON, dipakai JS)
| Route | Keterangan |
|---|---|
| `GET /api/search?q=` | smart search: layanan, lokasi, kecamatan, kode antrean |
| `GET /api/map/landmarks?kecamatan={slug}` | GeoJSON landmark (dipakai integrasi lain) |
| `GET /api/landmark/{id}` | deskripsi landmark (popup) |
| `GET /api/queue/{code}/status` | polling status |
| `GET /api/chat/{queue_id}?after={msg_id}` | polling pesan baru |
| `POST /api/chat/{queue_id}` | kirim pesan |
| `GET /api/stats?scope=&periode=` | data grafik rekap |

---

## 4. Stack & Struktur Folder

**Server:** PHP 8.0+ (diuji pada 8.0.30 bawaan XAMPP), MySQL 8 / MariaDB 10.6+, Apache/Nginx.
**Tanpa framework besar** (native MVC) agar ringan & mudah dinilai. **Tanpa Composer**:
autoloader PSR-4 sendiri di `bootstrap.php`, pembaca `.env` sendiri di `Core/Config`.
**Front-end:** HTML + CSS custom (tanpa build step), vanilla JS modular (`type="module"`).
**Peta:** digambar sendiri sebagai scene isometrik **dua tingkat**
(`Services/MapScene` + SVG dari server), bukan dari penyedia tile.
Tingkat 1 = Kabupaten Tuban sebagai satu pulau dengan siluet batas wilayah asli
(OpenStreetMap relasi 9676649, ODbL, disederhanakan ke 44 titik), dibagi 20 bidang
kecamatan lewat poligon Thiessen, dengan penanda per kecamatan. Tingkat 2 = setelah satu kecamatan dipilih, peta
memperbesar ke titik itu dan memunculkan kantor layanannya; kursor di atas sebuah
kantor memunculkan kartu pratinjau (ilustrasi gedung, daftar layanan, sisa kuota,
tombol ke Google Maps, tautan halaman lengkap). Tidak perlu API key, tidak bisa
tampil kosong, dan tetap utuh tanpa JavaScript. Semua ukuran dan warnanya di
`config/map.php`.

```
System/
├─ public/                  # satu-satunya folder yang diekspos web server
│  ├─ index.php             # front controller
│  ├─ .htaccess             # rewrite semua ke index.php
│  ├─ assets/
│  │  ├─ css/
│  │  │  ├─ base.css        # reset, variabel warna, tipografi
│  │  │  ├─ layout.css      # grid, header, sidebar, container
│  │  │  ├─ components.css  # card, badge, button, table, modal, chat bubble
│  │  │  ├─ map.css         # overlay peta, marker, popup
│  │  │  └─ anim.css        # SEMUA animasi di sini (lihat §13)
│  │  ├─ js/
│  │  │  ├─ map.js          # init MapLibre, marker, kamera per kecamatan
│  │  │  ├─ queue.js        # form antrean + polling status
│  │  │  ├─ chat.js         # polling/SSE chat
│  │  │  ├─ admin.js        # tabel, filter, chart rekap
│  │  │  └─ ui.js           # modal, toast, reveal-on-scroll
│  │  └─ img/
│  └─ uploads/              # berkas warga (idealnya di luar web root)
├─ app/
│  ├─ Core/                 # Router, Request, Response, View, DB, Auth, Csrf, Validator
│  ├─ Controllers/
│  ├─ Models/               # satu kelas per tabel (query builder tipis)
│  ├─ Services/             # QueueService, RecapService, PaymentService, ChatService
│  ├─ Middleware/           # AuthMiddleware, RoleMiddleware, CsrfMiddleware
│  └─ Views/
│     ├─ layouts/           # public.php, panel.php
│     ├─ partials/          # header, footer, nav, flash, pagination
│     └─ home/ location/ auth/ account/ officer/ admin/ track/
├─ config/                  # app.php, database.php, roles.php
├─ database/
│  ├─ schema.sql
│  ├─ seed.sql
│  └─ migrations/
├─ storage/                 # logs, cache, sesi file
├─ .env.example
└─ composer.json
```

**`.htaccess`**
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

---

## 5. Skema Database

```sql
-- === wilayah & lokasi =====================================================
CREATE TABLE districts (                 -- kecamatan
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  slug          VARCHAR(120) NOT NULL UNIQUE,
  center_lng    DECIMAL(10,7) NOT NULL,
  center_lat    DECIMAL(10,7) NOT NULL,
  zoom          DECIMAL(4,2)  NOT NULL DEFAULT 13.00,
  pitch         DECIMAL(4,1)  NOT NULL DEFAULT 55.0,
  bearing       DECIMAL(5,1)  NOT NULL DEFAULT 0.0,
  summary       TEXT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE locations (                 -- "tempat" / kantor layanan = landmark
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  district_id   INT UNSIGNED NOT NULL,
  name          VARCHAR(150) NOT NULL,
  slug          VARCHAR(170) NOT NULL UNIQUE,
  category      ENUM('kecamatan','kelurahan','dukcapil','puskesmas','samsat',
                     'polsek','sekolah','pasar','lainnya') NOT NULL DEFAULT 'lainnya',
  lng           DECIMAL(10,7) NOT NULL,
  lat           DECIMAL(10,7) NOT NULL,
  address       VARCHAR(255) NULL,
  phone         VARCHAR(40)  NULL,
  email         VARCHAR(120) NULL,
  icon          VARCHAR(40)  NULL,          -- nama ikon di sprite
  cover_image   VARCHAR(255) NULL,
  short_desc    VARCHAR(255) NULL,          -- muncul di popup marker
  long_desc     MEDIUMTEXT NULL,            -- halaman per tempat
  open_hours    JSON NULL,                  -- {"mon":["08:00","15:00"], ...}
  quota_per_day SMALLINT UNSIGNED NOT NULL DEFAULT 50,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE CASCADE,
  INDEX (district_id), INDEX (category)
) ENGINE=InnoDB;

-- === layanan & syarat berkas =============================================
CREATE TABLE services (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  location_id   INT UNSIGNED NOT NULL,
  name          VARCHAR(150) NOT NULL,
  slug          VARCHAR(170) NOT NULL,
  description   TEXT NULL,
  sla_days      SMALLINT UNSIGNED NULL,      -- estimasi hari kerja
  fee           DECIMAL(12,2) NOT NULL DEFAULT 0,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  sort_order    SMALLINT NOT NULL DEFAULT 0,
  FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
  UNIQUE KEY uq_service (location_id, slug)
) ENGINE=InnoDB;

CREATE TABLE service_requirements (        -- "berkas apa saja yang dibutuhkan"
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id    INT UNSIGNED NOT NULL,
  label         VARCHAR(180) NOT NULL,      -- "Fotokopi KTP 2 lembar"
  note          VARCHAR(255) NULL,
  is_mandatory  TINYINT(1) NOT NULL DEFAULT 1,
  needs_upload  TINYINT(1) NOT NULL DEFAULT 0,
  sort_order    SMALLINT NOT NULL DEFAULT 0,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- === akun ================================================================
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role          ENUM('user','officer','admin','superadmin') NOT NULL DEFAULT 'user',
  location_id   INT UNSIGNED NULL,           -- wajib utk officer & admin
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(160) NOT NULL UNIQUE,
  phone         VARCHAR(30)  NULL,
  password_hash VARCHAR(255) NOT NULL,
  nik           CHAR(16) NULL UNIQUE,
  birth_date    DATE NULL,
  gender        ENUM('L','P') NULL,
  address       VARCHAR(255) NULL,
  district_id   INT UNSIGNED NULL,
  avatar        VARCHAR(255) NULL,
  is_verified   TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at DATETIME NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
  INDEX (role), INDEX (location_id)
) ENGINE=InnoDB;

-- === antrean =============================================================
CREATE TABLE queues (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code          CHAR(10) NOT NULL UNIQUE,    -- kode pemesanan, mis. WIA-7KQ4M
  location_id   INT UNSIGNED NOT NULL,
  service_id    INT UNSIGNED NOT NULL,
  user_id       INT UNSIGNED NOT NULL,
  officer_id    INT UNSIGNED NULL,           -- petugas yang menerima
  queue_date    DATE NOT NULL,               -- tanggal kedatangan
  queue_number  SMALLINT UNSIGNED NOT NULL,  -- nomor urut per lokasi per tanggal
  slot_time     TIME NULL,
  status        ENUM('baru','ditinjau','ditangani','selesai','dibatalkan')
                NOT NULL DEFAULT 'baru',
  note_user     TEXT NULL,                   -- keterangan dari warga
  note_officer  TEXT NULL,                   -- "deskripsi per antrean"
  payment_status ENUM('tidak_perlu','menunggu','lunas','gagal')
                NOT NULL DEFAULT 'tidak_perlu',
  accepted_at   DATETIME NULL,
  finished_at   DATETIME NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (location_id) REFERENCES locations(id),
  FOREIGN KEY (service_id)  REFERENCES services(id),
  FOREIGN KEY (user_id)     REFERENCES users(id),
  FOREIGN KEY (officer_id)  REFERENCES users(id) ON DELETE SET NULL,
  UNIQUE KEY uq_number (location_id, queue_date, queue_number),
  INDEX (status), INDEX (user_id), INDEX (officer_id), INDEX (queue_date)
) ENGINE=InnoDB;

CREATE TABLE queue_documents (             -- upload berkas per antrean
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  queue_id      BIGINT UNSIGNED NOT NULL,
  requirement_id INT UNSIGNED NULL,
  file_path     VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime          VARCHAR(100) NOT NULL,
  size_bytes    INT UNSIGNED NOT NULL,
  verified      TINYINT(1) NOT NULL DEFAULT 0,
  uploaded_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (queue_id) REFERENCES queues(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE queue_status_logs (           -- timeline status, sumber rekap
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  queue_id      BIGINT UNSIGNED NOT NULL,
  from_status   VARCHAR(20) NULL,
  to_status     VARCHAR(20) NOT NULL,
  actor_id      INT UNSIGNED NULL,
  note          VARCHAR(255) NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (queue_id) REFERENCES queues(id) ON DELETE CASCADE,
  INDEX (queue_id), INDEX (created_at)
) ENGINE=InnoDB;

-- === chat ================================================================
CREATE TABLE messages (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  queue_id      BIGINT UNSIGNED NOT NULL,   -- 1 thread per antrean
  sender_id     INT UNSIGNED NOT NULL,
  body          TEXT NOT NULL,
  attachment    VARCHAR(255) NULL,
  read_at       DATETIME NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (queue_id)  REFERENCES queues(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(id),
  INDEX (queue_id, id)
) ENGINE=InnoDB;

-- === pembayaran ==========================================================
CREATE TABLE payments (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  queue_id      BIGINT UNSIGNED NOT NULL,
  amount        DECIMAL(12,2) NOT NULL,
  method        ENUM('tunai','transfer','qris','va') NOT NULL DEFAULT 'tunai',
  reference     VARCHAR(80) NULL,
  status        ENUM('menunggu','lunas','gagal','refund') NOT NULL DEFAULT 'menunggu',
  paid_at       DATETIME NULL,
  verified_by   INT UNSIGNED NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (queue_id) REFERENCES queues(id) ON DELETE CASCADE,
  INDEX (status), INDEX (created_at)
) ENGINE=InnoDB;

-- === pendukung ===========================================================
CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL, action VARCHAR(80) NOT NULL,
  entity VARCHAR(40) NULL, entity_id VARCHAR(40) NULL,
  ip VARCHAR(45) NULL, meta JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(160) NOT NULL, ip VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (email, created_at), INDEX (ip, created_at)
) ENGINE=InnoDB;
```

---

## 6. Alur Inti

### 6.1 Ambil antrean
1. Warga buka `/lokasi/{slug}` → baca layanan + syarat berkas.
2. Klik **Daftar Antrean** → bila belum login, redirect `/masuk?next=...`.
3. Form: pilih layanan → pilih tanggal (kuota tersisa dihitung dari `quota_per_day`)
   → centang checklist berkas → upload berkas yang `needs_upload=1` → catatan.
4. `QueueService::create()` dalam **transaksi**:

```php
$db->beginTransaction();
// ambil nomor urut berikutnya dengan lock
$next = (int) $db->fetchColumn(
    "SELECT COALESCE(MAX(queue_number),0)+1 FROM queues
      WHERE location_id = ? AND queue_date = ? FOR UPDATE",
    [$locationId, $date]
);
if ($next > $location['quota_per_day']) {
    throw new QuotaExceededException();
}
$code = CodeGenerator::make();            // retry bila bentrok unique
// INSERT queues  → queue_documents → queue_status_logs(to_status='baru')
// INSERT payments bila services.fee > 0 → queues.payment_status = 'menunggu'
$db->commit();
```

5. Halaman sukses: **kode pemesanan** + QR + ringkasan + tombol simpan PDF.

**Format kode:** `WIA-` + 5 karakter Crockford base32 (tanpa I, L, O, U) →
`WIA-7KQ4M`. Kolom `CHAR(10)` unique. Tidak berurutan agar tak bisa ditebak.

### 6.2 State machine status

```
baru ──(petugas klaim)──► ditinjau ──(mulai proses)──► ditangani ──► selesai
  │                           │                            │
  └──────────── dibatalkan ◄──┴────────────────────────────┘
```

Aturan transisi (`QueueService::changeStatus`):
- `baru → ditinjau` : hanya `officer` di lokasi yang sama; set `officer_id`, `accepted_at`.
- `ditinjau → ditangani` / `ditangani → selesai` : hanya `officer_id` pemilik atau `admin`.
- `→ dibatalkan` : pemilik antrean (sebelum `ditangani`) atau `admin` (kapan saja + alasan).
- Setiap transisi **wajib** menulis `queue_status_logs`.
- Transisi tak sah → `403`, bukan silent fail.

### 6.3 Chat
- Satu thread per antrean (`messages.queue_id`). Peserta: `queues.user_id` +
  `queues.officer_id` (+ `admin` lokasi dengan badge "Admin").
- Chat terbuka begitu status `ditinjau`. Sebelum itu tombol chat disabled dengan
  tooltip "Menunggu petugas menerima antrean".
- Transport: polling `GET /api/chat/{id}?after={last_id}` tiap 4 detik.
  Upgrade opsional: SSE `text/event-stream` di `/api/chat/{id}/stream`.
- Autorisasi dicek di **setiap** request, bukan hanya saat buka halaman.

### 6.4 Rekap
`RecapService::summary(scope, periode, range)` → agregasi dari `queues` +
`queue_status_logs` + `payments`:
- jumlah antrean per status
- rata-rata waktu tunggu (`accepted_at - created_at`)
- rata-rata waktu proses (`finished_at - accepted_at`)
- total pembayaran lunas
- distribusi per layanan & per petugas

Periode: `hari` (per jam), `minggu` (per hari), `bulan` (per hari), `tahun` (per bulan).
Export CSV di `/admin/rekap/export`. Cache hasil 5 menit di `storage/cache`.

---

## 7. Spesifikasi Halaman

### 7.1 Main page `/`
- **Peta 3D** full-bleed: MapLibre, `pitch: 55`, terrain + `fill-extrusion` bangunan,
  batas kecamatan sebagai layer `fill` + `line` dengan hover highlight.
- **Panel kecamatan** (kiri): daftar kecamatan; klik → `flyTo` center/zoom/pitch dari DB.
- **Marker landmark**: HTML marker ber-ikon kategori; cluster bila > 40 titik.
- **Popup deskripsi**: klik marker → kartu melayang berisi nama, kategori, `short_desc`,
  jam buka hari ini, kuota tersisa hari ini, tombol **Lihat Detail** → `/lokasi/{slug}`.
- **Panel informasi per lokasi** (kanan, slide-in): ringkasan lebih panjang tanpa pindah halaman.
- **About us**: section di bawah peta (scroll) — visi, struktur, kontak, FAQ.
- **Header**: logo, nav (Peta, Layanan, Cek Antrean, Tentang), tombol Masuk/Daftar
  atau avatar bila sudah login.

### 7.2 Page per tempat `/lokasi/{slug}`
- Hero: cover, nama, kategori, alamat, badge "Buka/Tutup sekarang", mini-map.
- **Tab Layanan**: tiap layanan = accordion → deskripsi, biaya, SLA,
  **checklist berkas** (wajib/opsional, ikon upload bila perlu unggah).
- Tab Jadwal & Kuota: tabel 7 hari ke depan + sisa kuota.
- Tab Kontak & Lokasi.
- CTA sticky bawah: **Daftar Antrean Online** → bila guest, modal "Masuk untuk melanjutkan"
  (tab Masuk / Daftar, tanpa kehilangan konteks).

### 7.3 Pengguna `/akun`
- Kartu antrean aktif: kode, lokasi, layanan, tanggal, nomor urut, badge status,
  stepper timeline 4 langkah, tombol Chat & Batalkan.
- Daftar antrean (tabel/kartu) + filter status & periode.
- Rekap pribadi: total antrean, selesai, dibatalkan, total biaya.
- Profil: data pribadi (nama, NIK, tanggal lahir, gender, alamat, kecamatan, kontak),
  dokumen tersimpan agar tak upload ulang, ganti password.

### 7.4 Admin dashboard `/admin`
- KPI: antrean hari ini, menunggu ditinjau, sedang ditangani, selesai, pembayaran masuk.
- Grafik: antrean per jam hari ini, status breakdown.
- **Landmark**: tabel + form (nama, kategori, koordinat via klik di mini-map, ikon,
  short/long desc, jam buka, kuota, aktif/nonaktif). Hapus = soft delete `is_active=0`.
- **Deskripsi**: editor layanan + syarat berkas (drag-sort).
- **Antrean**: tabel (kode, warga, layanan, tanggal, nomor, status, petugas, pembayaran),
  detail drawer: data warga, berkas + verifikasi, `note_officer`, timeline, assign petugas.
- **Pembayaran**: daftar + verifikasi manual (tunai) + tanda lunas.
- **Rekap per hari** + export CSV.
- **Petugas**: tambah/nonaktifkan akun petugas, lihat beban kerja.

### 7.5 Petugas `/petugas`
- Tiga kolom/tab: **Baru** (bisa diklaim), **Milik Saya**, **Selesai hari ini**.
- Kartu antrean: nomor besar, kode, layanan, nama warga, waktu tunggu berjalan.
- Tombol: Terima → ubah status → tulis `note_officer` → Chat.
- Rekap: toggle hari / minggu / bulan / tahun (jumlah ditangani, rata-rata durasi).

### 7.6 Cek Antrean `/cek-antrean`
- Satu input besar kode pemesanan (auto-uppercase, auto-format `WIA-XXXXX`).
- Rate limit 10 percobaan / 10 menit / IP.
- Hasil **tanpa login**: hanya data non-sensitif — nomor urut, layanan, lokasi, tanggal,
  status, estimasi dilayani, nama warga **dimasking** (`Ru*** M***`). Berkas & chat
  tidak ditampilkan; tombol "Masuk untuk detail lengkap".

---

## 8. Keamanan

| Area | Aturan |
|---|---|
| Password | `password_hash($p, PASSWORD_DEFAULT)`, min 8 karakter, cek `password_needs_rehash` saat login |
| SQL | **selalu** PDO prepared statement; `PDO::ATTR_EMULATE_PREPARES => false` |
| CSRF | token per sesi di tiap form POST; middleware tolak `419` bila gagal |
| Sesi | `session_regenerate_id(true)` saat login, cookie `HttpOnly; Secure; SameSite=Lax`, idle timeout 60 menit |
| Otorisasi | cek di controller **dan** di query (`WHERE location_id = ?` dari sesi) |
| Upload | whitelist `pdf,jpg,jpeg,png`, maks 3 MB, nama file di-rename hash, simpan di luar web root, serve lewat `FileController` yang cek hak akses |
| Output | `htmlspecialchars($v, ENT_QUOTES, 'UTF-8')` via helper `e()` di semua view |
| Rate limit | login (5/15 menit), cek antrean (10/10 menit), kirim chat (20/menit) |
| Header | `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, CSP yang mengizinkan CDN MapLibre |
| Audit | setiap aksi admin/petugas → `audit_logs` |

---

## 9. Validasi Form (ringkas)

| Field | Aturan |
|---|---|
| `email` | required, email, unique:users |
| `password` | required, min:8, konfirmasi cocok |
| `nik` | nullable, 16 digit, unique |
| `phone` | nullable, format nomor Indonesia (`08…` atau `+628…`) |
| `queue_date` | required, date, >= hari ini, <= +30 hari, bukan hari tutup lokasi |
| `service_id` | required, exists & milik `location_id` yang sama |
| upload berkas | semua requirement `is_mandatory=1 AND needs_upload=1` harus ada |

---

## 10. Seed Data Minimum

- 1 kabupaten, **5 kecamatan** dengan koordinat nyata.
- 12 lokasi (2–3 per kecamatan) mencakup beberapa kategori.
- 3–5 layanan per lokasi, masing-masing 3–6 syarat berkas.
- Akun demo: `superadmin@wia.id`, `admin@wia.id`, `petugas@wia.id`, `warga@wia.id`
  (password didokumentasikan di README lokal, **bukan** di repo publik).
- ~60 antrean tersebar 30 hari terakhir dengan status beragam agar rekap terlihat berisi.

---

## 11. Milestone Build

| # | Milestone | Isi | Est. |
|---|---|---|---|
| 1 | Fondasi | struktur folder, router, DB, View, Auth, layout, base.css | 2 hari |
| 2 | Data & publik | schema+seed, home tanpa peta, halaman lokasi, about | 2 hari |
| 3 | Peta 3D | MapLibre, marker, popup, flyTo per kecamatan | 2 hari |
| 4 | Auth & profil | login, register, middleware role, halaman profil | 1 hari |
| 5 | Antrean | form daftar, kuota, kode, halaman sukses, cek antrean | 3 hari |
| 6 | Petugas | papan antrean, klaim, status, log | 2 hari |
| 7 | Chat | thread, polling, autorisasi | 1 hari |
| 8 | Admin | CRUD landmark/layanan, antrean, assign, pembayaran | 3 hari |
| 9 | Rekap | agregasi, grafik, export CSV | 2 hari |
| 10 | Polish | animasi & CSS (§13), responsif, aksesibilitas, hardening | 3 hari |

---

## 12. Definition of Done per fitur

- Route terdaftar + middleware role benar.
- Validasi server-side lengkap (JS hanya pembantu).
- Semua query prepared, difilter `location_id` / `user_id`.
- Flash message sukses/gagal muncul.
- State kosong (`empty state`) dan state error punya tampilan sendiri.
- Responsif di 360px, 768px, 1280px.
- Keyboard-navigable, `aria-label` pada button ikon-saja.

---

## 13. Catatan Animasi & CSS — **menunggu detail dari kamu**

Semua animasi dipusatkan di `public/assets/css/anim.css` supaya mudah diganti nanti.
Kerangka yang sudah disiapkan:

```css
:root{
  --ease-out: cubic-bezier(.16,1,.3,1);
  --ease-in-out: cubic-bezier(.65,0,.35,1);
  --dur-fast: 160ms; --dur: 280ms; --dur-slow: 560ms;
}
@media (prefers-reduced-motion: reduce){
  *,*::before,*::after{
    animation-duration: .01ms !important;
    transition-duration: .01ms !important;
  }
}
```

Titik animasi yang direncanakan:
1. **Peta** — `flyTo` antar kecamatan, marker drop-in bertahap (stagger), pulse pada marker aktif.
2. **Popup landmark** — scale + fade dari titik marker (`transform-origin` di marker).
3. **Panel samping** — slide-in dengan overshoot halus.
4. **Scroll reveal** di About us (IntersectionObserver + class `.is-in`).
5. **Stepper status antrean** — garis progres mengisi, badge berdenyut saat `ditangani`.
6. **Chat** — bubble masuk dari bawah, indikator "mengetik…".
7. **Tabel admin** — skeleton shimmer saat load, row highlight saat update.
8. **Angka KPI** — count-up.
9. **Toast** — slide + auto-dismiss dengan progress bar.
10. **Transisi halaman** — `view-transition-name` (progressive enhancement).

> Begitu kamu kirim detail animasi/CSS, yang berubah hanya `anim.css` + variabel
> warna di `base.css` — struktur PHP tidak perlu disentuh.

---

## 14. Asumsi yang Dipakai

0. **Antrean bisa diambil tanpa akun.** Warga yang belum masuk mengisi nama, email,
   dan nomor ponsel di formulir; sistem membuat akun tamu tanpa sandi, dan akun itu
   otomatis tergabung bila orang yang sama mendaftar dengan email yang sama. Tombol
   Masuk/Daftar tetap ada. **Verifikasi email belum dibuat** — sampai itu ada, email
   orang lain masih bisa dipakai saat mengambil antrean sebagai tamu.
1. **Wilayah: Kabupaten Tuban, 20 kecamatan.** Nama kecamatan dan kantor memakai nama
   sebenarnya; koordinat, alamat, telepon, daftar layanan, biaya, syarat berkas, dan
   foto gedung masih contoh dan harus dicocokkan dengan standar pelayanan resmi.
2. Pembayaran tahap awal **manual/verifikasi admin**; integrasi gateway (QRIS/VA) disiapkan
   lewat tabel `payments` tapi belum diimplementasi.
3. Realtime cukup polling; SSE/WebSocket opsional bila hosting mendukung.
4. Peta 3D memakai MapLibre + tile gratis; bila butuh model bangunan custom, perlu aset GLB.
5. Notifikasi email/WA belum masuk scope v1 (hook sudah disiapkan di `QueueService`).
