<?php

declare(strict_types=1);

/**
 * Seeder Tuban Civic Map.
 *
 *   php database/seed.php           -> hanya berjalan bila basis data masih kosong
 *   php database/seed.php --fresh   -> kosongkan dulu, lalu isi ulang
 *
 * CATATAN DATA
 * Nama kecamatan dan kantor layanan memakai nama yang benar-benar ada di
 * Kabupaten Tuban. Yang TIDAK resmi dan harus diganti sebelum dipakai sungguhan:
 *   - koordinat: perkiraan pusat wilayah, bukan survei
 *   - alamat, telepon, email: contoh (domain contoh.go.id)
 *   - jenis layanan, biaya, dan syarat berkas: pola umum, perlu dicocokkan
 *     dengan standar pelayanan masing-masing kantor
 *   - foto gedung: belum ada; unggah lewat panel admin
 */

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Cache;
use App\Core\CodeGenerator;
use App\Core\DB;

$fresh = in_array('--fresh', $argv, true);

$sudahAda = (int) DB::column('SELECT COUNT(*) FROM districts');

if ($sudahAda > 0 && !$fresh) {
    fwrite(STDERR, "Basis data sudah berisi {$sudahAda} kecamatan.\n");
    fwrite(STDERR, "Jalankan ulang dengan --fresh bila memang ingin menimpa seluruh isinya.\n");
    exit(1);
}

if ($fresh) {
    echo "Mengosongkan tabel…\n";

    DB::run('SET FOREIGN_KEY_CHECKS = 0');

    foreach ([
        'login_attempts', 'audit_logs', 'payments', 'messages', 'queue_status_logs',
        'queue_documents', 'queues', 'service_requirements', 'services', 'users',
        'locations', 'districts',
    ] as $table) {
        DB::run('TRUNCATE TABLE `' . $table . '`');
    }

    DB::run('SET FOREIGN_KEY_CHECKS = 1');
}

/* ============================================================== kecamatan */

// [nama, bujur, lintang, ringkasan]  — koordinat perkiraan pusat kecamatan.
$kecamatan = [
    ['Tuban', 112.0649, -6.8976, 'Pusat pemerintahan kabupaten dan simpul layanan terlengkap.'],
    ['Semanding', 112.0580, -6.9400, 'Berbatasan langsung dengan kota, padat permukiman.'],
    ['Palang', 112.1350, -6.9120, 'Wilayah pesisir timur dengan pelabuhan rakyat.'],
    ['Jenu', 111.9700, -6.8700, 'Kawasan industri dan pesisir barat kota.'],
    ['Merakurak', 111.9800, -6.9300, 'Daerah kapur dengan sentra pertanian.'],
    ['Kerek', 111.9200, -6.9500, 'Dikenal dengan tenun gedog dan kawasan industri semen.'],
    ['Tambakboyo', 111.8300, -6.8800, 'Pesisir barat, pelabuhan perikanan.'],
    ['Jatirogo', 111.6100, -6.8900, 'Pusat kegiatan wilayah barat Kabupaten Tuban.'],
    ['Bancar', 111.7100, -6.8400, 'Pesisir paling barat, berbatasan dengan Rembang.'],
    ['Bangilan', 111.6600, -7.0300, 'Wilayah selatan barat dengan lahan pertanian luas.'],
    ['Senori', 111.7200, -7.0100, 'Perbukitan selatan, sentra palawija.'],
    ['Singgahan', 111.7900, -7.0000, 'Wilayah tengah selatan, dilintasi jalur provinsi.'],
    ['Parengan', 111.8600, -7.0500, 'Kawasan hutan jati dan pertanian.'],
    ['Montong', 111.9500, -7.0200, 'Perbukitan tengah dengan sentra buah.'],
    ['Rengel', 112.0350, -7.0620, 'Selatan kabupaten, dekat aliran Bengawan Solo.'],
    ['Soko', 111.9600, -7.1000, 'Lumbung padi di selatan kabupaten.'],
    ['Plumpang', 112.0700, -7.0300, 'Simpul jalan selatan menuju Babat.'],
    ['Widang', 112.1700, -7.0600, 'Perbatasan tenggara, dilalui Bengawan Solo.'],
    ['Grabagan', 112.0100, -7.0600, 'Kecamatan pemekaran di bagian tengah selatan.'],
    ['Kenduruan', 111.6800, -6.9600, 'Wilayah barat berbukit, berbatasan dengan Blora.'],
];

echo "Menyimpan kecamatan…\n";

$districtId = [];

foreach ($kecamatan as [$nama, $lng, $lat, $ringkas]) {
    $slug = slugify($nama);

    $districtId[$slug] = DB::insert('districts', [
        'name' => $nama,
        'slug' => $slug,
        'center_lng' => $lng,
        'center_lat' => $lat,
        'zoom' => 13.00,
        'pitch' => 55.0,
        'summary' => $ringkas,
    ]);
}

/* ================================================= katalog layanan per jenis */

/*
 * kategori => [ [nama layanan, biaya, dasar tarif, hari kerja, [ [label berkas, wajib, unggah], … ] ] ]
 *
 * BIAYA — baca ini sebelum mengubah angkanya:
 *
 * - Angka hanya diisi bila tarifnya ditetapkan peraturan tingkat nasional dan
 *   dasar hukumnya bisa disebut. Kolom "dasar tarif" wajib ikut terisi.
 * - 0 berarti layanan itu memang gratis menurut peraturan, bukan berarti
 *   tarifnya belum diketahui.
 * - null berarti BELUM DIKONFIRMASI — tarif yang ditetapkan Perda Kabupaten
 *   Tuban, SK ketua pengadilan, atau yang besarnya bergantung objek
 *   (nilai tanah, jenis kendaraan, kelas rawat). Halaman layanan menampilkan
 *   "Perlu dikonfirmasi", bukan "Gratis". Jangan diisi angka karangan:
 *   menjanjikan layanan berbayar sebagai cuma-cuma merugikan warga yang datang
 *   tanpa membawa uang.
 *
 * Tarif PNBP berubah bila peraturannya direvisi. Periksa ulang sebelum dipakai
 * di lingkungan sungguhan.
 */

$adminduk = 'UU 24/2013 Pasal 79A — dokumen kependudukan tidak dipungut biaya';
$perdaTuban = 'Tarif Perda Kabupaten Tuban — perlu dikonfirmasi ke instansi';
$objek = 'Besarnya bergantung objek layanan — perlu dikonfirmasi ke instansi';

