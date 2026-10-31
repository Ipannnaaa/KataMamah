# KataMamah

Nama folder kerja untuk **Tuban Civic Map** — situs informasi layanan publik
dan pemesanan antrean untuk Kabupaten Tuban.

Berkas ini ringkasan cepat: apa isinya dan cara menjalankannya.
Penjelasan lengkap (keputusan teknis, asal data, catatan tiap fitur) ada di
[`README.md`](README.md).

---

## Apa ini

Satu situs dengan empat sudut pandang:

| Siapa | Bisa apa | Masuk lewat |
|---|---|---|
| **Warga** | melihat peta kantor, membaca layanan + biaya + syarat berkas, mengambil antrean, melacak statusnya, mengadukan data yang keliru | `/` dan `/peta` |
| **Petugas** | papan antrean, menerima dan mengubah status, chat dengan warga | `/petugas` |
| **Admin kantor** | kelola landmark, layanan, syarat berkas, foto gedung, aduan, rekap | `/admin` |
| **Super admin** | sama seperti admin, tetapi untuk seluruh kabupaten | `/admin` |

Antrean **tidak memerlukan akun**: warga cukup mengisi nama dan kontak, lalu
menerima kode `TCM-XXXXX`. Akun hanya diperlukan untuk melihat riwayat dan
chat dengan petugas.

Yang membedakan isinya: **132 kantor layanan sungguhan** diambil dari
OpenStreetMap, dan tiap tarif serta daftar berkas membawa dasar hukumnya.
Yang belum dikonfirmasi ditandai "Perlu dikonfirmasi", bukan ditebak.

---

## Menjalankan

Butuh **XAMPP** (PHP 8.0+ dan MariaDB). Tidak memakai Composer.

**1. Hidupkan Apache/MySQL dari XAMPP Control Panel.** Kalau MySQL mati,
seluruh halaman memberi galat 500.

**2. Siapkan `.env`** — salin dari contohnya lalu sesuaikan:

```bash
cp .env.example .env
```

Untuk server bawaan PHP, isi `APP_URL=http://localhost:8080` dan
`APP_BASE_PATH=` kosong.

**3. Nyalakan ekstensi `gd`** di `C:\xampp\php\php.ini` — hapus titik koma
pada baris `;extension=gd`. Tanpa itu foto gedung yang diunggah tidak
dikecilkan.

**4. Impor basis data:**

```bash
C:\xampp\mysql\bin\mysql.exe -u root < database/schema.sql
C:\xampp\php\php.exe database/seed.php
```

**5. Jalankan:**

```bash
C:\xampp\php\php.exe -S localhost:8080 -t public
```

Buka <http://localhost:8080>.

---

## Akun demo

Sandi semuanya **`tuban12345`**. Hapus atau ganti sebelum dipasang di server
sungguhan.

| Peran | Email |
|---|---|
| Super admin | `superadmin@tcm.test` |
| Admin kantor | `admin@tcm.test` |
| Petugas | `petugas1@tcm.test` · `petugas2@tcm.test` · `petugas3@tcm.test` |
| Warga | `warga1@tcm.test` · `warga2@tcm.test` · `warga3@tcm.test` |

---

## Alur yang paling sering dipakai

**Mengambil antrean** — buka `/peta`, klik kecamatan, pilih kantor, tekan
**Daftar antrean**. Pilih layanan, tanggal, dan sesi; centang berkas yang
sudah siap dan unggah yang bertanda *unggah*. Kode pemesanan muncul di akhir
— simpan, itu satu-satunya cara melacak tanpa akun.

**Melacak antrean** — `/cek-antrean`, masukkan kodenya. Nama ditampilkan
tersamar.

**Petugas menangani** — masuk, buka `/petugas`, tekan **Terima** pada
antrean di tab *Baru*, lalu ubah statusnya sampai selesai.

**Admin mengubah data kantor** — `/admin/landmark`, pilih kantornya dari
daftar kiri. Di situ ada data kantor, foto gedung, dan seluruh layanan
beserta syarat berkasnya.

> Saat mengisi biaya layanan: **kosong berarti belum dikonfirmasi**, `0`
> berarti memang gratis menurut peraturan. Keduanya tampil berbeda di
> halaman kantor, jadi jangan menulis 0 untuk tarif yang belum pasti.

---

## Email verifikasi

Akun baru **tidak bisa masuk sebelum emailnya diverifikasi**, jadi
pengiriman email harus jalan. Isi bagian `MAIL_*` di `.env` (contohnya ada
di `.env.example`).

Untuk mencoba tanpa SMTP sungguhan, pakai penampung uji yang sudah
disertakan — ia menerima email betulan lewat SMTP dan menyimpannya sebagai
berkas:

```bash
C:\xampp\php\php.exe tests/smtp-sink.php 1025 600
```

Lalu isi `.env` dengan `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025`,
`MAIL_ENCRYPTION=none`, `MAIL_AUTH=false`. Email yang masuk tersimpan di
`storage/logs/smtp-sink/`, dan `tests/read-sink.php tautan` mencetak tautan
verifikasi terakhir.

---

## Menguji

```bash
C:\xampp\php\php.exe tests/smoke.php
```

```bash
pwsh tests/flow.ps1
```

`smoke.php` tidak memerlukan basis data. `flow.ps1` menguji aplikasi yang
sedang berjalan dari ujung ke ujung dan menyalakan penampung SMTP-nya
sendiri — jadi MySQL dan server harus hidup lebih dulu.

Menjalankan `flow.ps1` berkali-kali berturut-turut akan menabrak pembatas
laju `/cek-antrean`. Bila ada yang gagal di bagian 9, kosongkan dulu isi
`storage/cache/ratelimit`:

```bash
rm -f storage/cache/ratelimit/*.json
```

---

## Isi folder

```
app/        kode aplikasi (Core, Controllers, Models, Services, Views)
config/     pengaturan: app, mail, map, peta wilayah, daftar fasilitas
database/   schema.sql dan seed.php
public/     satu-satunya folder yang disajikan server web
storage/    log, cache, dan berkas unggahan warga (di luar web root)
tests/      smoke.php, flow.ps1, penampung SMTP uji
```

---

## Yang belum ada

- **Foto gedung** belum ada satu pun; harus dipotret sendiri atau diminta ke
  instansinya.
- **268 tarif** masih bertanda "Perlu dikonfirmasi" — butuh konfirmasi ke
  kantor masing-masing.
- **Pembayaran** masih diverifikasi admin secara manual; belum ada QRIS/VA.
- **Pemberitahuan** lewat email atau WhatsApp selain tautan verifikasi.

---

## Lisensi

Kode: **MIT** ([`LICENSE`](LICENSE)). PHPMailer di `app/Vendor` tetap LGPL-2.1,
dan data lokasi dari OpenStreetMap tetap ODbL — atribusi di bawah peta jangan
dihapus. Rinciannya di [`README.md`](README.md).
