-- =============================================
-- BENGKEL BOOKING SYSTEM DATABASE
-- =============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

SET AUTOCOMMIT = 0;

START TRANSACTION;

SET time_zone = "+07:00";

-- Buat database jika belum ada
-- Database creation removed for hosting compatibility

-- =============================================
-- TABEL MASTER USERS (Admin & Customer)
-- =============================================
CREATE TABLE `tb_master_users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `nama` varchar(100) NOT NULL,
    `email` varchar(100) NOT NULL UNIQUE,
    `password` varchar(255) NOT NULL,
    `no_hp` varchar(20) DEFAULT NULL,
    `alamat` text DEFAULT NULL,
    `role` enum('admin', 'customer') NOT NULL DEFAULT 'customer',
    `status` enum('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- =============================================
-- TABEL MASTER LAYANAN
-- =============================================
CREATE TABLE `tb_master_layanan` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `nama_layanan` varchar(100) NOT NULL,
    `deskripsi` text DEFAULT NULL,
    `harga` decimal(12, 2) NOT NULL DEFAULT 0,
    `durasi_menit` int(11) NOT NULL DEFAULT 60 COMMENT 'Estimasi durasi dalam menit',
    `status` enum('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- =============================================
-- TABEL KENDARAAN
-- =============================================
CREATE TABLE `tb_kendaraan` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `merk` varchar(50) NOT NULL,
    `tipe` varchar(50) NOT NULL,
    `tahun` year DEFAULT NULL,
    `plat_nomor` varchar(20) NOT NULL,
    `warna` varchar(30) DEFAULT NULL,
    `deleted_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `tb_kendaraan_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `tb_master_users` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- =============================================
-- TABEL BOOKING
-- =============================================
CREATE TABLE `tb_booking` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `kode_booking` varchar(20) NOT NULL UNIQUE,
    `user_id` int(11) NOT NULL,
    `kendaraan_id` int(11) NOT NULL,
    `tanggal_booking` date NOT NULL,
    `jam_booking` time NOT NULL,
    `keluhan` text DEFAULT NULL,
    `total_harga` decimal(12, 2) NOT NULL DEFAULT 0,
    `status` enum(
        'menunggu',
        'dikonfirmasi',
        'dikerjakan',
        'selesai',
        'dibatalkan'
    ) NOT NULL DEFAULT 'menunggu',
    `catatan_admin` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `kendaraan_id` (`kendaraan_id`),
    KEY `tanggal_booking` (`tanggal_booking`),
    CONSTRAINT `tb_booking_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `tb_master_users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `tb_booking_ibfk_2` FOREIGN KEY (`kendaraan_id`) REFERENCES `tb_kendaraan` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- =============================================
-- TABEL BOOKING_LAYANAN (Many-to-Many)
-- =============================================
CREATE TABLE `tb_booking_layanan` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `booking_id` int(11) NOT NULL,
    `layanan_id` int(11) NOT NULL,
    `harga` decimal(12, 2) NOT NULL COMMENT 'Harga saat booking dibuat',
    PRIMARY KEY (`id`),
    KEY `booking_id` (`booking_id`),
    KEY `layanan_id` (`layanan_id`),
    CONSTRAINT `tb_booking_layanan_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `tb_booking` (`id`) ON DELETE CASCADE,
    CONSTRAINT `tb_booking_layanan_ibfk_2` FOREIGN KEY (`layanan_id`) REFERENCES `tb_master_layanan` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- =============================================