$ktp = [['Fotokopi KK', 1, 0], ['KTP lama (bila ada)', 0, 0]];
$pengantar = [['Surat pengantar RT/RW', 1, 1], ['Fotokopi KK dan KTP', 1, 0]];

$templates = [
    'kecamatan' => [
        ['Surat pindah penduduk', 0, $adminduk, 3, [
            ['Fotokopi KK dan KTP', 1, 0],
            ['Surat pengantar RT/RW', 1, 1],
            ['Alamat tujuan lengkap', 1, 0],
        ]],
        ['Legalisasi dokumen kependudukan', 0, $adminduk, 1, [
            ['Dokumen asli', 1, 0],
            ['Fotokopi yang akan dilegalisasi', 1, 1],
        ]],
        ['Rekomendasi izin keramaian', null, $perdaTuban, 3, [
            ['Surat permohonan', 1, 1],
            ['Fotokopi KTP penanggung jawab', 1, 0],
            ['Denah lokasi kegiatan', 0, 0],
        ]],
        ['Surat keterangan domisili usaha', null, $perdaTuban, 3, [
            ['Surat pengantar desa/kelurahan', 1, 1],
            ['Fotokopi KTP pemilik usaha', 1, 0],
        ]],
        ['Legalisasi proposal bantuan', null, $perdaTuban, 2, [
            ['Proposal lengkap', 1, 1],
            ['Surat pengantar desa/kelurahan', 1, 0],
        ]],
    ],

    'kelurahan' => [
        ['Surat pengantar RT/RW ke kecamatan', 0, $adminduk, 1, $pengantar],
        ['Surat keterangan domisili', 0, $adminduk, 1, $pengantar],
        ['Surat keterangan usaha', null, $perdaTuban, 2, [
            ['Surat pengantar RT/RW', 1, 1],
            ['Fotokopi KTP dan KK', 1, 0],
            ['Foto tempat usaha', 0, 1],
        ]],
        ['Surat keterangan tidak mampu', 0, $adminduk, 2, [
            ['Surat pengantar RT/RW', 1, 1],
            ['Fotokopi KK dan KTP', 1, 0],
        ]],
        ['Surat keterangan ahli waris', null, $perdaTuban, 5, [
            ['Fotokopi KK dan KTP seluruh ahli waris', 1, 1],
            ['Akta kematian pewaris', 1, 1],
            ['Surat pengantar RT/RW', 1, 0],
        ]],
    ],

    'dukcapil' => [
        ['Perekaman dan cetak KTP-el', 0, $adminduk, 7, [
            ['Fotokopi KK', 1, 1],
            ['Surat pengantar desa/kelurahan', 0, 0],
        ]],
        ['Kartu Keluarga baru / perubahan', 0, $adminduk, 5, [
            ['KK lama', 1, 1],
            ['Surat pengantar desa/kelurahan', 1, 0],
            ['Buku nikah / akta cerai (bila berubah status)', 0, 1],
        ]],
        ['Akta kelahiran', 0, $adminduk, 7, [
            ['Surat keterangan lahir dari fasilitas kesehatan', 1, 1],
            ['Fotokopi KK dan KTP orang tua', 1, 1],
            ['Buku nikah orang tua', 0, 1],
            ['Fotokopi KTP dua orang saksi', 1, 0],
        ]],
        ['Akta kematian', 0, $adminduk, 5, [
            ['Surat keterangan kematian dari desa/kelurahan', 1, 1],
            ['Fotokopi KK dan KTP almarhum', 1, 1],
            ['Fotokopi KTP pelapor', 1, 0],
        ]],
        ['Kartu Identitas Anak (KIA)', 0, $adminduk, 5, [
            ['Fotokopi akta kelahiran anak', 1, 1],
            ['Fotokopi KK', 1, 0],
            ['Pasfoto anak 2x3', 0, 1],
        ]],
        ['Akta perkawinan (non-muslim)', 0, $adminduk, 10, [
            ['Surat keterangan perkawinan dari pemuka agama', 1, 1],
            ['Fotokopi KK dan KTP kedua mempelai', 1, 1],
            ['Pasfoto berdampingan 4x6', 1, 0],
        ]],
        ['Surat Keterangan Pindah WNI (SKPWNI)', 0, $adminduk, 5, [
            ['Fotokopi KK', 1, 1],
            ['Fotokopi KTP pemohon', 1, 0],
        ]],
    ],

    'puskesmas' => [
        ['Poli umum', null, 'Gratis bagi peserta JKN aktif; tarif umum mengikuti Perda — perlu dikonfirmasi', 0, [
            ['Kartu JKN/BPJS atau KTP', 1, 0],
        ]],
        ['Poli gigi', null, 'Gratis bagi peserta JKN aktif; tarif umum mengikuti Perda — perlu dikonfirmasi', 0, [
            ['Kartu JKN/BPJS atau KTP', 1, 0],
        ]],
        ['Imunisasi anak', 0, 'Program nasional, imunisasi dasar lengkap tidak dipungut biaya', 0, [
            ['Buku KIA', 1, 0],
            ['Fotokopi KK', 0, 0],
        ]],
        ['Surat keterangan sehat', null, $perdaTuban, 0, [
            ['KTP', 1, 0],
        ]],
        ['Pemeriksaan ibu hamil (ANC)', 0, 'Program nasional, pemeriksaan kehamilan di puskesmas tidak dipungut biaya', 0, [
            ['Buku KIA', 1, 0],
            ['Kartu JKN/BPJS', 0, 0],
        ]],
        ['Rujukan ke rumah sakit', 0, 'Mengikuti ketentuan JKN bagi peserta aktif', 1, [
            ['Kartu JKN/BPJS', 1, 0],
            ['Hasil pemeriksaan sebelumnya', 0, 1],
        ]],
    ],

    'rumah_sakit' => [
        ['Pendaftaran rawat jalan', null, 'Mengikuti kelas dan status kepesertaan JKN — perlu dikonfirmasi', 0, [
            ['Kartu JKN/BPJS atau KTP', 1, 0],
            ['Surat rujukan puskesmas', 0, 1],
        ]],
        ['Poliklinik spesialis', null, 'Mengikuti kelas dan status kepesertaan JKN — perlu dikonfirmasi', 0, [
            ['Surat rujukan', 1, 1],
            ['Kartu JKN/BPJS', 1, 0],
        ]],
        ['Permintaan resume medis', null, $perdaTuban, 5, [
            ['Fotokopi KTP pasien', 1, 1],
            ['Surat kuasa bila diwakilkan', 0, 1],
        ]],
        ['Surat keterangan rawat inap', null, $perdaTuban, 2, [
            ['Fotokopi KTP pasien', 1, 0],
        ]],
    ],

    'samsat' => [
        ['Pengesahan STNK tahunan', null, 'PNBP pengesahan nihil; yang dibayar PKB dan SWDKLLJ, besarnya bergantung kendaraan', 0, [
            ['STNK asli', 1, 0],
            ['BPKB asli', 1, 0],
            ['KTP pemilik sesuai STNK', 1, 0],
        ]],
        ['Perpanjangan STNK 5 tahun (roda 2/3)', 100000, 'PP 76/2020 — PNBP penerbitan STNK roda 2/3', 1, [
            ['STNK asli', 1, 0],
            ['BPKB asli', 1, 0],
            ['KTP pemilik', 1, 0],
            ['Kendaraan untuk cek fisik', 1, 0],
        ]],
        ['Perpanjangan STNK 5 tahun (roda 4 atau lebih)', 200000, 'PP 76/2020 — PNBP penerbitan STNK roda 4+', 1, [
            ['STNK asli', 1, 0],
            ['BPKB asli', 1, 0],
            ['KTP pemilik', 1, 0],
            ['Kendaraan untuk cek fisik', 1, 0],
        ]],
        ['Penerbitan TNKB (roda 2/3)', 60000, 'PP 76/2020 — PNBP penerbitan TNKB roda 2/3', 1, [
            ['STNK asli', 1, 0],
        ]],
        ['Penerbitan TNKB (roda 4 atau lebih)', 100000, 'PP 76/2020 — PNBP penerbitan TNKB roda 4+', 1, [
            ['STNK asli', 1, 0],
        ]],
        ['Mutasi kendaraan keluar daerah', null, 'PNBP mutasi ditambah biaya cek fisik — perlu dikonfirmasi', 7, [
            ['BPKB asli dan fotokopi', 1, 1],
            ['STNK asli', 1, 0],
            ['KTP pemilik', 1, 0],
            ['Kuitansi jual beli bermeterai', 0, 1],
        ]],
    ],

    'polsek' => [
        ['Penerbitan SKCK', 30000, 'PP 76/2020 — PNBP penerbitan SKCK', 1, [
            ['Fotokopi KTP', 1, 1],
            ['Fotokopi KK', 1, 1],
            ['Fotokopi akta kelahiran', 1, 0],
            ['Pasfoto 4x6 latar merah, 6 lembar', 1, 0],
            ['Rumus sidik jari', 1, 0],
        ]],
        ['Surat keterangan kehilangan', 0, 'Tidak dipungut biaya', 0, [
            ['KTP pelapor', 1, 0],
            ['Keterangan barang yang hilang', 1, 0],
        ]],
        ['Laporan polisi', 0, 'Tidak dipungut biaya', 0, [
            ['KTP pelapor', 1, 0],
            ['Bukti pendukung bila ada', 0, 1],
        ]],
        ['Surat izin keramaian', 0, 'Tidak dipungut biaya', 3, [
            ['Surat permohonan', 1, 1],
            ['Rekomendasi kecamatan', 1, 1],
            ['Fotokopi KTP penanggung jawab', 1, 0],
        ]],
        ['Pengaduan masyarakat', 0, 'Tidak dipungut biaya', 0, [
            ['KTP pengadu', 1, 0],
            ['Kronologi tertulis', 0, 1],
        ]],
    ],

    'kua' => [
        ['Pendaftaran nikah di KUA (hari dan jam kerja)', 0, 'PP 48/2014 — nikah di KUA pada hari dan jam kerja tidak dipungut biaya', 10, [
            ['Surat pengantar nikah dari desa/kelurahan (N1-N4)', 1, 1],
            ['Fotokopi KTP dan KK kedua calon', 1, 1],
            ['Fotokopi akta kelahiran', 1, 0],
            ['Pasfoto 2x3 latar biru, 4 lembar', 1, 0],
        ]],
        ['Pendaftaran nikah di luar KUA atau di luar jam kerja', 600000, 'PP 48/2014 — tarif PNBP nikah di luar KUA', 10, [
            ['Surat pengantar nikah dari desa/kelurahan (N1-N4)', 1, 1],
            ['Fotokopi KTP dan KK kedua calon', 1, 1],
            ['Bukti setor PNBP ke kas negara', 1, 1],
        ]],
        ['Legalisasi buku nikah', 0, 'Tidak dipungut biaya', 1, [
            ['Buku nikah asli', 1, 0],
            ['Fotokopi yang akan dilegalisasi', 1, 1],
        ]],
        ['Duplikat buku nikah', 0, 'Tidak dipungut biaya', 7, [
            ['Surat keterangan kehilangan dari kepolisian', 1, 1],
            ['Fotokopi KTP dan KK', 1, 0],
        ]],
        ['Rekomendasi nikah pindah KUA', 0, 'Tidak dipungut biaya', 3, [
            ['Surat pengantar desa/kelurahan', 1, 1],
            ['Fotokopi KTP dan KK', 1, 0],
        ]],
        ['Bimbingan perkawinan calon pengantin', 0, 'Program Kementerian Agama, tidak dipungut biaya', 0, [
            ['Bukti pendaftaran nikah', 1, 0],
        ]],
    ],

    'pengadilan' => [
        ['Pendaftaran gugatan perdata', null, 'Panjar biaya perkara ditetapkan SK Ketua Pengadilan dan bergantung radius pemanggilan', 0, [
            ['Surat gugatan', 1, 1],
            ['Fotokopi KTP penggugat', 1, 1],
            ['Bukti-bukti pendukung', 0, 1],
        ]],
        ['Permohonan salinan putusan', null, 'Biaya salinan per lembar ditetapkan SK Ketua Pengadilan', 3, [
            ['Fotokopi KTP pemohon', 1, 1],
            ['Nomor perkara', 1, 0],
        ]],
        ['Legalisasi dokumen', null, 'Tarif ditetapkan SK Ketua Pengadilan', 1, [
            ['Dokumen asli', 1, 0],
            ['Fotokopi dokumen', 1, 1],
        ]],
        ['Permohonan eksekusi putusan', null, 'Panjar eksekusi ditetapkan SK Ketua Pengadilan', 0, [
            ['Salinan putusan berkekuatan hukum tetap', 1, 1],
            ['Fotokopi KTP pemohon', 1, 0],
        ]],
    ],

    'pengadilan_agama' => [
        ['Pendaftaran cerai gugat / cerai talak', null, 'Panjar biaya perkara ditetapkan SK Ketua Pengadilan Agama', 0, [
            ['Surat gugatan atau permohonan', 1, 1],
            ['Buku nikah asli', 1, 0],
            ['Fotokopi KTP dan KK', 1, 1],
        ]],
        ['Itsbat nikah', null, 'Panjar biaya perkara ditetapkan SK Ketua Pengadilan Agama', 0, [
            ['Surat permohonan', 1, 1],
            ['Surat keterangan dari KUA', 1, 1],
            ['Fotokopi KTP dan KK', 1, 0],
        ]],
        ['Dispensasi nikah', null, 'Panjar biaya perkara ditetapkan SK Ketua Pengadilan Agama', 0, [
            ['Surat permohonan orang tua', 1, 1],
            ['Fotokopi akta kelahiran calon', 1, 1],
            ['Surat penolakan dari KUA', 1, 1],
        ]],
        ['Permohonan salinan putusan', null, 'Biaya salinan per lembar ditetapkan SK Ketua Pengadilan Agama', 3, [
            ['Fotokopi KTP pemohon', 1, 1],
            ['Nomor perkara', 1, 0],
        ]],
        ['Penetapan ahli waris', null, 'Panjar biaya perkara ditetapkan SK Ketua Pengadilan Agama', 0, [
            ['Surat permohonan', 1, 1],
            ['Akta kematian pewaris', 1, 1],
            ['Fotokopi KK seluruh ahli waris', 1, 0],
        ]],
    ],

    'kejaksaan' => [
        ['Pengambilan barang bukti', 0, 'Tidak dipungut biaya', 3, [
            ['Surat putusan atau penetapan', 1, 1],
            ['Fotokopi KTP pemohon', 1, 0],
        ]],
        ['Pembayaran denda tilang', null, 'Besarnya mengikuti putusan hakim', 0, [
            ['Surat tilang (biru atau merah)', 1, 0],
            ['Fotokopi KTP', 1, 0],
        ]],
        ['Konsultasi hukum (Pos Pelayanan Hukum)', 0, 'Tidak dipungut biaya', 0, [
            ['Fotokopi KTP', 1, 0],
        ]],
        ['Permohonan surat keterangan tidak sedang berperkara', 0, 'Tidak dipungut biaya', 5, [
            ['Surat permohonan', 1, 1],
            ['Fotokopi KTP', 1, 0],
        ]],
    ],

    'pertanahan' => [
        ['Pengecekan sertipikat', 50000, 'PP 128/2015 — tarif pengecekan sertipikat per bidang', 1, [
            ['Sertipikat asli', 1, 0],
            ['Fotokopi KTP pemohon', 1, 1],
            ['Surat kuasa bila diwakilkan', 0, 1],
        ]],
        ['Peralihan hak (jual beli)', null, 'PP 128/2015 — 1‰ dari nilai tanah ditambah biaya tetap, bergantung objek', 14, [
            ['Sertipikat asli', 1, 0],
            ['Akta jual beli dari PPAT', 1, 1],
            ['Fotokopi KTP penjual dan pembeli', 1, 1],
            ['Bukti lunas PBB tahun berjalan', 1, 1],
        ]],
        ['Pengukuran dan pemetaan bidang tanah', null, $objek, 30, [
            ['Sertipikat atau alas hak', 1, 1],
            ['Fotokopi KTP pemohon', 1, 0],
            ['Surat pernyataan batas disetujui tetangga', 1, 1],
        ]],
        ['Pendaftaran hak tanggungan', null, $objek, 7, [
            ['Sertipikat asli', 1, 0],
            ['Akta pemberian hak tanggungan', 1, 1],
        ]],
        ['Penggantian sertipikat hilang', null, $objek, 60, [
            ['Surat keterangan kehilangan dari kepolisian', 1, 1],
            ['Fotokopi KTP pemilik', 1, 1],
            ['Bukti pengumuman di surat kabar', 1, 1],
        ]],
    ],

    'bpjs' => [
        ['Pendaftaran peserta mandiri', 0, 'Pendaftaran tidak dipungut biaya; iuran bulanan mengikuti kelas yang dipilih', 1, [
            ['Fotokopi KK', 1, 1],
            ['Fotokopi KTP seluruh anggota keluarga', 1, 1],
            ['Nomor rekening bank', 1, 0],
        ]],
        ['Perubahan data kepesertaan', 0, 'Tidak dipungut biaya', 1, [
            ['Kartu JKN/BPJS', 1, 0],
            ['Dokumen pendukung perubahan', 1, 1],
        ]],
        ['Pindah fasilitas kesehatan tingkat pertama', 0, 'Tidak dipungut biaya', 1, [
            ['Kartu JKN/BPJS', 1, 0],
            ['Fotokopi KTP', 1, 0],
        ]],
        ['Cetak ulang kartu', 0, 'Tidak dipungut biaya', 1, [
            ['Fotokopi KTP', 1, 0],
        ]],
    ],

    'perizinan' => [
        ['Nomor Induk Berusaha (NIB)', 0, 'PP 5/2021 — penerbitan NIB lewat OSS tidak dipungut biaya', 3, [
            ['Fotokopi KTP pemilik usaha', 1, 1],
            ['NPWP', 1, 1],
            ['Fotokopi akta pendirian (badan usaha)', 0, 1],
        ]],
        ['Izin Mendirikan Bangunan / PBG', null, 'Retribusi PBG dihitung dari luas dan fungsi bangunan — perlu dikonfirmasi', 30, [
            ['Gambar rencana bangunan', 1, 1],
            ['Fotokopi sertipikat tanah', 1, 1],
            ['Fotokopi KTP pemohon', 1, 0],
            ['Bukti lunas PBB', 1, 1],
        ]],
        ['Izin usaha mikro dan kecil (IUMK)', 0, 'Tidak dipungut biaya', 5, [
            ['Fotokopi KTP', 1, 1],
            ['Surat pengantar desa/kelurahan', 1, 1],
            ['Pasfoto 4x6', 0, 0],
        ]],
        ['Izin reklame', null, $perdaTuban, 7, [
            ['Desain dan ukuran reklame', 1, 1],
            ['Surat persetujuan pemilik lahan', 1, 1],
            ['Fotokopi KTP pemohon', 1, 0],
        ]],
        ['Izin lingkungan (SPPL)', 0, 'Tidak dipungut biaya untuk usaha wajib SPPL', 14, [
            ['Formulir SPPL terisi', 1, 1],
            ['Denah lokasi usaha', 1, 1],
        ]],
    ],

    'perpustakaan' => [
        ['Pendaftaran anggota', 0, 'Tidak dipungut biaya', 1, [
            ['Fotokopi KTP atau kartu pelajar', 1, 1],
            ['Pasfoto 3x4', 1, 0],
        ]],
        ['Peminjaman buku', 0, 'Tidak dipungut biaya; denda keterlambatan mengikuti Perda', 0, [
            ['Kartu anggota', 1, 0],
        ]],
        ['Kunjungan rombongan', 0, 'Tidak dipungut biaya', 3, [
            ['Surat permohonan dari sekolah atau instansi', 1, 1],
        ]],
        ['Layanan bebas pustaka', 0, 'Tidak dipungut biaya', 1, [
            ['Kartu anggota', 1, 0],
            ['Bukti pengembalian seluruh pinjaman', 1, 0],
        ]],
    ],

    'pos' => [
        ['Pengiriman surat dan paket', null, 'Tarif mengikuti berat, tujuan, dan jenis layanan', 0, [
            ['Identitas pengirim', 1, 0],
            ['Alamat tujuan lengkap', 1, 0],
        ]],
        ['Pengambilan wesel', null, 'Biaya mengikuti ketentuan PT Pos Indonesia', 0, [
            ['KTP penerima', 1, 0],
            ['Nomor resi wesel', 1, 0],
        ]],
        ['Pembayaran tagihan', null, 'Biaya administrasi mengikuti ketentuan PT Pos Indonesia', 0, [
            ['Nomor pelanggan atau tagihan', 1, 0],
        ]],
        ['Pengambilan paket tertahan', 0, 'Tidak dipungut biaya bila tidak ada bea tambahan', 0, [
            ['KTP penerima', 1, 0],
            ['Surat pemberitahuan', 1, 1],
        ]],
    ],

    'pdam' => [
        ['Pemasangan sambungan baru', null, $perdaTuban, 14, [
            ['Fotokopi KTP', 1, 1],
            ['Fotokopi bukti kepemilikan rumah', 1, 1],
            ['Denah lokasi', 0, 1],
        ]],
        ['Pengaduan kebocoran atau air mati', 0, 'Tidak dipungut biaya', 2, [
            ['Nomor pelanggan', 1, 0],
        ]],
        ['Balik nama pelanggan', null, $perdaTuban, 7, [
            ['Fotokopi KTP pemilik lama dan baru', 1, 1],
            ['Bukti lunas tagihan terakhir', 1, 1],
        ]],
        ['Penutupan sambungan sementara', null, $perdaTuban, 7, [
            ['Nomor pelanggan', 1, 0],
            ['Fotokopi KTP', 1, 0],
        ]],
    ],

    'terminal' => [
        ['Informasi trayek dan jadwal', 0, 'Tidak dipungut biaya', 0, []],
        ['Retribusi masuk kendaraan umum', null, $perdaTuban, 0, [
            ['STNK kendaraan', 1, 0],
        ]],
        ['Izin pengusahaan angkutan dalam terminal', null, $perdaTuban, 14, [
            ['Surat permohonan', 1, 1],
            ['Fotokopi izin trayek', 1, 1],
        ]],
        ['Pengaduan layanan angkutan', 0, 'Tidak dipungut biaya', 2, [
            ['Kronologi tertulis', 1, 1],
        ]],
    ],

    'pasar' => [
        ['Retribusi pedagang harian', null, $perdaTuban, 0, [
            ['Kartu pedagang', 1, 0],
        ]],
        ['Perizinan kios atau los', null, $perdaTuban, 14, [
            ['Surat permohonan', 1, 1],
            ['Fotokopi KTP', 1, 1],
            ['Bukti lunas retribusi terakhir', 0, 1],
        ]],
        ['Balik nama hak pakai kios', null, $perdaTuban, 14, [
            ['Surat pernyataan pengalihan', 1, 1],
            ['Fotokopi KTP kedua pihak', 1, 1],
        ]],
        ['Pengaduan kebersihan dan keamanan pasar', 0, 'Tidak dipungut biaya', 2, [
            ['Kronologi tertulis', 0, 1],
        ]],
    ],

    'satpol_pp' => [
        ['Pengaduan pelanggaran perda', 0, 'Tidak dipungut biaya', 3, [
            ['Kronologi tertulis', 1, 1],
            ['Foto lokasi', 0, 1],
        ]],
        ['Permohonan pengamanan kegiatan', 0, 'Tidak dipungut biaya', 7, [
            ['Surat permohonan', 1, 1],
            ['Rekomendasi kecamatan', 1, 1],
        ]],
        ['Konsultasi penertiban usaha', 0, 'Tidak dipungut biaya', 3, [
            ['Fotokopi KTP', 1, 0],
        ]],
    ],

    'pemadam' => [
        ['Pelaporan kebakaran', 0, 'Tidak dipungut biaya', 0, []],
        ['Permohonan penyelamatan non-kebakaran', 0, 'Tidak dipungut biaya', 0, [
            ['Alamat dan kronologi', 1, 0],
        ]],
        ['Rekomendasi proteksi kebakaran bangunan', null, $perdaTuban, 14, [
            ['Gambar rencana bangunan', 1, 1],
            ['Surat permohonan', 1, 1],
        ]],
        ['Pengisian ulang alat pemadam api ringan', null, $perdaTuban, 3, [
            ['Tabung APAR', 1, 0],
        ]],
        ['Simulasi dan pelatihan kebakaran', null, $perdaTuban, 14, [
            ['Surat permohonan dari instansi atau warga', 1, 1],
        ]],
    ],

    'dinas' => [
        ['Layanan informasi publik', 0, 'UU 14/2008 — permohonan informasi publik tidak dipungut biaya, kecuali biaya penggandaan', 10, [
            ['Formulir permohonan informasi', 1, 1],
            ['Fotokopi KTP pemohon', 1, 0],
        ]],
        ['Pengaduan masyarakat', 0, 'Tidak dipungut biaya', 5, [
            ['Kronologi tertulis', 1, 1],
            ['Fotokopi KTP pengadu', 0, 0],
        ]],
        ['Permohonan audiensi', 0, 'Tidak dipungut biaya', 7, [
            ['Surat permohonan', 1, 1],
            ['Daftar peserta', 0, 1],
        ]],
        ['Legalisasi dokumen dinas', null, $perdaTuban, 3, [
            ['Dokumen asli', 1, 0],
            ['Fotokopi yang akan dilegalisasi', 1, 1],
        ]],
    ],
];


