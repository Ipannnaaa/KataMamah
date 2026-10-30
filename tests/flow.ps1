<#
    Uji alur Tuban Civic Map dari ujung ke ujung lewat HTTP.

    Prasyarat: MySQL hidup, schema + seed sudah diimpor, dan server jalan:
        php -S localhost:8080 -t public public/index.php

    Jalankan:
        pwsh tests/flow.ps1
        pwsh tests/flow.ps1 -Base http://wia.test

    Setiap eksekusi membuat satu antrean uji baru di Dinas Kependudukan dan Pencatatan Sipil Tuban
    dan menuntaskannya sampai status "selesai".

    Bagian verifikasi email menjalankan penampung SMTP sendiri
    (tests/smtp-sink.php) di port 1025 dan menghentikannya lagi di akhir.
    Supaya aplikasi mengirim ke sana, .env harus memuat MAIL_HOST=127.0.0.1,
    MAIL_PORT=1025, MAIL_ENCRYPTION=none, MAIL_AUTH=false.
#>

param(
    [string]$Base = 'http://localhost:8080',
    [string]$Php = 'C:\xampp\php\php.exe',
    [int]$SinkPort = 1025
)

$ErrorActionPreference = 'Stop'
$base = $Base.TrimEnd('/')
$pass = 0
$fail = 0
$proyek = Split-Path -Parent $PSScriptRoot

# Penampung SMTP dinyalakan lebih dulu: pendaftaran akun di bagian 20 sudah
# mengirim email, jadi sink harus sudah mendengarkan sebelum itu.
$sink = Start-Process -FilePath $Php `
    -ArgumentList @("$PSScriptRoot\smtp-sink.php", "$SinkPort", '600') `
    -WindowStyle Hidden -PassThru

function Sink([string]$apa) {
    # Keluaran digabung jadi satu string: badan email berisi banyak baris,
    # dan larik membuat -match mengembalikan elemen, bukan benar/salah.
    return ((& $Php "$PSScriptRoot\read-sink.php" $apa 2>$null) -join "`n").Trim()
}

function MatikanSink() {
    if ($script:sink -and -not $script:sink.HasExited) {
        Stop-Process -Id $script:sink.Id -Force -ErrorAction SilentlyContinue
    }
}

trap { MatikanSink; break }

function Check([string]$label, [bool]$ok, [string]$extra = '') {
    if ($ok) { $script:pass++; "  ok   $label" }
    else { $script:fail++; "  FAIL $label $extra" }
}

function Token([string]$html) {
    if ($html -match 'name="_token" value="([a-f0-9]{64})"') { return $Matches[1] }
    throw 'token CSRF tidak ditemukan di halaman'
}

# Layar antara "Mengalihkan…" berpindah lewat <meta http-equiv="refresh">.
# Peramban mengikutinya sendiri; Invoke-WebRequest tidak, jadi di sini
# dikerjakan manual supaya uji ini melihat apa yang dilihat pengunjung.
function Follow([object]$res, [object]$session) {
    if ($res.Content -match 'http-equiv="refresh" content="\d+;url=([^"]+)"') {
        return Invoke-WebRequest -Uri $Matches[1] -WebSession $session -UseBasicParsing
    }
    return $res
}

