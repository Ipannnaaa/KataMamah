-- =============================================================================
-- WIA — data awal (referensi + akun demo + contoh antrean)
--
-- Jalankan SETELAH schema.sql:
--   mysql -u root -p wia < database/seed.sql
--
-- Semua akun demo memakai sandi: wia12345
-- Ganti atau hapus akun ini sebelum dipakai di lingkungan nyata.
--
-- Catatan: file ini mengandalkan AUTO_INCREMENT dimulai dari 1, jadi jalankan
-- pada basis data yang baru dibuat oleh schema.sql.
-- =============================================================================

SET NAMES utf8mb4;
USE `wia`;

-- =================================================================== kecamatan

INSERT INTO `districts` (`name`, `slug`, `center_lng`, `center_lat`, `zoom`, `pitch`, `summary`) VALUES
('Wates',    'wates',    110.1556000, -7.8600000, 13.50, 55.0, 'Pusat pemerintahan kabupaten dengan kantor layanan terbanyak.'),
('Pengasih', 'pengasih', 110.1792000, -7.8311000, 13.50, 55.0, 'Wilayah dengan dua kantor layanan kesehatan dan satu kantor kecamatan.'),
('Sentolo',  'sentolo',  110.2174000, -7.8321000, 13.50, 55.0, 'Simpul layanan dokumen kependudukan untuk wilayah timur.'),
('Panjatan', 'panjatan', 110.1256000, -7.8906000, 13.50, 55.0, 'Melayani wilayah pesisir selatan, termasuk layanan kendaraan.'),
('Galur',    'galur',    110.2089000, -7.9372000, 13.50, 55.0, 'Wilayah selatan dengan layanan kelurahan dan kepolisian.');

-- ===================================================================== lokasi

INSERT INTO `locations`
(`district_id`, `name`, `slug`, `category`, `lng`, `lat`, `address`, `phone`, `email`, `short_desc`, `long_desc`, `open_hours`, `quota_per_day`) VALUES
(1, 'Kantor Kecamatan Wates', 'kantor-kecamatan-wates', 'kecamatan', 110.1561000, -7.8612000,
 'Jl. Sugiman No. 12, Wates', '(0274) 773011', 'kecamatan.wates@contoh.go.id',
 'Surat pindah, legalisasi, rekomendasi, dan pengantar KTP.',
 'Kantor kecamatan yang melayani administrasi kependudukan tingkat kecamatan untuk 8 kelurahan di wilayah Wates.',
 '{"mon":["08:00","15:00"],"tue":["08:00","15:00"],"wed":["08:00","15:00"],"thu":["08:00","15:00"],"fri":["08:00","14:00"],"sat":null,"sun":null}', 60),

(1, 'UPT Pasar Wates', 'upt-pasar-wates', 'pasar', 110.1583000, -7.8597000,
 'Kompleks Pasar Wates Blok B', '(0274) 773455', 'pasar.wates@contoh.go.id',
 'Perizinan kios, retribusi pedagang, dan pengaduan fasilitas pasar.',
 'Unit pelaksana teknis yang mengelola perizinan dan retribusi pedagang Pasar Wates.',
 '{"mon":["07:00","13:00"],"tue":["07:00","13:00"],"wed":["07:00","13:00"],"thu":["07:00","13:00"],"fri":["07:00","11:30"],"sat":["07:00","11:00"],"sun":null}', 25),

(1, 'Puskesmas Wates', 'puskesmas-wates', 'puskesmas', 110.1529000, -7.8641000,
 'Jl. Tentara Pelajar No. 4, Wates', '(0274) 773128', 'puskesmas.wates@contoh.go.id',
 'Poli umum, poli gigi, dan surat keterangan sehat.',
 'Fasilitas kesehatan tingkat pertama dengan layanan poli umum, poli gigi, laboratorium sederhana, dan surat keterangan sehat.',
 '{"mon":["07:30","14:00"],"tue":["07:30","14:00"],"wed":["07:30","14:00"],"thu":["07:30","14:00"],"fri":["07:30","11:30"],"sat":["07:30","11:00"],"sun":null}', 40),

(2, 'Kantor Kecamatan Pengasih', 'kantor-kecamatan-pengasih', 'kecamatan', 110.1798000, -7.8305000,
 'Jl. Pracoyo No. 9, Pengasih', '(0274) 773210', 'kecamatan.pengasih@contoh.go.id',
 'Surat pindah, surat keterangan tidak mampu, dan legalisasi.',
 'Kantor kecamatan Pengasih melayani tujuh kelurahan dengan fokus pada surat keterangan dan rekomendasi.',
 '{"mon":["08:00","15:00"],"tue":["08:00","15:00"],"wed":["08:00","15:00"],"thu":["08:00","15:00"],"fri":["08:00","14:00"],"sat":null,"sun":null}', 50),