/* ============================================ dasar dan catatan syarat berkas */

/*
 * Dari mana daftar berkas tiap kategori berasal.
 *
 * Aturannya sama seperti tarif: yang punya dasar peraturan disebut
 * peraturannya, yang tidak mengaku sebagai susunan umum. Daftar berkas yang
 * salah membuat warga bolak-balik — lebih baik terus terang bahwa daftarnya
 * belum dikonfirmasi daripada terlihat pasti padahal tidak.
 */
$umumBerkas = 'Susunan umum; tiap kantor dapat meminta berkas tambahan — konfirmasi sebelum datang';

$dasarBerkas = [
    'dukcapil' => 'Permendagri 108/2019 tentang Peraturan Pelaksanaan Perpres 96/2018 (Adminduk)',
    'kecamatan' => 'Permendagri 108/2019 untuk layanan kependudukan; selebihnya susunan umum',
    'kelurahan' => 'Permendagri 108/2019 untuk layanan kependudukan; selebihnya susunan umum',
    'polsek' => 'Perkap 18/2014 tentang Tata Cara Penerbitan SKCK',
    'kua' => 'PMA 20/2019 tentang Pencatatan Pernikahan',
    'samsat' => 'Perpol 7/2021 tentang Registrasi dan Identifikasi Kendaraan Bermotor',
    'pertanahan' => 'PP 24/1997 dan PP 128/2015 tentang Pendaftaran Tanah',
    'bpjs' => 'Perpres 82/2018 tentang Jaminan Kesehatan',
    'perizinan' => 'PP 5/2021 tentang Perizinan Berusaha Berbasis Risiko',
    'pengadilan' => 'Hukum acara perdata; rincian berkas mengikuti SK Ketua Pengadilan',
    'pengadilan_agama' => 'Hukum acara peradilan agama; rincian berkas mengikuti SK Ketua Pengadilan Agama',
];