function Login([string]$email, [string]$password) {
    $s = $null
    $page = Invoke-WebRequest -Uri "$base/masuk" -SessionVariable s -UseBasicParsing
    $token = Token $page.Content
    $res = Invoke-WebRequest -Uri "$base/masuk" -Method Post -WebSession $s -UseBasicParsing `
        -Body @{ email = $email; password = $password; _token = $token; next = '' }
    return @{ session = $s; page = (Follow $res $s) }
}

# Berkas contoh untuk menguji unggahan (dikenali finfo sebagai application/pdf).
$pdf = Join-Path $env:TEMP 'wia-surat-lahir.pdf'
[System.IO.File]::WriteAllText($pdf, @"
%PDF-1.4
1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj
2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj
3 0 obj<</Type/Page/MediaBox[0 0 200 200]/Parent 2 0 R>>endobj
trailer<</Root 1 0 R>>
%%EOF
"@)

"== 1. Login warga =="
$warga = Login 'warga1@tcm.test' 'tuban12345'
Check 'login warga berhasil' ($warga.page.Content -match 'Antrean saya')
Check 'nama warga tampil' ($warga.page.Content -match 'Rudi Maulana')

"== 2. Sandi salah ditolak =="
$salah = Login 'warga1@tcm.test' 'salah-sekali'
Check 'sandi salah tidak masuk' ($salah.page.Content -match 'tidak cocok')

"== 3. Halaman lokasi & syarat berkas =="
$lokasi = Invoke-WebRequest -Uri "$base/lokasi/dinas-kependudukan-dan-pencatatan-sipil-kabupaten-tuban" -WebSession $warga.session -UseBasicParsing
Check 'halaman lokasi terbuka' ($lokasi.Content -match 'Dinas Kependudukan dan Pencatatan Sipil Kabupaten Tuban')
Check 'layanan tampil' ($lokasi.Content -match 'Akta kelahiran')
Check 'syarat berkas tampil' ($lokasi.Content -match 'Surat keterangan lahir dari fasilitas kesehatan')
Check 'badge wajib-unggah tampil' ($lokasi.Content -match 'Wajib . unggah')
Check 'jadwal kuota tampil' ($lokasi.Content -match 'Jadwal')

"== 4. Form daftar antrean =="
$form = Invoke-WebRequest -Uri "$base/lokasi/dinas-kependudukan-dan-pencatatan-sipil-kabupaten-tuban/daftar" -WebSession $warga.session -UseBasicParsing
$token = Token $form.Content
Check 'form daftar terbuka' ($form.Content -match 'Daftar antrean online')

# Ambil layanan pertama yang punya berkas wajib unggah, langsung dari formulir,
# supaya tes tidak bergantung pada nomor id hasil seeder.
$pola = 'data-for-service="(\d+)"(?:(?!data-for-service)[\s\S])*?berkas\[(\d+)\]'

if ($form.Content -match $pola) {
    $serviceId = $Matches[1]
    $reqId = $Matches[2]
} else {
    $serviceId = ''
    $reqId = ''
}

Check 'ada layanan dengan berkas wajib unggah' ($serviceId -ne '') "(layanan $serviceId, berkas $reqId)"

$dates = [regex]::Matches($form.Content, 'name="queue_date"\s+value="(\d{4}-\d{2}-\d{2})"')
Check 'ada pilihan tanggal' ($dates.Count -gt 0) "($($dates.Count) tanggal)"
$tanggal = $dates[0].Groups[1].Value

"== 5. Berkas wajib yang kosong ditolak =="
$tanpaBerkas = Invoke-WebRequest -Uri "$base/lokasi/dinas-kependudukan-dan-pencatatan-sipil-kabupaten-tuban/daftar" -Method Post -WebSession $warga.session -UseBasicParsing `
    -Body @{ service_id = $serviceId; queue_date = $tanggal; slot_time = '09:00'; note_user = 'tanpa berkas'; _token = $token }
Check 'berkas wajib yang kosong ditolak' ($tanpaBerkas.Content -match 'wajib diunggah')

"== 6. Token CSRF palsu ditolak =="
$csrf = $null
try {
    Invoke-WebRequest -Uri "$base/lokasi/dinas-kependudukan-dan-pencatatan-sipil-kabupaten-tuban/daftar" -Method Post -WebSession $warga.session -UseBasicParsing `
        -Body @{ service_id = $serviceId; queue_date = $tanggal; slot_time = '09:00'; _token = 'palsu' } | Out-Null
} catch {
    $csrf = $_.Exception.Response.StatusCode.value__
}
Check 'token CSRF palsu ditolak 419' ($csrf -eq 419) "(status $csrf)"

"== 7. Kirim lengkap dengan unggahan =="
$form2 = Invoke-WebRequest -Uri "$base/lokasi/dinas-kependudukan-dan-pencatatan-sipil-kabupaten-tuban/daftar" -WebSession $warga.session -UseBasicParsing
$fields = @{
    service_id = "$serviceId"
    queue_date = $tanggal
    slot_time  = '09:00'
    note_user  = 'Uji alur otomatis: akta kelahiran anak kedua.'
    _token     = (Token $form2.Content)
}
$fields["berkas[$reqId]"] = Get-Item -LiteralPath $pdf

$kirim = Invoke-WebRequest -Uri "$base/lokasi/dinas-kependudukan-dan-pencatatan-sipil-kabupaten-tuban/daftar" -Method Post -WebSession $warga.session -UseBasicParsing -Form $fields
Check 'selesai daftar lewat layar Mengalihkan' ($kirim.Content -match 'Antrean berhasil dibuat')
$kirim = Follow $kirim $warga.session
Check 'antrean tersimpan' ($kirim.Content -match 'Kode pemesanan Anda')

if ($kirim.Content -match 'code-card__code">([A-Z]{3}-[0-9A-HJKMNP-TV-Z]{5})<') { $kode = $Matches[1] } else { $kode = '' }
Check 'kode pemesanan terbit' ($kode -ne '') "($kode)"
Check 'nomor urut tampil' ($kirim.Content -match 'Nomor urut')
Check 'berkas tercatat di halaman sukses' ($kirim.Content -match 'wia-surat-lahir.pdf')
Check 'status awal baru' ($kirim.Content -match 'Antrean baru')

"== 8. Antrean muncul di halaman warga =="
$akun = Invoke-WebRequest -Uri "$base/akun" -WebSession $warga.session -UseBasicParsing
Check 'antrean baru ada di daftar warga' ($akun.Content -match [regex]::Escape($kode))

"== 9. Cek antrean publik tanpa login =="
$cekSession = $null
$cekPage = Invoke-WebRequest -Uri "$base/cek-antrean" -SessionVariable cekSession -UseBasicParsing
$cekToken = Token $cekPage.Content
$cek = Invoke-WebRequest -Uri "$base/cek-antrean" -Method Post -WebSession $cekSession -UseBasicParsing `
    -Body @{ code = $kode; _token = $cekToken }
Check 'kode ditemukan tanpa login' ($cek.Content -match 'Nomor urut')
Check 'nama pemohon dimasking' ($cek.Content -match 'Ru\*\*\* Ma\*\*\*')
Check 'nama asli tidak dibocorkan' (-not ($cek.Content -match 'Rudi Maulana'))
Check 'berkas tidak ditampilkan' (-not ($cek.Content -match 'wia-surat-lahir'))

$cekSalah = Invoke-WebRequest -Uri "$base/cek-antrean" -Method Post -WebSession $cekSession -UseBasicParsing `
    -Body @{ code = 'TCM-00000'; _token = $cekToken }
Check 'kode tidak dikenal ditolak' ($cekSalah.Content -match 'tidak ditemukan')

"== 10. Warga ditolak di panel internal =="
$f1 = $null
try { Invoke-WebRequest -Uri "$base/petugas" -WebSession $warga.session -UseBasicParsing | Out-Null } catch { $f1 = $_.Exception.Response.StatusCode.value__ }
Check 'warga ditolak di /petugas (403)' ($f1 -eq 403) "(status $f1)"

$f2 = $null
try { Invoke-WebRequest -Uri "$base/admin" -WebSession $warga.session -UseBasicParsing | Out-Null } catch { $f2 = $_.Exception.Response.StatusCode.value__ }
Check 'warga ditolak di /admin (403)' ($f2 -eq 403) "(status $f2)"

"== 11. Petugas menerima antrean =="
$petugas = Login 'petugas1@tcm.test' 'tuban12345'
Check 'login petugas berhasil' ($petugas.page.Content -match 'Papan antrean')

$papan = Invoke-WebRequest -Uri "$base/petugas?tab=baru" -WebSession $petugas.session -UseBasicParsing
Check 'antrean baru terlihat petugas' ($papan.Content -match [regex]::Escape($kode))
$tokenP = Token $papan.Content

$terima = Invoke-WebRequest -Uri "$base/petugas/antrean/$kode/terima" -Method Post -WebSession $petugas.session -UseBasicParsing -Body @{ _token = $tokenP }
Check 'antrean diterima -> ditinjau' ($terima.Content -match 'diterima')

$milikSaya = Invoke-WebRequest -Uri "$base/petugas?tab=saya" -WebSession $petugas.session -UseBasicParsing
Check 'antrean pindah ke tab milik saya' ($milikSaya.Content -match [regex]::Escape($kode))
Check 'status ditinjau tampil' ($milikSaya.Content -match 'Ditinjau')

"== 12. Petugas lain tidak boleh mengubah antrean ini =="
$petugas2 = Login 'petugas2@tcm.test' 'tuban12345'
$papan2 = Invoke-WebRequest -Uri "$base/petugas?tab=baru" -WebSession $petugas2.session -UseBasicParsing
$tokenP2 = Token $papan2.Content

# Transisi yang sah (ditinjau -> ditangani) tetapi antrean milik petugas lain.
$rebut = Invoke-WebRequest -Uri "$base/petugas/antrean/$kode/status" -Method Post -WebSession $petugas2.session -UseBasicParsing `
    -Body @{ status = 'ditangani'; _token = $tokenP2 }
Check 'petugas lain ditolak mengubah status' ($rebut.Content -match 'ditangani petugas lain')
$s1 = (Invoke-WebRequest -Uri "$base/api/queue/$kode/status" -UseBasicParsing).Content | ConvertFrom-Json
Check 'status tetap ditinjau setelah percobaan petugas lain' ($s1.status -eq 'ditinjau') "($($s1.status))"

"== 13. Transisi status tidak sah ditolak =="
$lompat = Invoke-WebRequest -Uri "$base/petugas/antrean/$kode/status" -Method Post -WebSession $petugas.session -UseBasicParsing `
    -Body @{ status = 'baru'; _token = $tokenP }
Check 'transisi ditinjau -> baru ditolak' ($lompat.Content -match 'tidak diizinkan')
$s2 = (Invoke-WebRequest -Uri "$base/api/queue/$kode/status" -UseBasicParsing).Content | ConvertFrom-Json
Check 'status tetap ditinjau setelah transisi tak sah' ($s2.status -eq 'ditinjau') "($($s2.status))"

"== 14. Petugas menyelesaikan antrean =="
$ke1 = Invoke-WebRequest -Uri "$base/petugas/antrean/$kode/status" -Method Post -WebSession $petugas.session -UseBasicParsing `
    -Body @{ status = 'ditangani'; catatan = 'Berkas lengkap, akta diproses.'; _token = $tokenP }
Check 'status menjadi ditangani' ($ke1.Content -match 'Ditangani')

$catatan = Invoke-WebRequest -Uri "$base/petugas/antrean/$kode/catatan" -Method Post -WebSession $petugas.session -UseBasicParsing `
    -Body @{ note_officer = 'Akta dicetak, diambil di loket 3 pukul 13.00.'; _token = $tokenP }
Check 'catatan penanganan tersimpan' ($catatan.Content -match 'disimpan')

$ke2 = Invoke-WebRequest -Uri "$base/petugas/antrean/$kode/status" -Method Post -WebSession $petugas.session -UseBasicParsing `
    -Body @{ status = 'selesai'; _token = $tokenP }
Check 'status menjadi selesai' ($ke2.Content -match 'Telah selesai')

"== 15. Chat petugas dan warga =="
$chatPage = Invoke-WebRequest -Uri "$base/petugas/antrean/$kode/chat" -WebSession $petugas.session -UseBasicParsing
Check 'halaman chat petugas terbuka' ($chatPage.Content -match 'class="chat"')
if ($chatPage.Content -match 'data-token="([a-f0-9]{64})"') { $chatToken = $Matches[1] } else { $chatToken = $tokenP }

$kirimChat = Invoke-WebRequest -Uri "$base/api/chat/$kode" -Method Post -WebSession $petugas.session -UseBasicParsing `
    -Body @{ body = 'Akta sudah dicetak, bisa diambil di loket 3.'; _token = $chatToken }
Check 'petugas mengirim pesan (201)' ($kirimChat.StatusCode -eq 201) "(status $($kirimChat.StatusCode))"

$bacaChat = Invoke-WebRequest -Uri "$base/api/chat/$kode" -WebSession $warga.session -UseBasicParsing
Check 'warga menerima pesan petugas' ($bacaChat.Content -match 'bisa diambil di loket 3')

$chatLain = $null
try { Invoke-WebRequest -Uri "$base/api/chat/$kode" -WebSession $petugas2.session -UseBasicParsing | Out-Null } catch { $chatLain = $_.Exception.Response.StatusCode.value__ }
Check 'petugas lain ditolak membaca chat (403)' ($chatLain -eq 403) "(status $chatLain)"

"== 16. Panel admin & rekap =="
$admin = Login 'admin@tcm.test' 'tuban12345'
Check 'login admin berhasil' ($admin.page.Content -match 'Ringkasan hari ini')
Check 'KPI antrean tampil' ($admin.page.Content -match 'Antrean hari ini')

$adminAntrean = Invoke-WebRequest -Uri "$base/admin/antrean" -WebSession $admin.session -UseBasicParsing
Check 'antrean uji tampil di panel admin' ($adminAntrean.Content -match [regex]::Escape($kode))

$detail = Invoke-WebRequest -Uri "$base/admin/antrean/$kode" -WebSession $admin.session -UseBasicParsing
Check 'detail antrean terbuka' ($detail.Content -match 'Data pemohon')
Check 'berkas unggahan terdaftar' ($detail.Content -match 'wia-surat-lahir.pdf')
Check 'riwayat status lengkap' (([regex]::Matches($detail.Content, 'timeline__title')).Count -ge 4) "($(([regex]::Matches($detail.Content, 'timeline__title')).Count) langkah)"

$rekap = Invoke-WebRequest -Uri "$base/admin/rekap?periode=bulan" -WebSession $admin.session -UseBasicParsing
Check 'rekap bulan terbuka' ($rekap.Content -match 'Rata-rata tunggu')
Check 'rekap harian ada isinya' (([regex]::Matches($rekap.Content, '<tbody>')).Count -ge 1)

$csv = Invoke-WebRequest -Uri "$base/admin/rekap/export?periode=bulan" -WebSession $admin.session -UseBasicParsing
$csvType = ($csv.Headers['Content-Type'] -join ';')
Check 'export CSV terunduh' ($csvType -match 'text/csv') "($csvType)"
Check 'CSV berisi header kolom' ($csv.Content -match 'Tanggal')

$landmark = Invoke-WebRequest -Uri "$base/admin/landmark" -WebSession $admin.session -UseBasicParsing
Check 'halaman landmark terbuka' ($landmark.Content -match 'Landmark')
Check 'editor syarat berkas tampil' ($landmark.Content -match 'req_label')

"== 17. Berkas hanya untuk yang berhak =="
if ($detail.Content -match '/berkas/(\d+)') { $berkasId = $Matches[1] } else { $berkasId = '' }
Check 'id berkas ditemukan' ($berkasId -ne '') "($berkasId)"

if ($berkasId -ne '') {
    $unduh = Invoke-WebRequest -Uri "$base/berkas/$berkasId" -WebSession $warga.session -UseBasicParsing
    $unduhType = ($unduh.Headers['Content-Type'] -join ';')
    Check 'pemilik bisa membuka berkas' ($unduhType -match 'application/pdf') "($unduhType)"

    $tolak = $null
    try { Invoke-WebRequest -Uri "$base/berkas/$berkasId" -WebSession $petugas2.session -UseBasicParsing | Out-Null } catch { $tolak = $_.Exception.Response.StatusCode.value__ }
    Check 'orang lain ditolak membuka berkas (403)' ($tolak -eq 403) "(status $tolak)"

    $anonim = $null
    try { Invoke-WebRequest -Uri "$base/berkas/$berkasId" -UseBasicParsing | Out-Null } catch { $anonim = $_.Exception.Response.StatusCode.value__ }
    Check 'tanpa login dialihkan ke /masuk' ($null -eq $anonim -or $anonim -eq 302) "(status $anonim)"
}

"== 18. API peta & status =="
$geo = (Invoke-WebRequest -Uri "$base/api/map/landmarks" -UseBasicParsing).Content | ConvertFrom-Json
Check 'GeoJSON FeatureCollection' ($geo.type -eq 'FeatureCollection')
Check 'seluruh kantor aktif muncul di API' ($geo.features.Count -ge 50) "($($geo.features.Count))"
Check 'properti kuota ada' ($null -ne $geo.features[0].properties.quota_left)

$statusJson = (Invoke-WebRequest -Uri "$base/api/queue/$kode/status" -UseBasicParsing).Content | ConvertFrom-Json
Check 'API status antrean = selesai' ($statusJson.status -eq 'selesai') "($($statusJson.status))"
Check 'API status memasking nama' ($statusJson.user -match '\*\*\*')

"== 19. Ambil antrean tanpa akun (tamu) =="
$tamuSession = $null
$tamuForm = Invoke-WebRequest -Uri "$base/lokasi/dinas-kependudukan-dan-pencatatan-sipil-kabupaten-tuban/daftar" -SessionVariable tamuSession -UseBasicParsing
Check 'form daftar terbuka tanpa login' ($tamuForm.StatusCode -eq 200) "(status $($tamuForm.StatusCode))"
Check 'formulir meminta data pemohon' ($tamuForm.Content -match 'Data pemohon')
Check 'field nama tersedia' ($tamuForm.Content -match 'name="nama"')

$tamuDates = [regex]::Matches($tamuForm.Content, 'name="queue_date"\s+value="(\d{4}-\d{2}-\d{2})"')
$tamuTanggal = $tamuDates[0].Groups[1].Value
$tamuEmail = 'tamu.' + [guid]::NewGuid().ToString('N').Substring(0, 8) + '@contoh.id'

# Identitas kosong harus ditolak.
$tanpaIdentitas = Invoke-WebRequest -Uri "$base/lokasi/dinas-kependudukan-dan-pencatatan-sipil-kabupaten-tuban/daftar" -Method Post -WebSession $tamuSession -UseBasicParsing `
    -Body @{ service_id = $serviceId; queue_date = $tamuTanggal; slot_time = '09:00'; _token = (Token $tamuForm.Content) }
Check 'tamu tanpa identitas ditolak' ($tanpaIdentitas.Content -match 'wajib diisi')

$tamuForm2 = Invoke-WebRequest -Uri "$base/lokasi/dinas-kependudukan-dan-pencatatan-sipil-kabupaten-tuban/daftar" -WebSession $tamuSession -UseBasicParsing
$tamuFields = @{
    nama       = 'Siti Nurhaliza'
    email      = $tamuEmail
    telepon    = '081322009911'
    service_id = "$serviceId"
    queue_date = $tamuTanggal
    slot_time  = '10:00'
    note_user  = 'Uji alur tamu tanpa akun.'
    _token     = (Token $tamuForm2.Content)
}
$tamuFields["berkas[$reqId]"] = Get-Item -LiteralPath $pdf

$tamuKirim = Invoke-WebRequest -Uri "$base/lokasi/dinas-kependudukan-dan-pencatatan-sipil-kabupaten-tuban/daftar" -Method Post -WebSession $tamuSession -UseBasicParsing -Form $tamuFields
$tamuKirim = Follow $tamuKirim $tamuSession
Check 'antrean tamu tersimpan' ($tamuKirim.Content -match 'Kode pemesanan Anda')
Check 'ada peringatan simpan kode' ($tamuKirim.Content -match 'Simpan kode ini sekarang')

if ($tamuKirim.Content -match 'code-card__code">([A-Z]{3}-[0-9A-HJKMNP-TV-Z]{5})<') { $kodeTamu = $Matches[1] } else { $kodeTamu = '' }
Check 'kode antrean tamu terbit' ($kodeTamu -ne '') "($kodeTamu)"

$statusTamu = (Invoke-WebRequest -Uri "$base/api/queue/$kodeTamu/status" -UseBasicParsing).Content | ConvertFrom-Json
Check 'antrean tamu terlacak publik' ($statusTamu.status -eq 'baru') "($($statusTamu.status))"
Check 'nama tamu dimasking di API' ($statusTamu.user -match '\*\*\*')

$tamuAkun = $null
try { Invoke-WebRequest -Uri "$base/akun" -WebSession $tamuSession -UseBasicParsing -MaximumRedirection 0 | Out-Null }
catch { $tamuAkun = $_.Exception.Response.StatusCode.value__ }
Check 'tamu tidak bisa membuka /akun' ($tamuAkun -eq 302 -or $tamuAkun -eq 401) "(status $tamuAkun)"

"== 20. Klaim akun dengan email tamu yang sama =="
$daftarPage = Invoke-WebRequest -Uri "$base/daftar" -SessionVariable klaimSession -UseBasicParsing
$klaim = Invoke-WebRequest -Uri "$base/daftar" -Method Post -WebSession $klaimSession -UseBasicParsing `
    -Body @{
        nama = 'Siti Nurhaliza'; name = 'Siti Nurhaliza'; email = $tamuEmail
        password = 'tuban12345'; password_confirmation = 'tuban12345'
        setuju = '1'; _token = (Token $daftarPage.Content)
    }
Check 'email tamu tidak ditolak sebagai duplikat' (-not ($klaim.Content -match 'sudah terdaftar'))
Check 'registrasi lewat layar Mengalihkan' ($klaim.Content -match 'Akun dibuat')
$klaim = Follow $klaim $klaimSession

# Akun baru tidak lagi langsung masuk: emailnya harus diverifikasi dulu.
Check 'diarahkan ke halaman verifikasi' ($klaim.Content -match 'Periksa email Anda')
Check 'akun tamu dinyatakan tergabung' ($klaim.Content -match 'tergabung')

$tautanKlaim = Sink 'tautan'
Check 'email verifikasi klaim terkirim' ($tautanKlaim -match '/verifikasi/[0-9a-f]{64}')

$verifKlaim = Follow (Invoke-WebRequest -Uri $tautanKlaim -WebSession $klaimSession -UseBasicParsing) $klaimSession
Check 'klaim terverifikasi' ($verifKlaim.Content -match 'terverifikasi')

$masukKlaim = Invoke-WebRequest -Uri "$base/masuk" -WebSession $klaimSession -UseBasicParsing
$klaimMasuk = Follow (Invoke-WebRequest -Uri "$base/masuk" -Method Post -WebSession $klaimSession -UseBasicParsing `
    -Body @{ email = $tamuEmail; password = 'tuban12345'; _token = (Token $masukKlaim.Content); next = '' }) $klaimSession
Check 'akun tamu yang diklaim bisa masuk' ($klaimMasuk.Content -match 'Antrean saya')

$riwayat = Invoke-WebRequest -Uri "$base/akun/antrean" -WebSession $klaimSession -UseBasicParsing
Check 'antrean tamu muncul di akun yang diklaim' ($riwayat.Content -match [regex]::Escape($kodeTamu))

"== 21. Smart search =="
foreach ($pair in @(@('akta', 'Layanan'), @('jatirogo', 'Kecamatan'), @('puskesmas', 'Kantor layanan'))) {
    $hasil = (Invoke-WebRequest -Uri "$base/api/search?q=$($pair[0])" -UseBasicParsing).Content | ConvertFrom-Json
    Check "cari '$($pair[0])' menemukan $($pair[1])" (($hasil.results | Where-Object { $_.label -eq $pair[1] }).Count -ge 1) "($($hasil.results.Count) hasil)"
}

$cariKode = (Invoke-WebRequest -Uri "$base/api/search?q=$($kode.Replace('-',''))" -UseBasicParsing).Content | ConvertFrom-Json
Check 'cari kode tanpa tanda hubung menemukan antrean' (($cariKode.results | Where-Object { $_.label -eq 'Antrean' }).Count -eq 1)

$cariKosong = (Invoke-WebRequest -Uri "$base/api/search?q=zzzqqq" -UseBasicParsing).Content | ConvertFrom-Json
Check 'kata kunci asing tidak memaksakan hasil' ($cariKosong.results.Count -eq 0)

$halamanCari = Invoke-WebRequest -Uri "$base/cari?q=akta" -UseBasicParsing
Check 'halaman /cari bekerja tanpa JavaScript' ($halamanCari.Content -match 'Akta kelahiran')

"== 22. Halaman pembuka & peta =="
$beranda = Invoke-WebRequest -Uri "$base/" -UseBasicParsing
Check 'halaman pembuka tampil' ($beranda.Content -match 'Tuban Civic Map')
Check 'ilustrasi panorama ada' ($beranda.Content -match 'class="skyline"')
Check 'smart search ada di pembuka' ($beranda.Content -match 'smart-search__input')
Check 'tombol masuk & daftar tetap ada' (($beranda.Content -match '>Masuk<') -and ($beranda.Content -match '>Daftar<'))

$petaPage = Invoke-WebRequest -Uri "$base/peta" -UseBasicParsing
Check 'peta isometrik tampil di /peta' ($petaPage.Content -match 'class="iso"')
Check 'daratan kabupaten tergambar utuh' ($petaPage.Content -match 'iso-land')
Check '20 penanda kecamatan' (([regex]::Matches($petaPage.Content, 'class="iso-kec"')).Count -eq 20) "($(([regex]::Matches($petaPage.Content, 'class="iso-kec"')).Count))"
Check '20 batas kecamatan tergambar' (([regex]::Matches($petaPage.Content, 'class="iso-cell"')).Count -eq 20) "($(([regex]::Matches($petaPage.Content, 'class="iso-cell"')).Count))"
Check 'pulau tergambar berlapis' ($petaPage.Content -match 'iso-rock' -and $petaPage.Content -match 'iso-soil')
Check 'ikon per kategori dipakai di pin' (([regex]::Matches($petaPage.Content, 'iso-pin__icon')).Count -ge 50)
Check 'seluruh kantor tergambar' (([regex]::Matches($petaPage.Content, 'class="iso-mark"')).Count -ge 50)
Check 'tanpa JS pun peta tetap ada isinya' ($petaPage.Content -match 'Kantor Kecamatan Jenu')
Check 'tombol Google Maps tersedia di peta' ($petaPage.Content -match 'google\.com/maps')

$kec = Invoke-WebRequest -Uri "$base/kecamatan/jatirogo" -UseBasicParsing
Check 'halaman kecamatan membuka tingkat kedua' ($kec.Content -match 'data-active="jatirogo"')

$lokasi = Invoke-WebRequest -Uri "$base/lokasi/kantor-kecamatan-jenu" -UseBasicParsing
Check 'halaman kantor punya tombol Google Maps' ($lokasi.Content -match 'Buka di Google Maps')

"== 23. Wordmark & halaman error =="
Check 'wordmark memecah nama kabupaten per huruf' (([regex]::Matches($beranda.Content, 'class="wordmark__huruf"')).Count -eq 5) "($(([regex]::Matches($beranda.Content, 'class="wordmark__huruf"')).Count))"
Check 'wordmark tetap satu nama bagi pembaca layar' ($beranda.Content -match 'aria-label="Tuban Civic Map"')
Check 'bagian Civic Map terpisah dari nama kota' ($beranda.Content -match 'wordmark__judul')

$animCss = (Invoke-WebRequest -Uri "$base/assets/css/anim.css" -UseBasicParsing).Content
Check 'transisi antar halaman dideklarasikan' ($animCss -match '@view-transition')
Check 'bagian yang menetap diberi nama' ($animCss -match 'view-transition-name: vt-kepala')
Check 'cadangan tanpa transisi tetap ada' ($animCss -match 'tanpa-transisi')
Check 'cadangan tidak lagi dipicu :active-view-transition' ($animCss -notmatch 'html:active-view-transition')

# PowerShell 7 melempar pada status 4xx/5xx dan objek Response-nya bukan lagi
# HttpWebResponse, jadi badannya tidak bisa dibaca dari blok catch.
# -SkipHttpErrorCheck membuat responsnya dikembalikan apa adanya.
function Get-ErrorBody($url) {
    $r = Invoke-WebRequest -Uri $url -UseBasicParsing -SkipHttpErrorCheck
    return @{ Code = [int]$r.StatusCode; Body = $r.Content }
}

$e404 = Get-ErrorBody "$base/halaman-yang-tidak-ada"
Check 'alamat asing menjawab 404' ($e404.Code -eq 404) "($($e404.Code))"
Check 'halaman 404 memakai judul sendiri' ($e404.Body -match 'Halaman ini tidak ada di peta')
Check 'judul tab ikut berubah' ($e404.Body -match '<title>Halaman ini tidak ada di peta')
Check 'ilustrasi pin nyasar tampil' ($e404.Body -match 'errorpage__pin')
Check 'halaman error menawarkan jalan keluar' ($e404.Body -match 'Buka peta layanan')

# /mengalihkan hanya boleh bekerja setelah ada aksi; tujuannya disimpan di
# sesi, jadi membukanya langsung tidak boleh melempar ke mana-mana.
$alihLangsung = Invoke-WebRequest -Uri "$base/mengalihkan" -UseBasicParsing
Check 'buka /mengalihkan langsung dikembalikan ke depan' ($alihLangsung.BaseResponse.RequestMessage.RequestUri.AbsolutePath -eq '/')
Check 'wordmark berulang, bukan sekali jalan' ($animCss -match 'infinite both;')
Check 'bilah tunggu mengikuti config' ($animCss -match 'var\(--tunda')
Check 'perpindahan menggeser menyamping' ($animCss -match 'halaman-masuk-kanan' -and $animCss -match 'halaman-keluar-kiri')
Check 'arah dibalik saat tombol kembali' ($animCss -match 'halaman-masuk-kiri' -and $animCss -match 'mundur')
Check 'tempo perpindahan punya token sendiri' ($animCss -match '--dur-page-in')
$petaHead = (Invoke-WebRequest -Uri "$base/peta" -UseBasicParsing).Content
Check 'penentu arah dipasang di head, bukan ui.js' ($petaHead -match 'pagereveal')

$eKec = Get-ErrorBody "$base/kecamatan/kecamatan-palsu"
Check 'pesan spesifik dari controller ikut ditampilkan' ($eKec.Body -match 'Kecamatan tidak ditemukan')

"== 24. Data asli Tuban =="
Check 'kantor nyata dari OSM tampil di peta' ($petaPage.Content -match 'Polsek Bulu')
Check 'puskesmas asli tampil' ($petaPage.Content -match 'UPTD Puskesmas Bancar')
Check 'atribusi OpenStreetMap tetap ada' ($petaPage.Content -match 'kontributor OpenStreetMap')
Check 'catatan batas perkiraan sudah dicabut' ($petaPage.Content -notmatch 'perkiraan dari titik pusat tiap kecamatan')

$polsek = Invoke-WebRequest -Uri "$base/lokasi/polsek-bulu" -UseBasicParsing
Check 'tarif SKCK memakai angka resmi' ($polsek.Content -match 'Rp 30\.000')
Check 'dasar hukum tarif ikut tampil' ($polsek.Content -match 'PP 76/2020')
Check 'dasar daftar berkas ikut tampil' ($polsek.Content -match 'Perkap 18/2014')
Check 'daftar berkas tanpa dasar mengaku susunan umum' ((Invoke-WebRequest -Uri "$base/lokasi/pasar-bulu" -UseBasicParsing).Content -match 'susunan umum')
Check 'tiap berkas punya catatan praktis' ($polsek.Content -match 'Bawa dokumen aslinya')
Check 'tarif tak pasti tidak disebut gratis' ((Invoke-WebRequest -Uri "$base/lokasi/pasar-bulu" -UseBasicParsing).Content -match 'Perlu dikonfirmasi')
Check 'centang berkas sejajar dengan teksnya' ((Invoke-WebRequest -Uri "$base/lokasi/polsek-bulu/daftar" -UseBasicParsing).Content -match 'req__text--check')
Check 'halaman kantor nyata terbuka' ($polsek.Content -match 'Polsek Bulu')
Check 'nomor telepon tidak dikarang' ($polsek.Content -notmatch '\(0356\) 000-000')
Check 'email contoh sudah hilang' ($polsek.Content -notmatch 'contoh\.go\.id')

"== 25. Penyaring legenda =="
Check 'legenda jadi tombol' ($petaPage.Content -match 'iso-legend__btn')
Check 'tombol punya kategori' ($petaPage.Content -match 'data-filter="puskesmas"')
Check 'ada tombol kembali ke semua' ($petaPage.Content -match 'Semua jenis')

"== 26. Aduan per fasilitas =="
Check 'form aduan ada di halaman kantor' ($polsek.Content -match 'id="aduan"')
Check 'jenis aduan tersedia' ($polsek.Content -match 'Data di halaman ini tidak cocok')
Check 'aduan bisa anonim' ($polsek.Content -match 'Aduan anonim tetap diterima')

$aduanSesi = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$aduanPage = Invoke-WebRequest -Uri "$base/lokasi/polsek-bulu" -WebSession $aduanSesi -UseBasicParsing
$aduanToken = Token $aduanPage.Content

$pendek = Invoke-WebRequest -Uri "$base/lokasi/polsek-bulu/aduan" -Method Post -WebSession $aduanSesi -UseBasicParsing `
    -Body @{ _token = $aduanToken; kategori = 'data_salah'; aduan = 'jelek'; kontak = '' }
Check 'aduan terlalu pendek ditolak' ($pendek.Content -match 'field-error')

$aduanToken2 = Token $pendek.Content
$kirimAduan = Invoke-WebRequest -Uri "$base/lokasi/polsek-bulu/aduan" -Method Post -WebSession $aduanSesi -UseBasicParsing `
    -Body @{
        _token = $aduanToken2; kategori = 'data_salah'
        aduan = 'Jam buka di halaman ini keliru, hari Jumat tutup pukul sebelas siang.'
        kontak = ''
    }
Check 'aduan tanpa akun diterima' ($kirimAduan.Content -match 'Aduan terkirim')

$super = Login 'superadmin@tcm.test' 'tuban12345'
$panelAduan = Invoke-WebRequest -Uri "$base/admin/aduan" -WebSession $super.session -UseBasicParsing
Check 'aduan masuk panel admin' ($panelAduan.Content -match 'Jam buka di halaman ini keliru')
Check 'panel menunjukkan kantor yang diadukan' ($panelAduan.Content -match 'Polsek Bulu')

$idAduan = [regex]::Match($panelAduan.Content, '/admin/aduan/(\d+)/status').Groups[1].Value
$ubah = Invoke-WebRequest -Uri "$base/admin/aduan/$idAduan/status" -Method Post -WebSession $super.session -UseBasicParsing `
    -Body @{ _token = (Token $panelAduan.Content); status = 'ditindaklanjuti'; catatan = 'Dicek ke lapangan.' }
Check 'status aduan bisa diubah' ($ubah.Content -match 'ditandai')
Check 'catatan admin tersimpan' ($ubah.Content -match 'Dicek ke lapangan')

$adminLokasi = Login 'admin@tcm.test' 'tuban12345'
$panelLain = Invoke-WebRequest -Uri "$base/admin/aduan" -WebSession $adminLokasi.session -UseBasicParsing
Check 'admin lokasi tidak melihat aduan kantor lain' ($panelLain.Content -notmatch 'Jam buka di halaman ini keliru')

$tolak = Get-ErrorBody "$base/admin/aduan/$idAduan/status"
Check 'ubah status aduan menolak GET' ($tolak.Code -eq 405) "($($tolak.Code))"

"== 27. Foto gedung =="

# Gambar uji dibuat di sini, bukan disimpan di repo: ukurannya harus di atas
# lebar minimal supaya lolos pemeriksaan, dan itu lebih jelas kalau terlihat.
$fotoUji = Join-Path $env:TEMP 'wia-gedung-uji.jpg'
Add-Type -AssemblyName System.Drawing
$bmp = New-Object System.Drawing.Bitmap 1200, 750
$gfx = [System.Drawing.Graphics]::FromImage($bmp)
$gfx.Clear([System.Drawing.Color]::FromArgb(206, 222, 235))
$gfx.FillRectangle((New-Object System.Drawing.SolidBrush ([System.Drawing.Color]::FromArgb(238, 236, 228))), 150, 250, 900, 500)
$gfx.Dispose()
$bmp.Save($fotoUji, [System.Drawing.Imaging.ImageFormat]::Jpeg)
$bmp.Dispose()

# Invoke-WebRequest menahan handle berkas yang diunggah, jadi tiap unggahan
# memakai salinannya sendiri.
function Salinan([string]$src) {
    $tmp = Join-Path $env:TEMP ('wia-unggah-' + [guid]::NewGuid().ToString('N') + [IO.Path]::GetExtension($src))
    Copy-Item -LiteralPath $src -Destination $tmp
    return (Get-Item -LiteralPath $tmp)
}

$sadmin = Login 'superadmin@tcm.test' 'tuban12345'
$panelFoto = Invoke-WebRequest -Uri "$base/admin/landmark/2/deskripsi" -WebSession $sadmin.session -UseBasicParsing
Check 'panel superadmin melihat seluruh kantor' ($panelFoto.Content -match '132 terdaftar')
Check 'formulir foto tersedia' ($panelFoto.Content -match 'name="foto"')
Check 'formulir foto multipart' ($panelFoto.Content -match 'enctype="multipart/form-data"')

$tanpaSumber = Invoke-WebRequest -Uri "$base/admin/landmark/2/foto" -Method Post -WebSession $sadmin.session -UseBasicParsing `
    -Form @{ _token = (Token $panelFoto.Content); cover_credit = ''; foto = (Salinan $fotoUji) }
Check 'foto tanpa sumber ditolak' ($tanpaSumber.Content -match 'sebelum mengunggah')

$bukanGambar = Invoke-WebRequest -Uri "$base/admin/landmark/2/foto" -Method Post -WebSession $sadmin.session -UseBasicParsing `
    -Form @{ _token = (Token $tanpaSumber.Content); cover_credit = 'Uji'; foto = (Salinan $pdf) }
Check 'berkas non-gambar ditolak' ($bukanGambar.Content -match 'bukan foto')

$unggah = Invoke-WebRequest -Uri "$base/admin/landmark/2/foto" -Method Post -WebSession $sadmin.session -UseBasicParsing `
    -Form @{ _token = (Token $bukanGambar.Content); cover_credit = 'Dokumentasi uji otomatis'; foto = (Salinan $fotoUji) }
Check 'foto tersimpan' ($unggah.Content -match 'tersimpan')

$urlFoto = ''
if ($unggah.Content -match 'src="[^"]*(/uploads/lokasi/[0-9a-f]{32}\.jpg)"') { $urlFoto = $Matches[1] }
Check 'nama berkas diacak, bukan nama asli' ($urlFoto -ne '') "($urlFoto)"

if ($urlFoto -ne '') {
    $sajian = Invoke-WebRequest -Uri "$base$urlFoto" -UseBasicParsing
    # Headers[...] di PowerShell 7 berisi larik, jadi digabung dulu.
    $tipe = ($sajian.Headers['Content-Type'] -join ' ')
    Check 'foto disajikan langsung sebagai gambar' ($tipe -match 'image/jpeg') "($tipe)"
}

$halamanFoto = Invoke-WebRequest -Uri "$base/lokasi/polsek-bulu" -UseBasicParsing
Check 'foto tampil di halaman kantor' ($halamanFoto.Content -match 'class="cover"')
Check 'sumber foto ikut tampil' ($halamanFoto.Content -match 'Dokumentasi uji otomatis')

$petaFoto = Invoke-WebRequest -Uri "$base/peta" -UseBasicParsing
Check 'pin peta membawa foto' ($petaFoto.Content -match 'data-photo="[^"]*/uploads/lokasi/')

$apiFoto = Invoke-RestMethod -Uri "$base/api/landmark/2"
Check 'API menyertakan foto' ($apiFoto.photo -match '/uploads/lokasi/')
Check 'API menyertakan sumbernya' ($apiFoto.photo_credit -eq 'Dokumentasi uji otomatis')

# Admin kantor lain tidak boleh mengubah foto kantor yang bukan wilayahnya.
$lainFoto = Invoke-WebRequest -Uri "$base/admin/landmark" -WebSession $adminLokasi.session -UseBasicParsing
$silang = Invoke-WebRequest -Uri "$base/admin/landmark/2/foto" -Method Post -WebSession $adminLokasi.session -UseBasicParsing `
    -Body @{ _token = (Token $lainFoto.Content); hapus = '1' } -SkipHttpErrorCheck
Check 'admin kecamatan lain tidak bisa mengubah foto' ($silang.StatusCode -eq 403) "($($silang.StatusCode))"

$panelHapus = Invoke-WebRequest -Uri "$base/admin/landmark/2/deskripsi" -WebSession $sadmin.session -UseBasicParsing
$hapusFoto = Invoke-WebRequest -Uri "$base/admin/landmark/2/foto" -Method Post -WebSession $sadmin.session -UseBasicParsing `
    -Body @{ _token = (Token $panelHapus.Content); hapus = '1' }
Check 'foto bisa dihapus' ($hapusFoto.Content -match 'dihapus')

$setelahHapus = Invoke-WebRequest -Uri "$base/lokasi/polsek-bulu" -UseBasicParsing
Check 'halaman kembali tanpa foto' ($setelahHapus.Content -notmatch 'class="cover"')


# Foto besar dikodekan ulang oleh ekstensi gd: dipangkas ke 1600 px dan
# dibersihkan dari apa pun yang menumpang di dalam berkas. Muatan uji
# disisipkan setelah penanda akhir JPEG — berkas tetap gambar yang sah,
# jadi pemeriksaan tipe saja tidak akan menangkapnya.
$fotoBesar = Join-Path $env:TEMP 'wia-gedung-besar.jpg'
$bmpBesar = New-Object System.Drawing.Bitmap 3000, 1875
$gfxBesar = [System.Drawing.Graphics]::FromImage($bmpBesar)
$gfxBesar.Clear([System.Drawing.Color]::FromArgb(206, 222, 235))
$gfxBesar.Dispose()
$bmpBesar.Save($fotoBesar, [System.Drawing.Imaging.ImageFormat]::Jpeg)
$bmpBesar.Dispose()
[IO.File]::WriteAllBytes($fotoBesar, ([IO.File]::ReadAllBytes($fotoBesar) + [Text.Encoding]::ASCII.GetBytes('MUATAN-MENUMPANG-UJI')))

$panelBesar = Invoke-WebRequest -Uri "$base/admin/landmark/2/deskripsi" -WebSession $sadmin.session -UseBasicParsing
$unggahBesar = Invoke-WebRequest -Uri "$base/admin/landmark/2/foto" -Method Post -WebSession $sadmin.session -UseBasicParsing `
    -Form @{ _token = (Token $panelBesar.Content); cover_credit = 'Uji pengecilan'; foto = (Salinan $fotoBesar) }

$urlBesar = ''
if ($unggahBesar.Content -match 'src="[^"]*(/uploads/lokasi/[0-9a-f]{32}\.jpg)"') { $urlBesar = $Matches[1] }
Check 'foto besar tersimpan' ($urlBesar -ne '')

if ($urlBesar -ne '') {
    $berkasBesar = Join-Path "$PSScriptRoot\..\public" ($urlBesar -replace '/', '\')
    $isiBesar = [IO.File]::ReadAllBytes($berkasBesar)
    $msBesar = New-Object IO.MemoryStream (, $isiBesar)
    $imgBesar = [System.Drawing.Image]::FromStream($msBesar)
    $lebar = $imgBesar.Width
    $imgBesar.Dispose()
    $msBesar.Dispose()

    Check 'foto besar dipangkas ke 1600 px' ($lebar -eq 1600) "(lebar $lebar px; butuh ekstensi gd)"
    Check 'muatan yang menumpang ikut hilang' (-not ([Text.Encoding]::ASCII.GetString($isiBesar)).Contains('MUATAN-MENUMPANG-UJI'))

    $panelBersih = Invoke-WebRequest -Uri "$base/admin/landmark/2/deskripsi" -WebSession $sadmin.session -UseBasicParsing
    $null = Invoke-WebRequest -Uri "$base/admin/landmark/2/foto" -Method Post -WebSession $sadmin.session -UseBasicParsing `
        -Body @{ _token = (Token $panelBersih.Content); hapus = '1' }
}