(2, 'Puskesmas Pengasih I', 'puskesmas-pengasih-i', 'puskesmas', 110.1771000, -7.8339000,
 'Jl. Pracoyo No. 5, Pengasih', '(0274) 773333', 'puskesmas.pengasih1@contoh.go.id',
 'Poli umum, imunisasi, dan pemeriksaan ibu hamil.',
 'Melayani poli umum serta program imunisasi anak dan pemeriksaan kehamilan, dengan antrean dibagi dua sesi.',
 '{"mon":["07:30","14:00"],"tue":["07:30","14:00"],"wed":["07:30","14:00"],"thu":["07:30","14:00"],"fri":["07:30","11:30"],"sat":null,"sun":null}', 40),

(3, 'Dinas Dukcapil Sentolo', 'dinas-dukcapil-sentolo', 'dukcapil', 110.2174000, -7.8321000,
 'Jl. Nasional III Km 7, Sentolo', '(0274) 7730118', 'dukcapil.sentolo@contoh.go.id',
 'KTP-el, kartu keluarga, akta kelahiran, dan akta kematian.',
 'Unit pelayanan dokumen kependudukan untuk wilayah Sentolo dan sekitarnya. Seluruh layanan dapat diambil antreannya secara online.',
 '{"mon":["08:00","15:30"],"tue":["08:00","15:30"],"wed":["08:00","15:30"],"thu":["08:00","15:30"],"fri":["08:00","14:00"],"sat":null,"sun":null}', 80),

(3, 'Kantor Kecamatan Sentolo', 'kantor-kecamatan-sentolo', 'kecamatan', 110.2201000, -7.8295000,
 'Jl. Raya Sentolo No. 21', '(0274) 773077', 'kecamatan.sentolo@contoh.go.id',
 'Surat pindah dan pengantar perekaman KTP.',
 'Kantor kecamatan Sentolo melayani surat pindah, pengantar KTP, dan rekomendasi kegiatan warga.',
 '{"mon":["08:00","15:00"],"tue":["08:00","15:00"],"wed":["08:00","15:00"],"thu":["08:00","15:00"],"fri":["08:00","14:00"],"sat":null,"sun":null}', 60),

(3, 'Kantor Kelurahan Salamrejo', 'kantor-kelurahan-salamrejo', 'kelurahan', 110.2252000, -7.8372000,
 'Jl. Salamrejo No. 3, Sentolo', '(0274) 773500', 'salamrejo@contoh.go.id',
 'Surat domisili, surat tidak mampu, dan legalisasi dokumen desa.',
 'Kantor kelurahan dengan layanan surat keterangan dasar bagi warga Salamrejo.',
 '{"mon":["08:00","14:30"],"tue":["08:00","14:30"],"wed":["08:00","14:30"],"thu":["08:00","14:30"],"fri":["08:00","11:30"],"sat":null,"sun":null}', 30),

(4, 'Samsat Pembantu Panjatan', 'samsat-pembantu-panjatan', 'samsat', 110.1261000, -7.8921000,
 'Jl. Kolonel Sugiyono, Panjatan', '(0274) 773900', 'samsat.panjatan@contoh.go.id',
 'Pengesahan STNK tahunan dan pembayaran pajak kendaraan.',
 'Samsat pembantu untuk pengesahan STNK tahunan, pembayaran PKB, dan mutasi kendaraan dalam daerah.',
 '{"mon":["08:00","14:00"],"tue":["08:00","14:00"],"wed":["08:00","14:00"],"thu":["08:00","14:00"],"fri":["08:00","11:00"],"sat":null,"sun":null}', 50),

(4, 'Kantor Kecamatan Panjatan', 'kantor-kecamatan-panjatan', 'kecamatan', 110.1239000, -7.8888000,
 'Jl. Panjatan Raya No. 1', '(0274) 773800', 'kecamatan.panjatan@contoh.go.id',
 'Surat pindah dan legalisasi dokumen kependudukan.',
 'Kantor kecamatan Panjatan melayani sebelas kelurahan di wilayah pesisir selatan.',
 '{"mon":["08:00","15:00"],"tue":["08:00","15:00"],"wed":["08:00","15:00"],"thu":["08:00","15:00"],"fri":["08:00","14:00"],"sat":null,"sun":null}', 45),

