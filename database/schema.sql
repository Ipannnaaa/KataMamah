-- =============================================================================
-- WIA — skema basis data
-- MySQL 8 / MariaDB 10.6+ · utf8mb4
--
-- Jalankan:  mysql -u root -p < database/schema.sql
-- atau impor file ini lewat phpMyAdmin.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `wia`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `wia`;

DROP TABLE IF EXISTS `email_verifications`;
DROP TABLE IF EXISTS `facility_reports`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `messages`;
DROP TABLE IF EXISTS `queue_status_logs`;
DROP TABLE IF EXISTS `queue_documents`;
DROP TABLE IF EXISTS `queues`;
DROP TABLE IF EXISTS `service_requirements`;
DROP TABLE IF EXISTS `services`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `locations`;
DROP TABLE IF EXISTS `districts`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================ wilayah & lokasi

CREATE TABLE `districts` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(100) NOT NULL,
    `slug`       VARCHAR(120) NOT NULL UNIQUE,
    `center_lng` DECIMAL(10,7) NOT NULL,
    `center_lat` DECIMAL(10,7) NOT NULL,
    `zoom`       DECIMAL(4,2) NOT NULL DEFAULT 13.00,
    `pitch`      DECIMAL(4,1) NOT NULL DEFAULT 55.0,
    `bearing`    DECIMAL(5,1) NOT NULL DEFAULT 0.0,
    `summary`    TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

CREATE TABLE `locations` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `district_id`   INT UNSIGNED NOT NULL,
    `name`          VARCHAR(150) NOT NULL,
    `slug`          VARCHAR(170) NOT NULL UNIQUE,
    `category`      ENUM('kecamatan','kelurahan','dukcapil','puskesmas','rumah_sakit',
                         'samsat','polsek','pemadam','satpol_pp','pengadilan',
                         'pengadilan_agama','kejaksaan','kua','bpjs','pertanahan',
                         'perpustakaan','pos','pdam','terminal','sekolah','pasar',
                         'perizinan','dinas','lainnya') NOT NULL DEFAULT 'lainnya',
    `lng`           DECIMAL(10,7) NOT NULL,
    `lat`           DECIMAL(10,7) NOT NULL,
    `address`       VARCHAR(255) NULL,
    `phone`         VARCHAR(40) NULL,
    `email`         VARCHAR(120) NULL,
    `icon`          VARCHAR(40) NULL,
    -- Nama berkas foto gedung di public/uploads/lokasi, bukan path lengkap.
    `cover_image`   VARCHAR(255) NULL,
    -- Dari mana fotonya berasal: nama pemotret, instansi, atau lisensinya.
    -- Alasannya sama seperti fee_basis dan docs_basis — gambar tanpa sumber
    -- tidak bisa ditelusuri warga, dan foto orang lain tidak boleh dipakai
    -- diam-diam.
    `cover_credit`  VARCHAR(160) NULL,
    `short_desc`    VARCHAR(255) NULL,
    `long_desc`     MEDIUMTEXT NULL,
    `open_hours`    JSON NULL,
    `quota_per_day` SMALLINT UNSIGNED NOT NULL DEFAULT 50,
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    -- Dari mana koordinat dan alamatnya berasal. osm = terpetakan di
    -- OpenStreetMap; perkiraan = hanya titik tengah kecamatan, belum
    -- diverifikasi. Dipakai halaman kantor untuk berterus terang.
    `data_source`   ENUM('osm','perkiraan') NOT NULL DEFAULT 'perkiraan',
    `osm_ref`       VARCHAR(40) NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_locations_district`
        FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON DELETE CASCADE,
    INDEX `idx_locations_district` (`district_id`),
    INDEX `idx_locations_category` (`category`),
    INDEX `idx_locations_active` (`is_active`)
) ENGINE = InnoDB;

-- ===================================================== layanan & syarat berkas

CREATE TABLE `services` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `location_id` INT UNSIGNED NOT NULL,
    `name`        VARCHAR(150) NOT NULL,
    `slug`        VARCHAR(170) NOT NULL,
    `description` TEXT NULL,
    `sla_days`    SMALLINT UNSIGNED NULL,
    -- NULL berbeda dengan 0. 0 berarti layanan ini memang gratis menurut
    -- peraturan; NULL berarti tarifnya belum dikonfirmasi ke instansi.
    -- Menampilkan keduanya sebagai "Gratis" pernah membuat halaman ini
    -- menjanjikan layanan berbayar sebagai cuma-cuma.
    `fee`         DECIMAL(12,2) NULL DEFAULT NULL,
    -- Dasar hukum atau keterangan tarif, mis. "PP 76/2020 (PNBP Polri)".
    `fee_basis`   VARCHAR(180) NULL,
    -- Dari mana daftar syarat berkasnya berasal. Sama seperti fee_basis:
    -- daftar berkas tanpa sumber tidak bisa diperiksa warga, dan yang
    -- hanya susunan umum harus mengaku begitu.
    `docs_basis`  VARCHAR(180) NULL,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order`  SMALLINT NOT NULL DEFAULT 0,
    CONSTRAINT `fk_services_location`
        FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `uq_services_slug` (`location_id`, `slug`),
    INDEX `idx_services_active` (`location_id`, `is_active`)
) ENGINE = InnoDB;