"== 28. Verifikasi email =="

# Alamat baru tiap eksekusi supaya tes tidak bergantung pada sisa
# jalannya yang sebelumnya.
$mailUji = "uji-verif-$([guid]::NewGuid().ToString('N').Substring(0,8))@contoh.test"

$verifSesi = $null
$halDaftar = Invoke-WebRequest -Uri "$base/daftar" -SessionVariable verifSesi -UseBasicParsing
$buatAkun = Invoke-WebRequest -Uri "$base/daftar" -Method Post -WebSession $verifSesi -UseBasicParsing -Body @{
    _token = (Token $halDaftar.Content); name = 'Warga Uji Verifikasi'; email = $mailUji
    phone = '081234567890'; nik = ''; district_id = ''
    password = 'rahasia12345'; password_confirmation = 'rahasia12345'; setuju = '1'
}
$halVerif = Follow $buatAkun $verifSesi
Check 'pendaftaran berujung di halaman verifikasi' ($halVerif.Content -match 'Periksa email Anda')
Check 'akun baru tidak langsung masuk' ($halVerif.Content -notmatch 'Antrean saya')
Check 'alamat tujuan disebutkan' ($halVerif.Content -match [regex]::Escape($mailUji))

Check 'email verifikasi sampai ke penampung SMTP' ((Sink 'kepada') -eq $mailUji)
$isiMail = Sink 'isi'
Check 'badan email memuat nama penerima' ($isiMail -match 'Warga Uji Verifikasi')
$tautanUji = Sink 'tautan'
Check 'tautan verifikasi terbentuk' ($tautanUji -match '/verifikasi/[0-9a-f]{64}$')