(5, 'Kantor Kelurahan Brosot', 'kantor-kelurahan-brosot', 'kelurahan', 110.2102000, -7.9388000,
 'Jl. Brosot Raya No. 2, Galur', '(0274) 773600', 'brosot@contoh.go.id',
 'Surat domisili, pengantar nikah, dan surat tidak mampu.',
 'Kantor kelurahan Brosot melayani surat keterangan dasar dan pengantar administrasi pernikahan.',
 '{"mon":["08:00","14:30"],"tue":["08:00","14:30"],"wed":["08:00","14:30"],"thu":["08:00","14:30"],"fri":["08:00","11:30"],"sat":null,"sun":null}', 30),

(5, 'Polsek Galur', 'polsek-galur', 'polsek', 110.2065000, -7.9344000,
 'Jl. Raya Galur No. 18', '(0274) 773700', 'polsek.galur@contoh.go.id',
 'Penerbitan SKCK dan surat keterangan kehilangan.',
 'Melayani penerbitan SKCK untuk keperluan melamar kerja serta surat keterangan kehilangan dokumen.',
 '{"mon":["08:00","14:00"],"tue":["08:00","14:00"],"wed":["08:00","14:00"],"thu":["08:00","14:00"],"fri":["08:00","11:00"],"sat":null,"sun":null}', 20);

-- ==================================================================== layanan
-- id 1–5  : Dukcapil Sentolo (lokasi 6)
-- id 6–8  : Kecamatan Wates (lokasi 1)
-- id 9–10 : Kecamatan Sentolo (lokasi 7)
-- id 11–12: Kecamatan Pengasih (lokasi 4)
-- id 13–14: Kecamatan Panjatan (lokasi 10)
-- id 15–16: Kelurahan Salamrejo (lokasi 8)
-- id 17–18: Kelurahan Brosot (lokasi 11)
-- id 19–20: Puskesmas Wates (lokasi 3)
-- id 21–22: Puskesmas Pengasih I (lokasi 5)
-- id 23–24: Samsat Panjatan (lokasi 9)
-- id 25–26: UPT Pasar Wates (lokasi 2)
-- id 27–28: Polsek Galur (lokasi 12)

INSERT INTO `services` (`location_id`, `name`, `slug`, `description`, `sla_days`, `fee`, `sort_order`) VALUES
(6, 'Perekaman & cetak KTP-el', 'ktp-el', 'Perekaman biometrik untuk pemohon baru, penggantian KTP rusak, atau perubahan data.', 1, 0, 1),
(6, 'Akta kelahiran', 'akta-kelahiran', 'Penerbitan akta kelahiran anak, termasuk pencatatan terlambat lebih dari 60 hari.', 3, 0, 2),
(6, 'Kartu Keluarga baru / perubahan', 'kartu-keluarga', 'Pembuatan KK baru, penambahan anggota, pemisahan KK, atau perbaikan data.', 2, 0, 3),
(6, 'Akta kematian', 'akta-kematian', 'Pencatatan kematian warga dan pemutakhiran kartu keluarga yang bersangkutan.', 2, 0, 4),
(6, 'Legalisasi dokumen kependudukan', 'legalisasi', 'Legalisasi fotokopi dokumen kependudukan yang diterbitkan dinas.', 0, 5000, 5),

(1, 'Surat pindah penduduk', 'surat-pindah', 'Pengantar pindah keluar atau masuk wilayah kecamatan.', 2, 0, 1),
(1, 'Legalisasi dokumen', 'legalisasi-dokumen', 'Legalisasi fotokopi dokumen yang dikeluarkan kecamatan.', 0, 0, 2),
(1, 'Rekomendasi izin kegiatan', 'rekomendasi-izin', 'Rekomendasi kecamatan untuk kegiatan keramaian dan usaha mikro.', 3, 0, 3),

(7, 'Surat pindah penduduk', 'surat-pindah', 'Pengantar pindah keluar atau masuk wilayah kecamatan.', 2, 0, 1),
(7, 'Pengantar perekaman KTP', 'pengantar-ktp', 'Surat pengantar untuk perekaman KTP-el di dinas.', 0, 0, 2),

(4, 'Surat pindah penduduk', 'surat-pindah', 'Pengantar pindah keluar atau masuk wilayah kecamatan.', 2, 0, 1),
(4, 'Surat keterangan tidak mampu', 'sktm', 'SKTM untuk keperluan pendidikan, kesehatan, atau bantuan sosial.', 1, 0, 2),

(10, 'Surat pindah penduduk', 'surat-pindah', 'Pengantar pindah keluar atau masuk wilayah kecamatan.', 2, 0, 1),
(10, 'Legalisasi dokumen', 'legalisasi-dokumen', 'Legalisasi fotokopi dokumen yang dikeluarkan kecamatan.', 0, 0, 2),

(8, 'Surat keterangan domisili', 'domisili', 'Keterangan tempat tinggal untuk keperluan administrasi.', 1, 0, 1),
(8, 'Surat keterangan tidak mampu', 'sktm', 'SKTM tingkat kelurahan sebagai dasar pengajuan bantuan.', 1, 0, 2),