/*
 * Catatan per jenis berkas, dicocokkan dari labelnya.
 *
 * Sengaja hanya hal yang pasti benar di mana pun: fotokopi perlu aslinya untuk
 * dicocokkan, dan dokumen asli yang diserahkan akan dikembalikan. Hal yang
 * berbeda-beda antar kantor — masa berlaku surat pengantar, ukuran meterai,
 * jumlah rangkap — tidak dimasukkan karena tidak bisa dipastikan dari sini.
 */
$catatanBerkas = [
    '/^fotokopi\b/i' => 'Bawa dokumen aslinya untuk dicocokkan petugas.',
    '/\basli\b/i' => 'Dikembalikan setelah diperiksa petugas.',
    '/^pasfoto\b/i' => 'Cetak terbaru, bukan hasil potong dari foto lain.',
    '/\bsurat pengantar\b/i' => 'Ditandatangani dan distempel pejabat yang berwenang.',
    '/\bbermeterai\b/i' => 'Meterai ditempel dan ditandatangani di atasnya.',
    '/\bkronologi\b/i' => 'Tulis urutan kejadian beserta tanggal dan tempatnya.',
    '/\bkartu jkn\b|\bbpjs\b/i' => 'Pastikan kepesertaan dalam keadaan aktif.',
    '/\bkendaraan untuk cek fisik\b/i' => 'Kendaraan harus dibawa, tidak bisa diwakilkan berkasnya saja.',
];