-- TABEL JAM OPERASIONAL
-- =============================================
CREATE TABLE `tb_jam_operasional` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `hari` enum(
        'senin',
        'selasa',
        'rabu',
        'kamis',
        'jumat',
        'sabtu',
        'minggu'
    ) NOT NULL,
    `jam_buka` time NOT NULL,
    `jam_tutup` time NOT NULL,
    `is_buka` tinyint(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `hari` (`hari`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- =============================================
-- TABEL SLOT WAKTU
-- =============================================
CREATE TABLE `tb_slot_waktu` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `jam_mulai` time NOT NULL,
    `jam_selesai` time NOT NULL,
    `kapasitas` int(11) NOT NULL DEFAULT 2 COMMENT 'Maks booking per slot',
    `status` enum('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- =============================================
-- TABEL TANGGAL LIBUR
-- =============================================
CREATE TABLE `tb_tanggal_libur` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `tanggal` date NOT NULL UNIQUE,
    `keterangan` varchar(100) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- =============================================
-- TABEL PENGATURAN
-- =============================================
CREATE TABLE `tb_pengaturan` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `nama_bengkel` varchar(100) NOT NULL DEFAULT 'Bengkel AutoCare',
    `alamat_bengkel` text DEFAULT NULL,
    `no_telp` varchar(20) DEFAULT NULL,
    `email_bengkel` varchar(100) DEFAULT NULL,
    `deskripsi` text DEFAULT NULL,
    `max_booking_per_slot` int(11) NOT NULL DEFAULT 3,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- =============================================
-- DATA SAMPLE
-- =============================================

-- Admin default (password: admin123)
INSERT INTO
    `tb_master_users` (
        `nama`,
        `email`,
        `password`,
        `no_hp`,
        `role`,
        `status`
    )
VALUES (
        'Administrator',
        'admin@bengkel.com',
        '$2y$12$9uNPnWfmQrmFwDOxYQm7MeC.M/uapwntLu7epbhK6zt5JnQ4of45u',
        '081234567890',
        'admin',
        'aktif'
    );

-- Customer sample (password: customer123)
INSERT INTO
    `tb_master_users` (
        `nama`,
        `email`,
        `password`,
        `no_hp`,
        `alamat`,
        `role`,
        `status`
    )
VALUES (
        'Budi Santoso',
        'budi@email.com',
        '$2y$12$hpw3OfRLrt0CP0iXmc6Sd.9PMhd7OZpXPpvumvXXLHkgvRNEPIj.C',
        '081111222333',
        'Jl. Merdeka No. 123, Jakarta',
        'customer',
        'aktif'
    ),
    (
        'Siti Aminah',
        'siti@email.com',
        '$2y$12$hpw3OfRLrt0CP0iXmc6Sd.9PMhd7OZpXPpvumvXXLHkgvRNEPIj.C',
        '082222333444',
        'Jl. Sudirman No. 456, Jakarta',
        'customer',
        'aktif'
    );

-- Layanan bengkel
INSERT INTO
    `tb_master_layanan` (
        `nama_layanan`,
        `deskripsi`,
        `harga`,
        `durasi_menit`,
        `status`
    )
VALUES (
        'Service Ringan',
        'Pengecekan kondisi mesin, oli, filter, dan komponen dasar',
        150000.00,
        60,
        'aktif'
    ),
    (
        'Service Besar',
        'Service lengkap termasuk tune-up, penggantian spare part aus',
        500000.00,
        180,
        'aktif'
    ),
    (
        'Ganti Oli Mesin',
        'Penggantian oli mesin dengan oli berkualitas',
        100000.00,
        30,
        'aktif'
    ),
    (
        'Ganti Oli Gardan',
        'Penggantian oli gardan untuk transmisi manual',
        80000.00,
        30,
        'aktif'
    ),
    (
        'Ganti Ban',
        'Penggantian ban kendaraan (harga belum termasuk ban)',
        50000.00,
        45,
        'aktif'
    ),
    (
        'Spooring & Balancing',
        'Penyetelan kemiringan roda dan balancing',
        200000.00,
        60,
        'aktif'
    ),
    (
        'Tune Up Mesin',
        'Perbaikan dan penyetelan performa mesin',
        300000.00,
        120,
        'aktif'
    ),
    (
        'Ganti Kampas Rem',
        'Penggantian kampas rem depan/belakang',
        150000.00,
        60,
        'aktif'
    ),
    (
        'AC Service',
        'Pengecekan dan pengisian freon AC mobil',
        250000.00,
        90,
        'aktif'
    ),
    (
        'Cuci Mobil',
        'Cuci steam mobil lengkap interior dan eksterior',
        75000.00,
        45,
        'aktif'
    );

-- Jam operasional default
INSERT INTO
    `tb_jam_operasional` (
        `hari`,
        `jam_buka`,
        `jam_tutup`,
        `is_buka`
    )
VALUES (
        'senin',
        '08:00:00',
        '17:00:00',
        1
    ),
    (
        'selasa',
        '08:00:00',
        '17:00:00',
        1
    ),
    (
        'rabu',
        '08:00:00',
        '17:00:00',
        1
    ),
    (
        'kamis',
        '08:00:00',
        '17:00:00',
        1
    ),
    (
        'jumat',
        '08:00:00',
        '17:00:00',
        1
    ),
    (
        'sabtu',
        '08:00:00',
        '15:00:00',
        1
    ),
    (
        'minggu',
        '00:00:00',
        '00:00:00',
        0
    );

-- Slot waktu booking
INSERT INTO
    `tb_slot_waktu` (
        `jam_mulai`,
        `jam_selesai`,
        `kapasitas`,
        `status`
    )
VALUES (
        '08:00:00',
        '09:00:00',
        3,
        'aktif'
    ),
    (
        '09:00:00',
        '10:00:00',
        3,
        'aktif'
    ),
    (
        '10:00:00',
        '11:00:00',
        3,
        'aktif'
    ),
    (
        '11:00:00',
        '12:00:00',
        3,
        'aktif'
    ),
    (
        '13:00:00',
        '14:00:00',
        3,
        'aktif'
    ),
    (
        '14:00:00',
        '15:00:00',
        3,
        'aktif'
    ),
    (
        '15:00:00',
        '16:00:00',
        3,
        'aktif'
    ),
    (
        '16:00:00',
        '17:00:00',
        3,
        'aktif'
    );

-- Pengaturan bengkel
INSERT INTO
    `tb_pengaturan` (
        `nama_bengkel`,
        `alamat_bengkel`,
        `no_telp`,
        `email_bengkel`,
        `deskripsi`,
        `max_booking_per_slot`
    )
VALUES (
        'Bengkel AutoCare',
        'Jl. Raya Otomotif No. 88, Jakarta Timur',
        '021-12345678',
        'info@bengkelautocare.com',
        'Bengkel terpercaya dengan layanan profesional dan harga bersahabat. Melayani berbagai jenis kendaraan roda 4.',
        3
    );

-- Kendaraan sample
INSERT INTO
    `tb_kendaraan` (
        `user_id`,
        `merk`,
        `tipe`,
        `tahun`,
        `plat_nomor`,
        `warna`
    )
VALUES (
        2,
        'Toyota',
        'Avanza 1.3 G',
        2020,
        'B 1234 ABC',
        'Putih'
    ),
    (
        2,
        'Honda',
        'Jazz RS',
        2019,
        'B 5678 DEF',
        'Merah'
    ),
    (
        3,
        'Suzuki',
        'Ertiga GL',
        2021,
        'B 9999 XYZ',
        'Hitam'
    );

-- Tanggal libur sample
INSERT INTO
    `tb_tanggal_libur` (`tanggal`, `keterangan`)
VALUES (
        '2026-01-01',
        'Tahun Baru 2026'
    ),
    (
        '2026-03-29',
        'Hari Raya Nyepi'
    ),
    (
        '2026-04-03',
        'Wafat Isa Almasih'
    ),
    (
        '2026-05-01',
        'Hari Buruh Internasional'
    ),
    (
        '2026-08-17',
        'Hari Kemerdekaan RI'
    ),
    (
        '2026-12-25',
        'Hari Raya Natal'
    );

COMMIT;