(11, 'Surat keterangan domisili', 'domisili', 'Keterangan tempat tinggal untuk keperluan administrasi.', 1, 0, 1),
(11, 'Pengantar nikah (N1–N4)', 'pengantar-nikah', 'Berkas pengantar pernikahan dari kelurahan untuk KUA.', 2, 0, 2),

(3, 'Poli umum', 'poli-umum', 'Pemeriksaan dokter umum, dibagi sesi pagi dan siang.', 0, 0, 1),
(3, 'Surat keterangan sehat', 'surat-sehat', 'Pemeriksaan dasar dan penerbitan surat keterangan sehat.', 0, 15000, 2),

(5, 'Poli umum', 'poli-umum', 'Pemeriksaan dokter umum, dibagi sesi pagi dan siang.', 0, 0, 1),
(5, 'Imunisasi anak', 'imunisasi', 'Imunisasi dasar lengkap sesuai jadwal posyandu.', 0, 0, 2),

(9, 'Pengesahan STNK tahunan', 'pengesahan-stnk', 'Pengesahan STNK beserta pembayaran pajak tahunan.', 0, 0, 1),
(9, 'Pembayaran PKB', 'pembayaran-pkb', 'Pembayaran pajak kendaraan bermotor tanpa pengesahan fisik.', 0, 0, 2),

(2, 'Izin kios pasar', 'izin-kios', 'Permohonan izin menempati kios atau los pasar.', 5, 50000, 1),
(2, 'Retribusi pedagang', 'retribusi', 'Pembayaran retribusi bulanan pedagang pasar.', 0, 0, 2),

(12, 'Penerbitan SKCK', 'skck', 'Surat keterangan catatan kepolisian untuk keperluan melamar kerja.', 1, 30000, 1),
(12, 'Surat keterangan kehilangan', 'surat-kehilangan', 'Keterangan kehilangan dokumen atau barang untuk pengurusan ulang.', 0, 0, 2);

-- ============================================================= syarat berkas