/* ================================================================== lokasi */

// Daftar kantor tidak lagi dibuat di sini. Sejak data dicocokkan dengan
// keadaan sebenarnya, isinya datang dari config/fasilitas.php — hasil
// penyaringan POI OpenStreetMap di dalam batas Kabupaten Tuban. Lihat
// komentar di berkas itu untuk apa yang asli dan apa yang masih perkiraan.

echo "Menyimpan kantor layanan dan layanannya…\n";

$jamUmum = json_encode([
    'mon' => ['08:00', '15:00'], 'tue' => ['08:00', '15:00'], 'wed' => ['08:00', '15:00'],
    'thu' => ['08:00', '15:00'], 'fri' => ['08:00', '14:00'], 'sat' => null, 'sun' => null,
]);

$jamKesehatan = json_encode([
    'mon' => ['07:30', '14:00'], 'tue' => ['07:30', '14:00'], 'wed' => ['07:30', '14:00'],
    'thu' => ['07:30', '14:00'], 'fri' => ['07:30', '11:30'], 'sat' => ['07:30', '11:00'], 'sun' => null,
]);

$jamPasar = json_encode([
    'mon' => ['07:00', '13:00'], 'tue' => ['07:00', '13:00'], 'wed' => ['07:00', '13:00'],
    'thu' => ['07:00', '13:00'], 'fri' => ['07:00', '11:30'], 'sat' => ['07:00', '11:00'], 'sun' => null,
]);