CREATE TABLE `service_requirements` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `service_id`   INT UNSIGNED NOT NULL,
    `label`        VARCHAR(180) NOT NULL,
    `note`         VARCHAR(255) NULL,
    `is_mandatory` TINYINT(1) NOT NULL DEFAULT 1,
    `needs_upload` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order`   SMALLINT NOT NULL DEFAULT 0,
    CONSTRAINT `fk_requirements_service`
        FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
    INDEX `idx_requirements_service` (`service_id`, `sort_order`)
) ENGINE = InnoDB;

-- ===================================================================== akun

CREATE TABLE `users` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role`          ENUM('user','officer','admin','superadmin') NOT NULL DEFAULT 'user',
    `location_id`   INT UNSIGNED NULL COMMENT 'wajib untuk officer dan admin',
    `name`          VARCHAR(120) NOT NULL,
    `email`         VARCHAR(160) NOT NULL UNIQUE,
    `phone`         VARCHAR(30) NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `nik`           CHAR(16) NULL UNIQUE,
    `birth_date`    DATE NULL,
    `gender`        ENUM('L','P') NULL,
    `address`       VARCHAR(255) NULL,
    `district_id`   INT UNSIGNED NULL,
    `avatar`        VARCHAR(255) NULL,
    `is_verified`   TINYINT(1) NOT NULL DEFAULT 0,
    -- Alamat baru yang diminta pemilik akun tetapi belum dibuktikan.
    -- Alamat lama tetap berlaku sampai tautannya dibuka: kalau alamat baru
    -- salah ketik, pemiliknya tidak kehilangan satu-satunya jalan masuk.
    `pending_email` VARCHAR(160) NULL,
    `last_login_at` DATETIME NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_users_location`
        FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_users_district`
        FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON DELETE SET NULL,
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_location` (`location_id`)
) ENGINE = InnoDB;

-- Tautan verifikasi email.
--
-- Yang disimpan hanya SHA-256 dari tokennya, bukan tokennya sendiri: isi
-- tabel ini setara kunci masuk, dan basis data yang bocor tidak boleh
-- sekaligus menyerahkan tautan yang masih berlaku. Token aslinya hanya
-- pernah ada di email yang dikirim.
CREATE TABLE `email_verifications` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED NOT NULL,
    `token_hash` CHAR(64) NOT NULL UNIQUE,
    `expires_at` DATETIME NOT NULL,
    `used_at`    DATETIME NULL,
    `ip`         VARCHAR(45) NULL COMMENT 'peminta tautan, untuk menelusuri penyalahgunaan',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_email_verifications_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_email_verifications_user` (`user_id`)
) ENGINE = InnoDB;

-- ================================================================== antrean

CREATE TABLE `queues` (
    `id`             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code`           CHAR(10) NOT NULL UNIQUE COMMENT 'WIA-XXXXX',
    `location_id`    INT UNSIGNED NOT NULL,
    `service_id`     INT UNSIGNED NOT NULL,
    `user_id`        INT UNSIGNED NOT NULL,
    `officer_id`     INT UNSIGNED NULL,
    `queue_date`     DATE NOT NULL,
    `queue_number`   SMALLINT UNSIGNED NOT NULL,
    `slot_time`      TIME NULL,
    `status`         ENUM('baru','ditinjau','ditangani','selesai','dibatalkan')
                     NOT NULL DEFAULT 'baru',
    `note_user`      TEXT NULL,
    `note_officer`   TEXT NULL COMMENT 'deskripsi penanganan per antrean',
    `payment_status` ENUM('tidak_perlu','belum_pasti','menunggu','lunas','gagal')
                     NOT NULL DEFAULT 'tidak_perlu',
    `accepted_at`    DATETIME NULL,
    `finished_at`    DATETIME NULL,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_queues_location`
        FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`),
    CONSTRAINT `fk_queues_service`
        FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
    CONSTRAINT `fk_queues_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_queues_officer`
        FOREIGN KEY (`officer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    UNIQUE KEY `uq_queues_number` (`location_id`, `queue_date`, `queue_number`),
    INDEX `idx_queues_status` (`status`),
    INDEX `idx_queues_user` (`user_id`),
    INDEX `idx_queues_officer` (`officer_id`),
    INDEX `idx_queues_date` (`queue_date`),
    INDEX `idx_queues_board` (`location_id`, `queue_date`, `status`)
) ENGINE = InnoDB;