INSERT INTO `service_requirements` (`service_id`, `label`, `note`, `is_mandatory`, `needs_upload`, `sort_order`) VALUES
-- 1 KTP-el
(1, 'Fotokopi Kartu Keluarga', '1 lembar, KK terbaru yang sudah ber-barcode', 1, 0, 0),
(1, 'Surat pengantar dari kelurahan', 'Ditandatangani lurah, berlaku 14 hari', 1, 1, 1),
(1, 'KTP lama', 'Untuk penggantian atau perubahan data', 0, 0, 2),
(1, 'Surat keterangan hilang dari kepolisian', 'Hanya untuk KTP hilang', 0, 1, 3),
-- 2 Akta kelahiran
(2, 'Surat keterangan lahir dari fasilitas kesehatan', 'Asli, bertanda tangan bidan atau dokter', 1, 1, 0),
(2, 'Fotokopi KTP kedua orang tua', 'Masing-masing 1 lembar', 1, 0, 1),
(2, 'Fotokopi buku nikah atau akta perkawinan', '1 lembar, halaman data', 1, 0, 2),
(2, 'Fotokopi KTP dua orang saksi', 'Saksi dewasa, bukan orang tua', 1, 0, 3),
-- 3 Kartu Keluarga
(3, 'KK lama (asli)', 'Diserahkan ke petugas saat dilayani', 1, 0, 0),
(3, 'Surat pengantar RT/RW dan kelurahan', 'Lengkap dengan cap basah', 1, 1, 1),
(3, 'Fotokopi akta perkawinan atau akta cerai', 'Sesuai perubahan yang diajukan', 0, 0, 2),
-- 4 Akta kematian
(4, 'Surat keterangan kematian dari rumah sakit atau kelurahan', 'Asli', 1, 1, 0),
(4, 'Fotokopi KK dan KTP almarhum', 'Masing-masing 1 lembar', 1, 0, 1),
(4, 'Fotokopi KTP pelapor', '1 lembar', 1, 0, 2),
-- 5 Legalisasi dukcapil
(5, 'Dokumen asli yang akan dilegalisasi', 'Dibawa saat dilayani', 1, 0, 0),
(5, 'Fotokopi dokumen', '2 lembar per dokumen', 1, 1, 1),
-- 6 Surat pindah Wates
(6, 'Fotokopi KK dan KTP', 'Masing-masing 1 lembar', 1, 0, 0),
(6, 'Surat pengantar RT/RW', 'Dengan cap basah', 1, 1, 1),
(6, 'Alamat tujuan lengkap', 'Termasuk RT, RW, dan kode pos', 1, 0, 2),
-- 7 Legalisasi Wates
(7, 'Dokumen asli', 'Dibawa saat dilayani', 1, 0, 0),
(7, 'Fotokopi dokumen', '2 lembar', 1, 0, 1),
-- 8 Rekomendasi izin
(8, 'Surat permohonan bermaterai', 'Ditandatangani penanggung jawab kegiatan', 1, 1, 0),
(8, 'Fotokopi KTP penanggung jawab', '1 lembar', 1, 0, 1),
(8, 'Rencana kegiatan', 'Jadwal, lokasi, dan perkiraan jumlah peserta', 1, 1, 2),
-- 9 Surat pindah Sentolo
(9, 'Fotokopi KK dan KTP', 'Masing-masing 1 lembar', 1, 0, 0),
(9, 'Surat pengantar RT/RW', 'Dengan cap basah', 1, 1, 1),
-- 10 Pengantar KTP
(10, 'Fotokopi Kartu Keluarga', '1 lembar', 1, 0, 0),
(10, 'Surat pengantar RT/RW', 'Dengan cap basah', 1, 0, 1),
-- 11 Surat pindah Pengasih
(11, 'Fotokopi KK dan KTP', 'Masing-masing 1 lembar', 1, 0, 0),
(11, 'Surat pengantar RT/RW', 'Dengan cap basah', 1, 1, 1),
-- 12 SKTM Pengasih
(12, 'Surat pengantar RT/RW', 'Dengan cap basah', 1, 1, 0),
(12, 'Fotokopi KK dan KTP', 'Masing-masing 1 lembar', 1, 0, 1),
(12, 'Keterangan keperluan', 'Pendidikan, kesehatan, atau bantuan sosial', 1, 0, 2),
-- 13 Surat pindah Panjatan
(13, 'Fotokopi KK dan KTP', 'Masing-masing 1 lembar', 1, 0, 0),
(13, 'Surat pengantar RT/RW', 'Dengan cap basah', 1, 1, 1),
-- 14 Legalisasi Panjatan
(14, 'Dokumen asli', 'Dibawa saat dilayani', 1, 0, 0),
(14, 'Fotokopi dokumen', '2 lembar', 1, 0, 1),
-- 15 Domisili Salamrejo
(15, 'Fotokopi KK dan KTP', 'Masing-masing 1 lembar', 1, 0, 0),
(15, 'Surat pengantar RT/RW', 'Dengan cap basah', 1, 1, 1),
-- 16 SKTM Salamrejo
(16, 'Surat pengantar RT/RW', 'Dengan cap basah', 1, 1, 0),
(16, 'Fotokopi KK dan KTP', 'Masing-masing 1 lembar', 1, 0, 1),
-- 17 Domisili Brosot
(17, 'Fotokopi KK dan KTP', 'Masing-masing 1 lembar', 1, 0, 0),
(17, 'Surat pengantar RT/RW', 'Dengan cap basah', 1, 1, 1),
-- 18 Pengantar nikah
(18, 'Fotokopi KTP dan KK calon pengantin', 'Masing-masing 1 lembar', 1, 0, 0),
(18, 'Akta kelahiran calon pengantin', 'Fotokopi, 1 lembar', 1, 1, 1),
(18, 'Surat pengantar RT/RW', 'Dengan cap basah', 1, 1, 2),
(18, 'Surat izin orang tua', 'Bila calon berusia di bawah 21 tahun', 0, 1, 3),
-- 19 Poli umum Wates
(19, 'Kartu identitas (KTP atau kartu pasien)', 'Dibawa saat berkunjung', 1, 0, 0),
(19, 'Kartu BPJS', 'Bila menggunakan jaminan kesehatan', 0, 0, 1),
-- 20 Surat sehat
(20, 'Fotokopi KTP', '1 lembar', 1, 0, 0),
(20, 'Keterangan keperluan surat', 'Melamar kerja, sekolah, atau lainnya', 1, 0, 1),
-- 21 Poli umum Pengasih
(21, 'Kartu identitas (KTP atau kartu pasien)', 'Dibawa saat berkunjung', 1, 0, 0),
(21, 'Kartu BPJS', 'Bila menggunakan jaminan kesehatan', 0, 0, 1),
-- 22 Imunisasi
(22, 'Buku KIA atau KMS', 'Dibawa saat berkunjung', 1, 0, 0),
(22, 'Fotokopi Kartu Keluarga', '1 lembar', 1, 0, 1),
-- 23 Pengesahan STNK
(23, 'STNK asli', 'Dibawa saat dilayani', 1, 0, 0),
(23, 'Fotokopi KTP pemilik', 'Sesuai nama di STNK', 1, 1, 1),
(23, 'BPKB atau fotokopinya', 'Untuk pencocokan data', 0, 0, 2),
-- 24 PKB
(24, 'STNK asli', 'Dibawa saat dilayani', 1, 0, 0),
(24, 'Fotokopi KTP pemilik', 'Sesuai nama di STNK', 1, 0, 1),
-- 25 Izin kios
(25, 'Surat permohonan bermaterai', 'Ditandatangani pemohon', 1, 1, 0),
(25, 'Fotokopi KTP', '1 lembar', 1, 0, 1),
(25, 'Foto kios atau los yang dimohon', 'Tampak depan', 1, 1, 2),
-- 26 Retribusi
(26, 'Kartu pedagang', 'Dibawa saat pembayaran', 1, 0, 0),
-- 27 SKCK
(27, 'Fotokopi KTP', '1 lembar', 1, 0, 0),
(27, 'Fotokopi Kartu Keluarga', '1 lembar', 1, 0, 1),
(27, 'Fotokopi akta kelahiran', '1 lembar', 1, 1, 2),
(27, 'Foto berwarna 4x6 latar merah', '6 lembar', 1, 0, 3),
-- 28 Surat kehilangan
(28, 'Fotokopi KTP', '1 lembar', 1, 0, 0),
(28, 'Keterangan kronologi kehilangan', 'Ditulis saat pelaporan', 1, 0, 1);