$sesiTolak = $null
$halMasuk = Invoke-WebRequest -Uri "$base/masuk" -SessionVariable sesiTolak -UseBasicParsing
$masukTolak = Follow (Invoke-WebRequest -Uri "$base/masuk" -Method Post -WebSession $sesiTolak -UseBasicParsing `
    -Body @{ _token = (Token $halMasuk.Content); email = $mailUji; password = 'rahasia12345'; next = '' }) $sesiTolak
Check 'belum terverifikasi tidak bisa masuk' ($masukTolak.Content -match 'belum diverifikasi')
Check 'form kirim ulang muncul di halaman masuk' ($masukTolak.Content -match 'id="kirim-ulang"')

$sesiVerif = $null
$bukaTautan = Follow (Invoke-WebRequest -Uri $tautanUji -SessionVariable sesiVerif -UseBasicParsing) $sesiVerif
Check 'tautan verifikasi diterima' ($bukaTautan.Content -match 'terverifikasi')
Check 'verifikasi tidak memberi sesi, diminta masuk' ($bukaTautan.Content -match 'Masuk ke akun Anda')

$pakaiUlang = Invoke-WebRequest -Uri $tautanUji -UseBasicParsing
Check 'tautan hanya sekali pakai' ($pakaiUlang.Content -match 'tidak berlaku lagi')

$tokenNgawur = Invoke-WebRequest -Uri "$base/verifikasi/$('a' * 64)" -UseBasicParsing
Check 'token karangan ditolak' ($tokenNgawur.Content -match 'tidak berlaku lagi')

$sesiMasuk = $null
$halMasuk2 = Invoke-WebRequest -Uri "$base/masuk" -SessionVariable sesiMasuk -UseBasicParsing
$masukOk = Follow (Invoke-WebRequest -Uri "$base/masuk" -Method Post -WebSession $sesiMasuk -UseBasicParsing `
    -Body @{ _token = (Token $halMasuk2.Content); email = $mailUji; password = 'rahasia12345'; next = '' }) $sesiMasuk