$locationIds = [];
$jumlahLayanan = 0;

// Kantor layanan diambil dari config/fasilitas.php — hasil penyaringan POI
// OpenStreetMap di dalam batas Kabupaten Tuban. Sebelumnya daftar ini dibuat
// otomatis tiga kantor per kecamatan dengan koordinat karangan; sekarang nama
// dan titiknya nyata, dan yang belum terpetakan ditandai 'perkiraan'.
$kuotaKategori = [
    'kecamatan' => 60,
    'kelurahan' => 30,
    'dukcapil' => 80,
    'puskesmas' => 40,
    'rumah_sakit' => 70,
    'polsek' => 30,
    'pemadam' => 15,
    'pengadilan' => 25,
    'pengadilan_agama' => 25,
    'kejaksaan' => 20,
    'kua' => 25,
    'samsat' => 60,
    'pertanahan' => 35,
    'perpustakaan' => 40,
    'pos' => 50,
    'terminal' => 20,
    'pasar' => 25,
    'dinas' => 35,
];

$ringkasKategori = [
    'kecamatan' => 'Surat pindah, legalisasi, dan rekomendasi tingkat kecamatan.',
    'kelurahan' => 'Pengantar RT/RW, surat domisili, dan keterangan usaha.',
    'dukcapil' => 'KTP, Kartu Keluarga, akta kelahiran, dan akta kematian.',
    'puskesmas' => 'Poli umum, poli gigi, imunisasi, dan surat keterangan sehat.',
    'rumah_sakit' => 'Poliklinik spesialis, rawat jalan, dan surat rujukan.',
    'polsek' => 'SKCK, laporan kehilangan, dan pengaduan masyarakat.',
    'pemadam' => 'Penanganan kebakaran, penyelamatan, dan rekomendasi proteksi.',
    'pengadilan' => 'Pendaftaran perkara, salinan putusan, dan legalisasi.',
    'pengadilan_agama' => 'Dispensasi nikah, itsbat nikah, dan salinan putusan.',
    'kejaksaan' => 'Pelayanan hukum, tilang, dan pengambilan barang bukti.',
    'kua' => 'Pendaftaran nikah, legalisasi buku nikah, dan rekomendasi nikah.',
    'samsat' => 'Perpanjangan STNK, mutasi kendaraan, dan pembayaran pajak.',
    'pertanahan' => 'Pengecekan sertipikat, peralihan hak, dan pengukuran.',
    'perpustakaan' => 'Keanggotaan, peminjaman, dan ruang baca.',
    'pos' => 'Pengiriman, wesel, dan pembayaran tagihan.',
    'terminal' => 'Informasi trayek dan retribusi angkutan.',
    'pasar' => 'Retribusi pedagang dan perizinan kios.',
    'dinas' => 'Layanan administratif sesuai tugas dinas terkait.',
];