-- ==================================================================== akun
-- Sandi semua akun demo: wia12345

INSERT INTO `users`
(`role`, `location_id`, `name`, `email`, `phone`, `password_hash`, `nik`, `birth_date`, `gender`, `address`, `district_id`, `is_verified`) VALUES
('superadmin', NULL, 'Dwi Hartanto', 'superadmin@wia.test', '081322001100',
 '$2y$10$Tpca0PoohIX5Qqv0jq/Urusd4sduydC2gwSF9o3af03EbbPvXTIPm',
 NULL, NULL, 'L', NULL, NULL, 1),

('admin', 6, 'Sulastri Handayani', 'admin@wia.test', '081322001101',
 '$2y$10$ElstVRBuvClmwrXXpbfxGuLhWwY4N94GBCBW64CDNkpCnLYmms5MC',
 NULL, NULL, 'P', NULL, 3, 1),

('officer', 6, 'Bayu Prasetya', 'petugas1@wia.test', '081322001102',
 '$2y$10$nTiDKbxLVhQeaLZfVtRw4OyfPMb1YKZW.Ue4Yzf1g5H4aqiO9NNoC',
 NULL, NULL, 'L', NULL, 3, 1),

('officer', 6, 'Ratih Kusuma', 'petugas2@wia.test', '081322001103',
 '$2y$10$zP3GTD2xTNfAHuLYe43gsuLtJar.FgdKGwQYvA/P7VE0PZ3H/jDW.',
 NULL, NULL, 'P', NULL, 3, 1),

('officer', 6, 'Joko Santoso', 'petugas3@wia.test', '081322001104',
 '$2y$10$EUr39ksfqmcsPG8exMmk9ezCMcLYQGvvD3nMgSYAHclNnOG1wP9FO',
 NULL, NULL, 'L', NULL, 3, 1),

('user', NULL, 'Rudi Maulana', 'warga1@wia.test', '081322001180',
 '$2y$10$3EUiHv0eCqpWogBPV.fgBuh8TZ79AxjjsXYV8roPl/k1fVD2MiN/i',
 '3401011203990002', '1999-03-12', 'L',
 'Dusun Kalimenur RT 02 RW 05, Sukoreno, Sentolo', 3, 1),

('user', NULL, 'Dewi Larasati', 'warga2@wia.test', '081322001181',
 '$2y$10$9SzRcb3InAhzgF2HtVcO9.Zv1S9yi3YdGbl2xlV.RgvGEplTRthSy',
 '3401014506020003', '2002-06-05', 'P',
 'Jl. Pracoyo No. 44, Pengasih', 2, 1),

('user', NULL, 'Agus Winarno', 'warga3@wia.test', '081322001182',
 '$2y$10$a1ZkB0BwJ7Zk6v.0Rujc9u4IQn0dgWTdLZ4PhkK8peTXllXzqqlrS',
 '3401012208850004', '1985-08-22', 'L',
 'Dusun Brosot RT 01 RW 02, Galur', 5, 1);

-- ================================================================== antrean
-- id 1–13. Tanggal relatif terhadap hari impor agar papan antrean selalu terisi.

INSERT INTO `queues`
(`code`, `location_id`, `service_id`, `user_id`, `officer_id`, `queue_date`, `queue_number`, `slot_time`,
 `status`, `note_user`, `note_officer`, `payment_status`, `accepted_at`, `finished_at`, `created_at`) VALUES