Check 'setelah verifikasi bisa masuk' ($masukOk.Content -match 'Antrean saya')

# Jawaban kirim ulang harus sama persis untuk alamat yang sudah
# terverifikasi dan alamat yang tidak terdaftar sama sekali — kalau berbeda,
# halaman ini berubah jadi alat memeriksa siapa yang punya akun di sini.
$jumlahSebelum = [int](Sink 'jumlah')
$sesiUlang = $null
$halUlang = Invoke-WebRequest -Uri "$base/verifikasi" -SessionVariable sesiUlang -UseBasicParsing
$ulangTerverif = Invoke-WebRequest -Uri "$base/verifikasi/kirim-ulang" -Method Post -WebSession $sesiUlang -UseBasicParsing `
    -Body @{ _token = (Token $halUlang.Content); email = $mailUji }
Check 'kirim ulang untuk akun terverifikasi dijawab netral' ($ulangTerverif.Content -match 'Kalau alamat itu terdaftar')

$halUlang2 = Invoke-WebRequest -Uri "$base/verifikasi" -WebSession $sesiUlang -UseBasicParsing
$ulangAsing = Invoke-WebRequest -Uri "$base/verifikasi/kirim-ulang" -Method Post -WebSession $sesiUlang -UseBasicParsing `
    -Body @{ _token = (Token $halUlang2.Content); email = 'tidak-pernah-ada@contoh.test' }