foreach (config('fasilitas') as $f) {
    $slugKec = (string) $f['kecamatan'];

    if (!isset($districtId[$slugKec])) {
        continue;
    }

    $kategori = (string) $f['kategori'];
    $namaLokasi = (string) $f['nama'];
    $slugLokasi = slugify($namaLokasi);

    // Nama fasilitas di OSM tidak dijamin unik se-kabupaten (ada dua
    // "Pos Indonesia"), sedangkan slug harus unik. Yang kedua dan seterusnya
    // diberi nama kecamatannya supaya tetap terbaca manusia.
    if (isset($locationIds[$slugLokasi])) {
        $namaLokasi .= ' (' . ucwords(str_replace('-', ' ', $slugKec)) . ')';
        $slugLokasi = slugify($namaLokasi);
    }

    if (isset($locationIds[$slugLokasi])) {
        continue;
    }

    $jam = $jamUmum;

    if (in_array($kategori, ['puskesmas', 'rumah_sakit'], true)) {
        $jam = $jamKesehatan;
    } elseif ($kategori === 'pasar') {
        $jam = $jamPasar;
    }

    $ringkas = $ringkasKategori[$kategori] ?? 'Layanan publik untuk warga sekitar.';
    $perkiraan = ($f['sumber'] ?? 'perkiraan') !== 'osm';

    $locationId = DB::insert('locations', [
        'district_id' => $districtId[$slugKec],
        'name' => $namaLokasi,
        'slug' => $slugLokasi,
        'category' => $kategori,
        'lng' => $f['lng'],
        'lat' => $f['lat'],
        // Telepon dan email sengaja dibiarkan kosong bila tidak ada di OSM.
        // Nomor karangan di kantor pemerintah sungguhan lebih berbahaya
        // daripada kolom kosong: ada yang benar-benar meneleponnya.
        'address' => $f['alamat'],
        'phone' => $f['telepon'],
        'email' => null,
        'short_desc' => $ringkas,
        'long_desc' => $namaLokasi . ' melayani warga Kecamatan '
            . ucwords(str_replace('-', ' ', $slugKec)) . '. Tarif yang diatur peraturan '
            . 'nasional dicantumkan beserta dasar hukumnya, begitu pula daftar '
            . 'berkasnya. Yang belum pasti ditandai terus terang di tiap layanan.',
        'open_hours' => $jam,
        'quota_per_day' => $kuotaKategori[$kategori] ?? 30,
        'data_source' => $perkiraan ? 'perkiraan' : 'osm',
        'osm_ref' => $f['osm'],
        'is_active' => 1,
    ]);

    $locationIds[$slugLokasi] = $locationId;

    foreach ($templates[$kategori] ?? [] as $urutan => [$namaLayanan, $biaya, $dasar, $sla, $berkas]) {
        $serviceId = DB::insert('services', [
            'location_id' => $locationId,
            'name' => $namaLayanan,
            'slug' => slugify($namaLayanan),
            'description' => null,
            'sla_days' => $sla > 0 ? $sla : null,
            // null berarti belum dikonfirmasi, bukan gratis.
            'fee' => $biaya,
            'fee_basis' => $dasar,
            'docs_basis' => $dasarBerkas[$kategori] ?? $umumBerkas,
            'is_active' => 1,
            'sort_order' => $urutan,
        ]);

        $jumlahLayanan++;

        foreach ($berkas as $i => [$label, $wajib, $unggah]) {
            $catatan = null;

            foreach ($catatanBerkas as $pola => $teks) {
                if (preg_match($pola, $label)) {
                    $catatan = $teks;
                    break;
                }
            }

            DB::insert('service_requirements', [
                'service_id' => $serviceId,
                'label' => $label,
                'note' => $catatan,
                'is_mandatory' => $wajib,
                'needs_upload' => $unggah,
                'sort_order' => $i,
            ]);
        }
    }
}

/* =================================================================== akun */

echo "Menyimpan akun demo…\n";

// Dicari lewat kategori, bukan slug tetap: nama resmi kantor berubah
// mengikuti data OSM, slug-nya ikut berubah, dan seed tidak boleh pecah
// hanya karena itu.
$dukcapil = DB::column(
    'SELECT id FROM locations WHERE category = ? ORDER BY id LIMIT 1',
    ['dukcapil']
);

if ($dukcapil === null) {
    throw new RuntimeException('Tidak ada kantor berkategori dukcapil di config/fasilitas.php.');
}
$sandi = password_hash('tuban12345', PASSWORD_DEFAULT);

$userIds = [];

foreach ([
    ['superadmin', null, 'Dwi Hartanto', 'superadmin@tcm.test', null],
    ['admin', $dukcapil, 'Sulastri Handayani', 'admin@tcm.test', null],
    ['officer', $dukcapil, 'Bayu Prasetya', 'petugas1@tcm.test', null],
    ['officer', $dukcapil, 'Ratih Kusuma', 'petugas2@tcm.test', null],
    ['officer', $dukcapil, 'Joko Santoso', 'petugas3@tcm.test', null],
    ['user', null, 'Rudi Maulana', 'warga1@tcm.test', '3523011203990002'],
    ['user', null, 'Dewi Larasati', 'warga2@tcm.test', '3523014506020003'],
    ['user', null, 'Agus Winarno', 'warga3@tcm.test', '3523012208850004'],
] as [$peran, $lokasi, $nama, $email, $nik]) {
    $userIds[$email] = DB::insert('users', [
        'role' => $peran,
        'location_id' => $lokasi,
        'name' => $nama,
        'email' => $email,
        'phone' => '0813' . random_int(10000000, 99999999),
        'password_hash' => $sandi,
        'nik' => $nik,
        'district_id' => $districtId['tuban'],
        'is_verified' => 1,
    ]);
}

/* ================================================================ antrean */

echo "Membuat contoh antrean…\n";