('WIA-7KQ4M', 6, 2, 6, 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 24, '09:00:00', 'ditangani',
 'Akta kelahiran untuk anak kedua, pencatatan terlambat 4 bulan.',
 'Berkas lengkap. Nama ibu dikoreksi mengikuti KK. Akta dicetak, siap diambil di loket 3.',
 'tidak_perlu', TIMESTAMP(CURDATE(), '08:32:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '20:14:00')),

('WIA-3PX8T', 1, 7, 6, NULL, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 5, '10:00:00', 'baru',
 'Legalisasi tiga dokumen untuk keperluan beasiswa.', NULL,
 'tidak_perlu', NULL, NULL, TIMESTAMP(CURDATE(), '07:41:00')),

('WIA-9BZ2K', 9, 23, 6, NULL, DATE_SUB(CURDATE(), INTERVAL 13 DAY), 12, '08:00:00', 'selesai',
 NULL, 'Pengesahan STNK selesai, stiker ditempel.',
 'tidak_perlu', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '08:12:00'),
 TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '08:47:00'),
 TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '19:02:00')),

('WIA-5HM6R', 6, 3, 6, 4, DATE_SUB(CURDATE(), INTERVAL 40 DAY), 18, '09:00:00', 'selesai',
 'Pemisahan KK setelah menikah.', 'KK baru dicetak dan diserahkan ke pemohon.',
 'tidak_perlu', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 40 DAY), '09:05:00'),
 TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 40 DAY), '09:38:00'),
 TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 41 DAY), '21:15:00')),

('WIA-2CF8H', 6, 2, 7, NULL, CURDATE(), 35, '10:00:00', 'baru',
 'Pencatatan terlambat 90 hari, mohon diperiksa syarat tambahannya.', NULL,
 'tidak_perlu', NULL, NULL, TIMESTAMP(CURDATE(), '09:31:00')),

('WIA-4RT9P', 6, 1, 7, NULL, CURDATE(), 31, '09:00:00', 'baru',
 'Penggantian KTP rusak.', NULL,
 'tidak_perlu', NULL, NULL, TIMESTAMP(CURDATE(), '08:58:00')),

('WIA-8KD2N', 6, 3, 8, NULL, CURDATE(), 32, '09:00:00', 'baru',
 'Penambahan anggota keluarga.', NULL,
 'tidak_perlu', NULL, NULL, TIMESTAMP(CURDATE(), '09:04:00')),

('WIA-5XN1W', 6, 3, 8, 3, CURDATE(), 26, '08:00:00', 'ditinjau',
 'Perbaikan data tanggal lahir di KK.', 'Menunggu pemohon mengirim ulang pengantar RT yang terbaca.',
 'tidak_perlu', TIMESTAMP(CURDATE(), '08:20:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '18:40:00')),

('WIA-8PD4Z', 6, 5, 7, 4, CURDATE(), 29, '10:00:00', 'ditinjau',
 'Legalisasi dua lembar akta kelahiran.', NULL,
 'menunggu', TIMESTAMP(CURDATE(), '10:18:00'), NULL, TIMESTAMP(CURDATE(), '09:52:00')),

('WIA-4HT2M', 6, 2, 8, 5, CURDATE(), 12, '08:00:00', 'selesai',
 NULL, 'Akta diserahkan langsung ke pemohon.',
 'tidak_perlu', TIMESTAMP(CURDATE(), '08:31:00'), TIMESTAMP(CURDATE(), '09:12:00'),
 TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '13:22:00')),

('WIA-7NB9K', 6, 1, 6, 3, CURDATE(), 14, '08:00:00', 'selesai',
 NULL, 'Perekaman biometrik selesai, KTP dicetak dan diserahkan.',
 'tidak_perlu', TIMESTAMP(CURDATE(), '09:03:00'), TIMESTAMP(CURDATE(), '09:38:00'),
 TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '10:05:00')),

('WIA-6VB3N', 12, 27, 8, NULL, DATE_ADD(CURDATE(), INTERVAL 2 DAY), 4, '08:00:00', 'baru',
 'SKCK untuk melamar kerja.', NULL,
 'menunggu', NULL, NULL, TIMESTAMP(CURDATE(), '06:55:00')),

('WIA-2WS5R', 6, 5, 6, 5, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 22, '13:00:00', 'selesai',
 'Legalisasi KK untuk pendaftaran sekolah.', 'Tiga lembar dilegalisasi, pembayaran tunai diterima.',
 'lunas', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '13:06:00'),
 TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '13:24:00'),
 TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '20:44:00'));

-- ========================================================= riwayat status