Check 'kirim ulang untuk alamat asing dijawab sama' ($ulangAsing.Content -match 'Kalau alamat itu terdaftar')
Check 'tidak ada email terkirim untuk keduanya' ([int](Sink 'jumlah') -eq $jumlahSebelum)

$sesiCsrf = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$csrfPalsu = Invoke-WebRequest -Uri "$base/verifikasi/kirim-ulang" -Method Post -WebSession $sesiCsrf -UseBasicParsing `
    -Body @{ _token = ('0' * 64); email = $mailUji } -SkipHttpErrorCheck
Check 'kirim ulang menolak token CSRF palsu' ($csrfPalsu.StatusCode -eq 419) "($($csrfPalsu.StatusCode))"

# -LiteralPath wajib: nama folder proyek memuat "[WIA]", dan Test-Path
# tanpa itu membaca kurung sikunya sebagai wildcard sehingga selalu salah.
Check 'PHPMailer terpasang tanpa Composer' (Test-Path -LiteralPath "$proyek\app\Vendor\PHPMailer\PHPMailer.php")
Check 'lisensi PHPMailer ikut disalin' (Test-Path -LiteralPath "$proyek\app\Vendor\PHPMailer\LICENSE")

"== 29. Panel layanan tidak merusak data =="

# Menyimpan ulang satu layanan tanpa mengubah apa pun harus menghasilkan
# data yang sama persis. Dua hal pernah hilang di sini: tarif yang belum
# dikonfirmasi berubah jadi "Gratis", dan catatan tiap berkas terhapus.

# Mengambil satu <form> layanan dari halaman panel beserta seluruh isiannya,
# lalu mengirimkannya kembali apa adanya — persis seperti menekan "Simpan
# layanan" tanpa menyentuh satu kolom pun.
function FormLayanan([string]$html, [string]$namaLayanan) {
    foreach ($m in [regex]::Matches($html, '(?s)<form[^>]*service-form[^>]*>(.*?)</form>')) {
        $isi = $m.Groups[1].Value

        if ($isi -notmatch ('name="name"[^>]*value="' + [regex]::Escape($namaLayanan) + '"')) {
            continue
        }

        $pasangan = @()

        foreach ($i in [regex]::Matches($isi, '<input[^>]*>')) {
            $tag = $i.Value
            if ($tag -notmatch 'name="([^"]+)"') { continue }
            $nama = $Matches[1]
            $nilai = if ($tag -match 'value="([^"]*)"') { $Matches[1] } else { '' }

            # Kotak centang yang tidak tercentang memang tidak dikirim peramban.
            if ($tag -match 'type="checkbox"' -and $tag -notmatch '\schecked') { continue }

            # Dikumpulkan sebagai pasangan, bukan hashtable: req_label[]
            # muncul berkali-kali dengan nama yang sama. Hashtable hanya
            # menyimpan satu nilai per kunci, dan -Body yang menerima larik
            # mengirimkannya sebagai teks "System.Object[]" — sempat
            # menimpa seluruh syarat berkas dengan satu baris berisi itu.
            $pasangan += , @($nama, [System.Net.WebUtility]::HtmlDecode($nilai))
        }

        foreach ($t in [regex]::Matches($isi, '(?s)<textarea[^>]*name="([^"]+)"[^>]*>(.*?)</textarea>')) {
            $pasangan += , @($t.Groups[1].Value, [System.Net.WebUtility]::HtmlDecode($t.Groups[2].Value))
        }

        return $pasangan
    }

    throw "form layanan '$namaLayanan' tidak ditemukan"
}

# Merangkai pasangan nama/nilai menjadi badan application/x-www-form-urlencoded,
# satu-satunya cara mengirim nama yang berulang.
function Urlencode($pasangan) {
    return (($pasangan | ForEach-Object {
        [Uri]::EscapeDataString($_[0]) + '=' + [Uri]::EscapeDataString($_[1])
    }) -join '&')
}

$panelLayanan = Invoke-WebRequest -Uri "$base/admin/landmark/9/deskripsi" -WebSession $sadmin.session -UseBasicParsing
Check 'tarif belum dikonfirmasi tampil kosong, bukan 0' `
    ($panelLayanan.Content -match '(?s)value="Balik nama hak pakai kios".{0,900}?name="fee"[^>]*value=""')
Check 'kolom dasar tarif tersedia di panel' ($panelLayanan.Content -match 'name="fee_basis"')
Check 'kolom dasar berkas tersedia di panel' ($panelLayanan.Content -match 'name="docs_basis"')
Check 'kolom catatan berkas tersedia di panel' ($panelLayanan.Content -match 'name="req_note\[0\]"')