CREATE TABLE `queue_documents` (
    `id`             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `queue_id`       BIGINT UNSIGNED NOT NULL,
    `requirement_id` INT UNSIGNED NULL,
    `file_path`      VARCHAR(255) NOT NULL COMMENT 'relatif terhadap storage/uploads',
    `original_name`  VARCHAR(255) NOT NULL,
    `mime`           VARCHAR(100) NOT NULL,
    `size_bytes`     INT UNSIGNED NOT NULL,
    `verified`       TINYINT(1) NOT NULL DEFAULT 0,
    `uploaded_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_documents_queue`
        FOREIGN KEY (`queue_id`) REFERENCES `queues` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_documents_requirement`
        FOREIGN KEY (`requirement_id`) REFERENCES `service_requirements` (`id`) ON DELETE SET NULL,
    INDEX `idx_documents_queue` (`queue_id`)
) ENGINE = InnoDB;

CREATE TABLE `queue_status_logs` (
    `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `queue_id`    BIGINT UNSIGNED NOT NULL,
    `from_status` VARCHAR(20) NULL,
    `to_status`   VARCHAR(20) NOT NULL,
    `actor_id`    INT UNSIGNED NULL,
    `note`        VARCHAR(255) NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_logs_queue`
        FOREIGN KEY (`queue_id`) REFERENCES `queues` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_logs_actor`
        FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    INDEX `idx_logs_queue` (`queue_id`),
    INDEX `idx_logs_created` (`created_at`)
) ENGINE = InnoDB;

-- ===================================================================== chat

CREATE TABLE `messages` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `queue_id`   BIGINT UNSIGNED NOT NULL COMMENT 'satu thread per antrean',
    `sender_id`  INT UNSIGNED NOT NULL,
    `body`       TEXT NOT NULL,
    `attachment` VARCHAR(255) NULL,
    `read_at`    DATETIME NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_messages_queue`
        FOREIGN KEY (`queue_id`) REFERENCES `queues` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_messages_sender`
        FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
    INDEX `idx_messages_thread` (`queue_id`, `id`)
) ENGINE = InnoDB;

-- =============================================================== pembayaran

CREATE TABLE `payments` (
    `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `queue_id`    BIGINT UNSIGNED NOT NULL,
    `amount`      DECIMAL(12,2) NOT NULL,
    `method`      ENUM('tunai','transfer','qris','va') NOT NULL DEFAULT 'tunai',
    `reference`   VARCHAR(80) NULL,
    `status`      ENUM('menunggu','lunas','gagal','refund') NOT NULL DEFAULT 'menunggu',
    `paid_at`     DATETIME NULL,
    `verified_by` INT UNSIGNED NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_payments_queue`
        FOREIGN KEY (`queue_id`) REFERENCES `queues` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_payments_verifier`
        FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    INDEX `idx_payments_status` (`status`),
    INDEX `idx_payments_created` (`created_at`)
) ENGINE = InnoDB;

-- ================================================================= pendukung

CREATE TABLE `audit_logs` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED NULL,
    `action`     VARCHAR(80) NOT NULL,
    `entity`     VARCHAR(40) NULL,
    `entity_id`  VARCHAR(40) NULL,
    `ip`         VARCHAR(45) NULL,
    `meta`       JSON NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_audit_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    INDEX `idx_audit_created` (`created_at`)
) ENGINE = InnoDB;

CREATE TABLE `login_attempts` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email`      VARCHAR(160) NOT NULL,
    `ip`         VARCHAR(45) NOT NULL,
    `success`    TINYINT(1) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_attempts_email` (`email`, `created_at`),
    INDEX `idx_attempts_ip` (`ip`, `created_at`)
) ENGINE = InnoDB;

-- ================================================== aduan per fasilitas

-- Laporan warga tentang satu kantor: data tidak cocok, pelayanan kurang,
-- fasilitas rusak. Sengaja bisa diisi tanpa akun — justru data yang salah
-- paling sering ditemukan orang yang baru pertama datang dan belum
-- mendaftar. user_id diisi bila pelapor sedang masuk.
CREATE TABLE `facility_reports` (
    `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `location_id`  INT UNSIGNED NOT NULL,
    `user_id`      INT UNSIGNED NULL,
    `category`     ENUM('data_salah','pelayanan','fasilitas','lainnya')
                   NOT NULL DEFAULT 'lainnya',
    `message`      TEXT NOT NULL,
    -- Kontak pelapor opsional: aduan anonim tetap diterima.
    `contact`      VARCHAR(160) NULL,
    `status`       ENUM('baru','ditinjau','ditindaklanjuti','selesai','ditolak')
                   NOT NULL DEFAULT 'baru',
    `admin_note`   TEXT NULL,
    `handled_by`   INT UNSIGNED NULL,
    `ip`           VARCHAR(45) NULL,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_reports_location`
        FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reports_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_reports_handler`
        FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    INDEX `idx_reports_location` (`location_id`, `status`),
    INDEX `idx_reports_status` (`status`, `created_at`),
    INDEX `idx_reports_ip` (`ip`, `created_at`)
) ENGINE = InnoDB;