INSERT INTO `queue_status_logs` (`queue_id`, `from_status`, `to_status`, `actor_id`, `note`, `created_at`) VALUES
(1, NULL, 'baru', 6, 'Permohonan dibuat oleh warga', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '20:14:00')),
(1, 'baru', 'ditinjau', 3, 'Antrean diterima petugas', TIMESTAMP(CURDATE(), '08:32:00')),
(1, 'ditinjau', 'ditangani', 3, 'Berkas dinyatakan lengkap', TIMESTAMP(CURDATE(), '10:05:00')),

(2, NULL, 'baru', 6, 'Permohonan dibuat oleh warga', TIMESTAMP(CURDATE(), '07:41:00')),

(3, NULL, 'baru', 6, 'Permohonan dibuat oleh warga', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '19:02:00')),
(3, 'baru', 'ditinjau', NULL, 'Antrean diterima petugas loket', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '08:12:00')),
(3, 'ditinjau', 'ditangani', NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '08:20:00')),
(3, 'ditangani', 'selesai', NULL, 'Stiker pengesahan ditempel', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '08:47:00')),

(8, NULL, 'baru', 8, 'Permohonan dibuat oleh warga', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '18:40:00')),
(8, 'baru', 'ditinjau', 3, 'Antrean diterima petugas', TIMESTAMP(CURDATE(), '08:20:00')),

(9, NULL, 'baru', 7, 'Permohonan dibuat oleh warga', TIMESTAMP(CURDATE(), '09:52:00')),
(9, 'baru', 'ditinjau', 4, 'Menunggu verifikasi pembayaran', TIMESTAMP(CURDATE(), '10:18:00')),

(10, NULL, 'baru', 8, 'Permohonan dibuat oleh warga', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '13:22:00')),
(10, 'baru', 'ditinjau', 5, 'Antrean diterima petugas', TIMESTAMP(CURDATE(), '08:31:00')),
(10, 'ditinjau', 'ditangani', 5, NULL, TIMESTAMP(CURDATE(), '08:40:00')),
(10, 'ditangani', 'selesai', 5, 'Akta diserahkan', TIMESTAMP(CURDATE(), '09:12:00')),

(13, NULL, 'baru', 6, 'Permohonan dibuat oleh warga', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '20:44:00')),
(13, 'baru', 'ditinjau', 5, 'Antrean diterima petugas', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '13:06:00')),
(13, 'ditinjau', 'ditangani', 5, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '13:10:00')),
(13, 'ditangani', 'selesai', 5, 'Pembayaran tunai diterima', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '13:24:00'));

-- ====================================================================== chat

INSERT INTO `messages` (`queue_id`, `sender_id`, `body`, `read_at`, `created_at`) VALUES
(1, 3, 'Selamat pagi Pak Rudi. Berkas sudah saya periksa, semuanya lengkap. Satu hal: nama ibu pada surat keterangan lahir perlu disesuaikan dengan KK.',
 TIMESTAMP(CURDATE(), '08:40:00'), TIMESTAMP(CURDATE(), '08:34:00')),
(1, 6, 'Baik Pak. Yang benar sesuai KK: Siti Marlina. Di surat rumah sakit tertulis Siti Marlinah.',
 TIMESTAMP(CURDATE(), '08:45:00'), TIMESTAMP(CURDATE(), '08:41:00')),
(1, 3, 'Noted. Saya catat koreksinya di berkas antrean, tidak perlu surat tambahan.',
 NULL, TIMESTAMP(CURDATE(), '08:47:00')),
(1, 3, 'Akta kelahiran sudah dicetak dan dapat diambil di loket 3 mulai pukul 13.00. Mohon membawa KTP asli ya.',
 NULL, TIMESTAMP(CURDATE(), '10:12:00')),

(8, 3, 'Selamat pagi Pak Agus. Pengantar RT yang diunggah kurang terbaca pada bagian tanda tangan. Bisa dikirim ulang fotonya?',
 NULL, TIMESTAMP(CURDATE(), '08:26:00')),

(9, 4, 'Selamat pagi Bu Dewi. Biaya legalisasi Rp 5.000 dapat dibayar tunai di loket saat kedatangan.',
 NULL, TIMESTAMP(CURDATE(), '10:20:00'));

-- ================================================================ pembayaran

INSERT INTO `payments` (`queue_id`, `amount`, `method`, `reference`, `status`, `paid_at`, `verified_by`, `created_at`) VALUES
(9,  5000.00,  'tunai', NULL, 'menunggu', NULL, NULL, TIMESTAMP(CURDATE(), '09:52:00')),
(12, 30000.00, 'qris',  NULL, 'menunggu', NULL, NULL, TIMESTAMP(CURDATE(), '06:55:00')),
(13, 15000.00, 'tunai', 'TUNAI-0923', 'lunas',
 TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '13:20:00'), 2,
 TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '20:44:00'));