$isiForm = FormLayanan $panelLayanan.Content 'Balik nama hak pakai kios'
$isiForm += , @('_token', (Token $panelLayanan.Content))
$simpanUlang = Invoke-WebRequest -Uri "$base/admin/layanan/simpan" -Method Post -WebSession $sadmin.session -UseBasicParsing `
    -ContentType 'application/x-www-form-urlencoded' -Body (Urlencode $isiForm)
Check 'layanan tersimpan ulang' ($simpanUlang.Content -match 'disimpan')
Check 'jumlah syarat berkas tidak berubah' `
    ((FormLayanan (Invoke-WebRequest -Uri "$base/admin/landmark/9/deskripsi" -WebSession $sadmin.session -UseBasicParsing).Content 'Balik nama hak pakai kios' `
        | Where-Object { $_[0] -eq 'req_label[]' -and $_[1] -ne '' }).Count -eq 2)

$publik = Invoke-WebRequest -Uri "$base/lokasi/pasar-plumpang" -UseBasicParsing
Check 'tarif tetap "Perlu dikonfirmasi" setelah disimpan' ($publik.Content -match 'Perlu dikonfirmasi')
Check 'dasar tarif tidak hilang' ($publik.Content -match 'Tarif Perda Kabupaten Tuban')
Check 'dasar berkas tidak hilang' ($publik.Content -match 'Susunan umum')
# Dicocokkan pada berkas milik layanan itu sendiri, bukan sekadar ada di
# halaman: teks catatan yang sama dipakai banyak layanan lain, jadi
# pencarian lepas akan tetap lolos walau catatannya terhapus.
Check 'catatan berkas tidak terhapus' `
    ($publik.Content -match '(?s)Fotokopi KTP kedua pihak.{0,200}?Bawa dokumen aslinya untuk dicocokkan petugas')

"== 30. Tampilan unggah berkas =="
$halUnggah = Invoke-WebRequest -Uri "$base/lokasi/polsek-bulu/daftar" -UseBasicParsing
Check 'baris syarat memakai kisi dua kolom' ($halUnggah.Content -match 'class="req"')
Check 'lencana mendahului input berkas' `
    ($halUnggah.Content -match '(?s)badge--unggah">Unggah</span>\s*<input\s[^>]*class="req__file"')
$css = Invoke-WebRequest -Uri "$base/assets/css/components.css" -UseBasicParsing
Check 'input berkas punya gaya sendiri' ($css.Content -match "input\[type='file'\]")
Check 'tombol pilih berkas ikut ditata' ($css.Content -match '::file-selector-button')
Check 'Safari lama ikut ditangani' ($css.Content -match '::-webkit-file-upload-button')

Check 'halaman daftar memperingatkan verifikasi email' `
    ((Invoke-WebRequest -Uri "$base/daftar" -UseBasicParsing).Content -match 'Email Anda akan diverifikasi')

"== 31. Panel superadmin tidak kosong =="

# Superadmin tidak terikat satu kantor (users.location_id NULL). Nilai itu
# pernah dicor ke 0 dan diteruskan ke query, sehingga seluruh panelnya
# mencari location_id = 0 dan setiap halaman tampil kosong.
$panelSuper = @{}
$panelAdmin = @{}

foreach ($path in @('/admin', '/admin/antrean', '/admin/rekap', '/admin/petugas')) {
    $panelSuper[$path] = (Invoke-WebRequest -Uri "$base$path" -WebSession $sadmin.session -UseBasicParsing).Content
    $panelAdmin[$path] = (Invoke-WebRequest -Uri "$base$path" -WebSession $adminLokasi.session -UseBasicParsing).Content
}

function JumlahKode([string]$html) { return ([regex]::Matches($html, 'TCM-[A-Z0-9]{5}')).Count }

Check 'ringkasan superadmin memuat antrean' ((JumlahKode $panelSuper['/admin']) -gt 0)
Check 'daftar antrean superadmin terisi' ((JumlahKode $panelSuper['/admin/antrean']) -gt 0)
Check 'superadmin melihat paling tidak sebanyak admin lokasi' `
    ((JumlahKode $panelSuper['/admin/antrean']) -ge (JumlahKode $panelAdmin['/admin/antrean']))
Check 'daftar petugas superadmin terisi' ($panelSuper['/admin/petugas'] -match 'petugas1@tcm\.test')
# Hanya sel tanggal di tabel rekap yang dihitung. Judul halaman juga memuat
# tanggal hari ini, dan mencocokkannya ke seluruh halaman membuat setiap
# rekap yang memuat hari ini terbaca seperti data ganda.
$tanggalRekap = [regex]::Matches($panelSuper['/admin/rekap'], '<th scope="row">(\d{1,2} \w+ \d{4})</th>') |
    ForEach-Object { $_.Groups[1].Value }
Check 'rekap superadmin punya baris tanggal' ($tanggalRekap.Count -gt 0)
Check 'rekap superadmin tidak menampilkan tanggal ganda' `
    (($tanggalRekap | Group-Object | Where-Object { $_.Count -gt 1 }).Count -eq 0) `
    "($($tanggalRekap.Count) baris)"

$eksporSuper = Invoke-WebRequest -Uri "$base/admin/rekap/export?periode=bulan" -WebSession $sadmin.session -UseBasicParsing
Check 'ekspor CSV superadmin jalan' ($eksporSuper.StatusCode -eq 200 -and $eksporSuper.Content -match 'Tanggal')

"== 32. Kategori landmark tidak berubah sendiri =="

# Formulir landmark dulu hanya memuat 9 dari 24 kategori. Untuk kantor di
# luar daftar itu tidak ada <option> yang cocok, peramban memilih opsi
# pertama, dan menyimpan tanpa mengubah apa pun mengganti kategorinya.
$halLandmark = Invoke-WebRequest -Uri "$base/admin/landmark/14/deskripsi" -WebSession $sadmin.session -UseBasicParsing
$pilihan = ([regex]::Match($halLandmark.Content, '(?s)<select name="category".*?</select>')).Value

Check 'pilihan kategori selengkap enum basis data' `
    ((([regex]::Matches($pilihan, 'value="([^"]+)"')).Count) -eq 24) "($(([regex]::Matches($pilihan,'value="([^"]+)"')).Count) opsi)"
Check 'kategori kantor yang dibuka ikut terpilih' ($pilihan -match 'value="perpustakaan"[^>]*selected')

$formLandmark = ([regex]::Match($halLandmark.Content, '(?s)<form[^>]*action="[^"]*/admin/landmark/simpan"[^>]*>(.*?)</form>')).Groups[1].Value
$kirimLandmark = @()

foreach ($i in [regex]::Matches($formLandmark, '<input[^>]*>')) {
    $tag = $i.Value
    if ($tag -notmatch 'name="([^"]+)"') { continue }
    $n = $Matches[1]
    $v = if ($tag -match 'value="([^"]*)"') { $Matches[1] } else { '' }
    if ($tag -match 'type="checkbox"' -and $tag -notmatch '\schecked') { continue }
    $kirimLandmark += , @($n, [System.Net.WebUtility]::HtmlDecode($v))
}

foreach ($sel in [regex]::Matches($formLandmark, '(?s)<select[^>]*name="([^"]+)"[^>]*>(.*?)</select>')) {
    $n = $sel.Groups[1].Value
    $opt = [regex]::Match($sel.Groups[2].Value, '<option value="([^"]+)"[^>]*selected')
    $v = if ($opt.Success) { $opt.Groups[1].Value } else { ([regex]::Match($sel.Groups[2].Value, 'value="([^"]+)"')).Groups[1].Value }
    $kirimLandmark += , @($n, $v)
}

foreach ($t in [regex]::Matches($formLandmark, '(?s)<textarea[^>]*name="([^"]+)"[^>]*>(.*?)</textarea>')) {
    $kirimLandmark += , @($t.Groups[1].Value, [System.Net.WebUtility]::HtmlDecode($t.Groups[2].Value))
}

$simpanLandmark = Invoke-WebRequest -Uri "$base/admin/landmark/simpan" -Method Post -WebSession $sadmin.session -UseBasicParsing `
    -ContentType 'application/x-www-form-urlencoded' -Body (Urlencode $kirimLandmark)
Check 'landmark tersimpan ulang' ($simpanLandmark.Content -match 'diperbarui')

$sesudah = Invoke-WebRequest -Uri "$base/admin/landmark/14/deskripsi" -WebSession $sadmin.session -UseBasicParsing
Check 'kategori tetap perpustakaan setelah disimpan' `
    (([regex]::Match($sesudah.Content, '(?s)<select name="category".*?</select>')).Value -match 'value="perpustakaan"[^>]*selected')

$kategoriAsing = $kirimLandmark | ForEach-Object { if ($_[0] -eq 'category') { , @('category', 'bukan-kategori') } else { , $_ } }
$tolakKategori = Invoke-WebRequest -Uri "$base/admin/landmark/simpan" -Method Post -WebSession $sadmin.session -UseBasicParsing `
    -ContentType 'application/x-www-form-urlencoded' -Body (Urlencode $kategoriAsing)
Check 'kategori di luar daftar ditolak' ($tolakKategori.Content -match 'Periksa kembali isian|Kategori')

"== 33. Antrean tidak bisa direbut =="

# $queue dibaca saat halaman dibuka dan baru dipakai beberapa saat kemudian.
# Tanpa syarat status pada UPDATE-nya, petugas yang menekan "Terima"
# belakangan menimpa officer_id milik yang pertama tanpa pesan apa pun.
# Skenarionya hanya bisa disusun di dalam PHP: yang diuji adalah jeda
# antara membaca baris dan menulisnya, yang lewat HTTP tidak bisa diatur.
$bootstrap = ($proyek -replace '\\', '/') + '/bootstrap.php'

$skripRebutan = @"
require '$bootstrap';
use App\Core\DB;
use App\Models\Queue;
use App\Services\QueueService;
`$p = DB::all("SELECT id, location_id FROM users WHERE role = 'officer' ORDER BY id LIMIT 2");
`$kode = DB::column("SELECT code FROM queues WHERE status = 'baru' AND location_id = ? LIMIT 1", [`$p[0]['location_id']]);
if (`$kode === null) { echo 'LEWAT:pemilik-tetap'; exit(0); }
`$basi = Queue::findForLocation(`$kode, (int) `$p[0]['location_id']);
QueueService::changeStatus(Queue::findForLocation(`$kode, (int) `$p[1]['location_id']), 'ditinjau', (int) `$p[1]['id'], 'officer', 'uji rebutan B');
try { QueueService::changeStatus(`$basi, 'ditinjau', (int) `$p[0]['id'], 'officer', 'uji rebutan A'); `$hasil = 'TEREBUT'; }
catch (Throwable `$e) { `$hasil = 'DITOLAK'; }
`$akhir = (int) DB::column('SELECT officer_id FROM queues WHERE code = ?', [`$kode]);
echo `$hasil, ':', (`$akhir === (int) `$p[1]['id'] ? 'pemilik-tetap' : 'pemilik-berubah');
DB::update('queues', ['status' => 'baru', 'officer_id' => null, 'accepted_at' => null], 'code = :c', ['c' => `$kode]);
DB::run("DELETE FROM queue_status_logs WHERE note LIKE 'uji rebutan%'");
"@

$rebutan = ((& $Php -r $skripRebutan) -join '').Trim()
Check 'klaim dari salinan basi ditolak' ($rebutan -like 'DITOLAK*' -or $rebutan -like 'LEWAT*') "($rebutan)"
Check 'pemilik antrean tidak berubah' ($rebutan -like '*pemilik-tetap') "($rebutan)"

"== 34. Ganti email lewat profil =="

# Mengubah email tidak boleh langsung berlaku: akun akan tetap bertanda
# terverifikasi untuk alamat yang belum pernah dibuktikan, dan satu salah
# ketik memutus satu-satunya jalan masuk pemiliknya.
$wargaSesi = Login 'warga3@tcm.test' 'tuban12345'
$emailBaru = "warga3-pindah-$([guid]::NewGuid().ToString('N').Substring(0,6))@contoh.test"
$profil = Invoke-WebRequest -Uri "$base/akun/profil" -WebSession $wargaSesi.session -UseBasicParsing

$isianProfil = @{
    name = 'Agus Winarno'; email = $emailBaru; phone = '081233334444'; nik = ''
    address = ''; gender = ''; birth_date = ''; district_id = ''
    password = 'sandilain123'; password_confirmation = 'sandilain123'
}