$layananDukcapil = DB::all('SELECT id, fee FROM services WHERE location_id = ?', [$dukcapil]);
$petugas = [$userIds['petugas1@tcm.test'], $userIds['petugas2@tcm.test'], $userIds['petugas3@tcm.test']];
$warga = [$userIds['warga1@tcm.test'], $userIds['warga2@tcm.test'], $userIds['warga3@tcm.test']];
$slot = array_keys((array) config('app.queue.slots', ['09:00' => '']));

$jumlahAntrean = 0;

for ($hariLalu = 30; $hariLalu >= 0; $hariLalu--) {
    $tanggal = date('Y-m-d', strtotime('-' . $hariLalu . ' day'));

    if ((int) date('N', strtotime($tanggal)) >= 6) {
        continue; // Sabtu dan Minggu tutup
    }

    $banyak = $hariLalu === 0 ? 6 : random_int(1, 3);

    for ($i = 0; $i < $banyak; $i++) {
        $layanan = $layananDukcapil[array_rand($layananDukcapil)];
        $officer = $petugas[array_rand($petugas)];
        $citizen = $warga[array_rand($warga)];
        $biaya = (float) $layanan['fee'];

        if ($hariLalu === 0) {
            // Hari ini sengaja dibuat beragam supaya papan petugas terisi.
            $status = ['baru', 'baru', 'ditinjau', 'ditangani', 'selesai', 'selesai'][$i];
        } else {
            $undi = random_int(1, 10);
            $status = $undi <= 8 ? 'selesai' : ($undi === 9 ? 'dibatalkan' : 'ditangani');
        }

        $dibuat = $tanggal . ' ' . sprintf('%02d:%02d:00', random_int(7, 14), random_int(0, 59));
        $diterima = date('Y-m-d H:i:s', strtotime($dibuat) + random_int(5, 40) * 60);
        $selesai = date('Y-m-d H:i:s', strtotime($diterima) + random_int(10, 70) * 60);

        $nomor = 1 + (int) DB::column(
            'SELECT COALESCE(MAX(queue_number), 0) FROM queues WHERE location_id = ? AND queue_date = ?',
            [$dukcapil, $tanggal]
        );

        $bayar = 'tidak_perlu';

        if ($biaya > 0) {
            $bayar = $status === 'selesai' ? 'lunas' : 'menunggu';
        }

        $queueId = DB::insert('queues', [
            'code' => CodeGenerator::uniqueQueueCode(),
            'location_id' => $dukcapil,
            'service_id' => (int) $layanan['id'],
            'user_id' => $citizen,
            'officer_id' => in_array($status, ['baru', 'dibatalkan'], true) ? null : $officer,
            'queue_date' => $tanggal,
            'queue_number' => $nomor,
            'slot_time' => $slot[array_rand($slot)],
            'status' => $status,
            'payment_status' => $bayar,
            'accepted_at' => in_array($status, ['baru', 'dibatalkan'], true) ? null : $diterima,
            'finished_at' => $status === 'selesai' ? $selesai : null,
            'created_at' => $dibuat,
        ]);

        DB::insert('queue_status_logs', [
            'queue_id' => $queueId, 'from_status' => null, 'to_status' => 'baru',
            'actor_id' => $citizen, 'note' => 'Permohonan dibuat oleh warga', 'created_at' => $dibuat,
        ]);

        if (!in_array($status, ['baru', 'dibatalkan'], true)) {
            DB::insert('queue_status_logs', [
                'queue_id' => $queueId, 'from_status' => 'baru', 'to_status' => 'ditinjau',
                'actor_id' => $officer, 'note' => 'Antrean diterima petugas', 'created_at' => $diterima,
            ]);
        }

        if (in_array($status, ['ditangani', 'selesai'], true)) {
            DB::insert('queue_status_logs', [
                'queue_id' => $queueId, 'from_status' => 'ditinjau', 'to_status' => 'ditangani',
                'actor_id' => $officer, 'note' => null, 'created_at' => $diterima,
            ]);
        }

        if ($status === 'selesai') {
            DB::insert('queue_status_logs', [
                'queue_id' => $queueId, 'from_status' => 'ditangani', 'to_status' => 'selesai',
                'actor_id' => $officer, 'note' => 'Permohonan selesai', 'created_at' => $selesai,
            ]);
        }

        if ($biaya > 0) {
            DB::insert('payments', [
                'queue_id' => $queueId,
                'amount' => $biaya,
                'method' => 'tunai',
                'status' => $bayar === 'lunas' ? 'lunas' : 'menunggu',
                'paid_at' => $bayar === 'lunas' ? $selesai : null,
                'verified_by' => $bayar === 'lunas' ? $userIds['admin@tcm.test'] : null,
                'created_at' => $dibuat,
            ]);
        }

        $jumlahAntrean++;
    }
}

// Satu percakapan contoh pada antrean yang sedang ditangani hari ini.
$contoh = DB::first(
    "SELECT id, user_id, officer_id FROM queues
      WHERE location_id = ? AND queue_date = CURDATE() AND status = 'ditangani' LIMIT 1",
    [$dukcapil]
);

if ($contoh !== null && $contoh['officer_id'] !== null) {
    DB::insert('messages', [
        'queue_id' => $contoh['id'], 'sender_id' => $contoh['officer_id'],
        'body' => 'Selamat pagi. Berkas sudah kami periksa dan lengkap, mohon datang sesuai sesi ya.',
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    DB::insert('messages', [
        'queue_id' => $contoh['id'], 'sender_id' => $contoh['user_id'],
        'body' => 'Baik, terima kasih informasinya.',
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

/* ================================================================ ringkasan */

printf(
    "\nSelesai.\n  %d kecamatan\n  %d kantor layanan\n  %d layanan\n  %d akun (sandi: tuban12345)\n  %d antrean contoh\n",
    count($kecamatan),
    count($locationIds),
    $jumlahLayanan,
    count($userIds),
    $jumlahAntrean
);

// Scene peta ikut berubah, jadi cache-nya dikosongkan.
printf("  %d berkas cache dibersihkan\n", Cache::flush());

echo <<<CATATAN

Nama, kategori, koordinat, dan alamat kantor berasal dari OpenStreetMap
(ODbL) dan sudah nyata. Tarif layanan hanya diisi bila diatur peraturan
nasional dan dasar hukumnya tercantum; selebihnya bertanda "Perlu
dikonfirmasi", bukan "Gratis".

Yang BELUM: syarat berkas masih susunan umum, telepon hanya ada bila OSM
punya, email selalu kosong, dan kantor bertanda perkiraan hanya duduk di
titik tengah kecamatan. Konfirmasikan ke instansi sebelum dipakai
sungguhan, dan periksa ulang tarif PNBP kalau peraturannya direvisi.

CATATAN;

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

    return trim($value, '-') ?: 'item';
}