$tolakSandi = Follow (Invoke-WebRequest -Uri "$base/akun/profil" -Method Post -WebSession $wargaSesi.session -UseBasicParsing `
    -Body ($isianProfil + @{ _token = (Token $profil.Content); password_current = 'salah-sekali' })) $wargaSesi.session
Check 'sandi lama salah ditolak' ($tolakSandi.Content -match 'Sandi saat ini tidak cocok')

# Yang penting bukan hanya pesannya: sebelumnya profil sudah tersimpan
# lebih dulu, jadi permintaan yang dinyatakan gagal tetap mengubah data.
$masihLama = Login 'warga3@tcm.test' 'tuban12345'
Check 'tidak ada yang tersimpan saat ditolak' ($masihLama.page.Content -match 'Antrean saya')

$profil2 = Invoke-WebRequest -Uri "$base/akun/profil" -WebSession $wargaSesi.session -UseBasicParsing
$simpanProfil = Follow (Invoke-WebRequest -Uri "$base/akun/profil" -Method Post -WebSession $wargaSesi.session -UseBasicParsing `
    -Body ($isianProfil + @{ _token = (Token $profil2.Content); password_current = 'tuban12345' })) $wargaSesi.session
Check 'ganti email minta konfirmasi dulu' ($simpanProfil.Content -match 'Konfirmasi dikirim ke')
Check 'konfirmasi dikirim ke alamat baru' ((Sink 'kepada') -eq $emailBaru)

$lamaMasihBisa = Login 'warga3@tcm.test' 'sandilain123'
Check 'alamat lama masih bisa masuk' ($lamaMasihBisa.page.Content -match 'Antrean saya')
$baruBelumBisa = Login $emailBaru 'sandilain123'
Check 'alamat baru belum berlaku' (-not ($baruBelumBisa.page.Content -match 'Antrean saya'))

$sesiKonf = $null
$konfirmasi = Follow (Invoke-WebRequest -Uri (Sink 'tautan') -SessionVariable sesiKonf -UseBasicParsing) $sesiKonf
Check 'konfirmasi penggantian diterima' ($konfirmasi.Content -match [regex]::Escape($emailBaru))

$baruBisa = Login $emailBaru 'sandilain123'
Check 'alamat baru berlaku setelah dikonfirmasi' ($baruBisa.page.Content -match 'Antrean saya')
$lamaSudahTidak = Login 'warga3@tcm.test' 'sandilain123'
Check 'alamat lama tidak berlaku lagi' (-not ($lamaSudahTidak.page.Content -match 'Antrean saya'))

# Kembalikan akun uji ke keadaan seperti seeder.
$null = & $Php -r @"
require '$bootstrap';
App\Core\DB::update('users', ['email' => 'warga3@tcm.test', 'pending_email' => null, 'phone' => null,
    'password_hash' => password_hash('tuban12345', PASSWORD_DEFAULT)], 'id = :id', ['id' => 8]);
App\Core\DB::run('DELETE FROM email_verifications WHERE user_id = 8');
"@

MatikanSink

"== 35. Alur antrean menolak isian yang tidak sah =="

# Formulir memang menandai hari tutup dan hanya menawarkan enam sesi, tetapi
# itu hanya tampilan. Yang diuji di sini permintaan POST yang menyebut nilai
# di luar itu — persis yang dilakukan skrip atau peramban yang disetel orang.
function AmbilAntrean([hashtable]$ubah, [string]$slug = 'polsek-bulu') {
    $isi = @{
        service_id = '8'; slot_time = '09:00'; note_user = ''; queue_date = (Get-Date).ToString('yyyy-MM-dd')
        nama = 'Penguji Alur'; email = 'uji-alur@contoh.test'; telepon = '081200001234'; nik = ''; district_id = ''
    }
    foreach ($k in $ubah.Keys) { $isi[$k] = $ubah[$k] }

    $sesi = $null
    $form = Invoke-WebRequest -Uri "$base/lokasi/$slug/daftar" -SessionVariable sesi -UseBasicParsing
    $hasil = Follow (Invoke-WebRequest -Uri "$base/lokasi/$slug/daftar" -Method Post -WebSession $sesi -UseBasicParsing `
        -Body ($isi + @{ _token = (Token $form.Content) })) $sesi

    if ($hasil.Content -match '(TCM-[A-Z0-9]{5})') { return "DITERIMA:$($Matches[1])" }
    if ($hasil.Content -match 'class="field-error">([^<]+)') { return "DITOLAK:$(($Matches[1]).Trim())" }
    return 'TIDAK-JELAS'
}

# Cari hari tutup terdekat dari jadwal yang ditampilkan halamannya sendiri.
$halJadwal = Invoke-WebRequest -Uri "$base/lokasi/polsek-bulu" -UseBasicParsing
$hariTutup = ''
foreach ($m in [regex]::Matches($halJadwal.Content, '(?s)<th scope="row">([^<]+)</th>\s*<td>\s*Tutup')) {
    $hariTutup = $m.Groups[1].Value.Trim()
    break
}
Check 'jadwal kantor memuat hari tutup' ($hariTutup -ne '') "($hariTutup)"

if ($hariTutup -ne '') {
    $tglTutup = ([datetime]::ParseExact(
        ($hariTutup -replace 'Januari', 'January' -replace 'Februari', 'February' -replace 'Maret', 'March' `
                    -replace 'April', 'April' -replace 'Mei', 'May' -replace 'Juni', 'June' -replace 'Juli', 'July' `
                    -replace 'Agustus', 'August' -replace 'September', 'September' -replace 'Oktober', 'October' `
                    -replace 'November', 'November' -replace 'Desember', 'December'),
        'd MMMM yyyy', [cultureinfo]::GetCultureInfo('en-US'))).ToString('yyyy-MM-dd')

    $hasilTutup = AmbilAntrean @{ queue_date = $tglTutup }
    Check 'antrean pada hari tutup ditolak' ($hasilTutup -like 'DITOLAK*tutup*') "($hasilTutup)"
}

$sesiNgawur = AmbilAntrean @{ slot_time = 'kapan saja' }
Check 'sesi waktu ngawur ditolak' ($sesiNgawur -like 'DITOLAK*') "($sesiNgawur)"

$sesiLuar = AmbilAntrean @{ slot_time = '03:00' }
Check 'sesi di luar daftar ditolak' ($sesiLuar -like 'DITOLAK*') "($sesiLuar)"

$layananLain = AmbilAntrean @{ service_id = '41' }
Check 'layanan kantor lain ditolak' ($layananLain -like 'DITOLAK*tidak tersedia*') "($layananLain)"

"== 36. Lampiran dan berkas yang tertinggal =="

function HitungBerkas() {
    $skrip = "require '$bootstrap';" + @'
$dir = BASE_PATH . '/storage/uploads';
$n = 0;
if (is_dir($dir)) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
        if ($f->isFile()) { $n++; }
    }
}
echo $n;
'@
    return [int](((& $Php -r $skrip) -join '').Trim())
}

$pdfUji = Join-Path $env:TEMP 'wia-lampiran-uji.pdf'
[IO.File]::WriteAllText($pdfUji, "%PDF-1.4`n1 0 obj<</Type/Catalog>>endobj`ntrailer<</Root 1 0 R>>`n%%EOF")
function SalinanPdf { $t = Join-Path $env:TEMP ("lam-$([guid]::NewGuid().ToString('N')).pdf"); Copy-Item -LiteralPath $pdfUji -Destination $t; return (Get-Item -LiteralPath $t) }

$sebelumBerkas = HitungBerkas
$sesiLampiran = $null
$formLampiran = Invoke-WebRequest -Uri "$base/lokasi/polsek-bulu/daftar" -SessionVariable sesiLampiran -UseBasicParsing

# berkas[1] dan berkas[3] milik layanan di kantor lain.
$kirimLampiran = Follow (Invoke-WebRequest -Uri "$base/lokasi/polsek-bulu/daftar" -Method Post -WebSession $sesiLampiran -UseBasicParsing -Form @{
    _token = (Token $formLampiran.Content); service_id = '8'; queue_date = (Get-Date).ToString('yyyy-MM-dd')
    slot_time = '09:00'; note_user = ''
    nama = 'Penguji Lampiran'; email = 'uji-lampiran@contoh.test'; telepon = '081200005678'; nik = ''; district_id = ''
    'berkas[1]' = (SalinanPdf); 'berkas[3]' = (SalinanPdf)
}) $sesiLampiran

Check 'lampiran milik layanan lain ditolak' ($kirimLampiran.Content -match 'bukan syarat layanan ini')
Check 'galat basis data tidak bocor ke warga' ($kirimLampiran.Content -notmatch 'SQLSTATE|CONSTRAINT|FOREIGN KEY')
Check 'berkas yang ditolak tidak tertinggal' ((HitungBerkas) -eq $sebelumBerkas) "($sebelumBerkas -> $(HitungBerkas))"

"== 37. Tarif yang belum pasti tidak dijanjikan gratis =="

$tarifBelum = AmbilAntrean @{ service_id = '41'; nama = 'Penguji Tarif'; email = 'uji-tarif@contoh.test' } 'pasar-plumpang'
Check 'antrean layanan bertarif belum pasti dibuat' ($tarifBelum -like 'DITERIMA*') "($tarifBelum)"

if ($tarifBelum -like 'DITERIMA*') {
    $kodeTarif = $tarifBelum.Split(':')[1]
    $statusBayar = ((& $Php -r "require '$bootstrap'; echo App\Core\DB::column('SELECT payment_status FROM queues WHERE code = ?', ['$kodeTarif']);") -join '').Trim()
    Check 'statusnya belum_pasti, bukan tidak_perlu' ($statusBayar -eq 'belum_pasti') "($statusBayar)"
}

# Bersihkan antrean dan akun tamu yang dibuat bagian ini.
$null = & $Php -r @"
require '$bootstrap';
App\Core\DB::run("DELETE FROM queues WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'uji-%@contoh.test')");
App\Core\DB::run("DELETE FROM users WHERE email LIKE 'uji-%@contoh.test'");
"@

''
'--------------------------------------'
"lolos: $pass   gagal: $fail   (antrean uji: $kode, tamu: $kodeTamu)"
if ($fail -gt 0) { exit 1 }
