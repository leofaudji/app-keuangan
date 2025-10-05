SET FOREIGN_KEY_CHECKS = 0;

-- Hapus tabel lama jika ada
DROP TABLE IF EXISTS `transaksi`, `anggaran`, `users`, `settings`, `accounts`, `activity_log`,`jurnal_entries`,`jurnal_details`,`general_ledger`, `suppliers`, `consignment_items`,`recurring_templates`, `bank_reconciliations`, `bank_statement_lines`, `fixed_assets`, `customers`, `invoices`, `invoice_items`, `payments_received`;

SET FOREIGN_KEY_CHECKS = 1;

-- Tabel untuk pengguna aplikasi
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Master Chart of Accounts (COA)
CREATE TABLE `accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `kode_akun` varchar(20) NOT NULL,
  `nama_akun` varchar(100) NOT NULL,
  `tipe_akun` enum('Aset','Liabilitas','Ekuitas','Pendapatan','Beban') NOT NULL,
  `saldo_normal` enum('Debit','Kredit') NOT NULL,
  `cash_flow_category` enum('Operasi','Investasi','Pendanaan') DEFAULT NULL,
  `is_kas` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Flag jika ini adalah akun kas/bank',
  `saldo_awal` decimal(15,2) NOT NULL DEFAULT 0.00,  
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_kode_akun` (`user_id`,`kode_akun`),
  KEY `parent_id` (`parent_id`),
  FOREIGN KEY (`parent_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel utama untuk semua transaksi
CREATE TABLE `transaksi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `account_id` int(11) DEFAULT NULL COMMENT 'Akun yang didebit/dikredit (Pendapatan/Beban/Utang)',
  `tanggal` date NOT NULL,
  `jenis` enum('pemasukan','pengeluaran','transfer') NOT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `keterangan` text DEFAULT NULL,  
  `nomor_referensi` varchar(50) DEFAULT NULL COMMENT 'Nomor faktur/transaksi',
  `kas_account_id` int(11) NOT NULL COMMENT 'Akun kas/bank yang terpengaruh',
  `kas_tujuan_account_id` int(11) DEFAULT NULL COMMENT 'Untuk transfer antar akun kas',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `status` enum('aktif','dibatalkan') NOT NULL DEFAULT 'aktif',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`kas_account_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`kas_tujuan_account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Buku Besar Umum (General Ledger) - PUSAT DATA AKUNTANSI
CREATE TABLE `general_ledger` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `qty` int(11) DEFAULT NULL COMMENT 'Quantity for consignment sales',
  `consignment_item_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `nomor_referensi` varchar(50) DEFAULT NULL,
  `account_id` int(11) NOT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `kredit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `ref_id` int(11) NOT NULL COMMENT 'ID dari tabel sumber (transaksi atau jurnal_entries)',
  `ref_type` enum('transaksi','jurnal') NOT NULL COMMENT 'Tabel sumber',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `account_id` (`account_id`),
  KEY `ref_id_type` (`ref_id`,`ref_type`),
  KEY `tanggal` (`tanggal`),
  KEY `consignment_item_id` (`consignment_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Pemasok (untuk konsinyasi)
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `nama_pemasok` varchar(100) NOT NULL,
  `kontak` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),  
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Barang Konsinyasi
CREATE TABLE `consignment_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `nama_barang` varchar(100) NOT NULL,
  `harga_jual` decimal(15,2) NOT NULL,
  `harga_beli` decimal(15,2) NOT NULL COMMENT 'Harga yang harus dibayar ke pemasok',
  `stok_awal` int(11) NOT NULL DEFAULT 0,
  `tanggal_terima` date NOT NULL,  
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel untuk header entri jurnal umum (majemuk)
CREATE TABLE `jurnal_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `keterangan` varchar(255) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `status` enum('aktif','dibatalkan') NOT NULL DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel untuk template transaksi/jurnal berulang
CREATE TABLE `recurring_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL COMMENT 'Nama template, cth: "Sewa Kantor Bulanan"',
  `frequency_unit` enum('day','week','month','year') NOT NULL,
  `frequency_interval` int(11) NOT NULL DEFAULT 1 COMMENT 'cth: 1 bulan, 2 minggu',
  `start_date` date NOT NULL,
  `next_run_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `template_type` enum('transaksi','jurnal') NOT NULL,
  `template_data` json NOT NULL COMMENT 'Data JSON dari transaksi/jurnal yang akan dibuat',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `next_run_date` (`next_run_date`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel untuk menyimpan header setiap event rekonsiliasi
CREATE TABLE `reconciliations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `statement_date` date NOT NULL COMMENT 'Tanggal akhir periode rekonsiliasi',
  `statement_balance` decimal(15,2) NOT NULL COMMENT 'Saldo akhir dari rekening koran',
  `cleared_balance` decimal(15,2) NOT NULL COMMENT 'Saldo buku setelah transaksi yang cocok dibersihkan',
  `difference` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `account_id` (`account_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel untuk detail/baris entri jurnal umum
CREATE TABLE `jurnal_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jurnal_entry_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `kredit` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`jurnal_entry_id`) REFERENCES `jurnal_entries` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel untuk anggaran
CREATE TABLE `anggaran` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `periode_tahun` smallint(4) NOT NULL,
  `jumlah_anggaran` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_account_periode` (`user_id`,`account_id`,`periode_tahun`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel untuk pengaturan
CREATE TABLE `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel untuk log aktivitas
CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Data Awal
INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `role`) VALUES (1, 'admin', '{$default_password_hash}', 'Administrator', 'admin'), (2, 'user', '{$default_password_hash}', 'User Biasa', 'user');

-- Data Demo Bagan Akun (COA) untuk user 'admin' (user_id = 1)
-- Saldo awal neraca sudah diatur di sini.
-- CONTOH UNTUK KOPERASI TOKO SEKOLAH
INSERT INTO `accounts` (`id`, `user_id`, `parent_id`, `kode_akun`, `nama_akun`, `tipe_akun`, `saldo_normal`, `cash_flow_category`, `is_kas`, `saldo_awal`) VALUES
-- Aset
(100, 1, NULL, '1', 'Aset', 'Aset', 'Debit', NULL, 0, 0.00),
    (101, 1, 100, '1-1000', 'Aset Lancar', 'Aset', 'Debit', NULL, 0, 0.00),
        (102, 1, 101, '1-1100', 'Kas dan Setara Kas', 'Aset', 'Debit', NULL, 0, 0.00),
            (103, 1, 102, '1-1110', 'Kas di Tangan', 'Aset', 'Debit', NULL, 1, 2000000.00),
            (104, 1, 102, '1-1120', 'Kas di Bank', 'Aset', 'Debit', NULL, 1, 10000000.00),
        (105, 1, 101, '1-1200', 'Persediaan Barang Dagang', 'Aset', 'Debit', 'Operasi', 0, 15000000.00),
    (106, 1, 100, '1-2000', 'Aset Tetap', 'Aset', 'Debit', 'Investasi', 0, 0.00),
        (107, 1, 106, '1-2100', 'Peralatan Toko', 'Aset', 'Debit', 'Investasi', 0, 5000000.00),

-- Liabilitas
(200, 1, NULL, '2', 'Liabilitas', 'Liabilitas', 'Kredit', NULL, 0, 0.00),
    (201, 1, 200, '2-1000', 'Liabilitas Jangka Pendek', 'Liabilitas', 'Kredit', NULL, 0, 0.00),
        (202, 1, 201, '2-1100', 'Utang Dagang', 'Liabilitas', 'Kredit', 'Operasi', 0, 3000000.00),

-- Ekuitas
(300, 1, NULL, '3', 'Ekuitas', 'Ekuitas', 'Kredit', NULL, 0, 0.00),
    (301, 1, 300, '3-1100', 'Simpanan Pokok Anggota', 'Ekuitas', 'Kredit', 'Pendanaan', 0, 20000000.00),
    (302, 1, 300, '3-1200', 'Simpanan Sukarela Anggota', 'Ekuitas', 'Kredit', 'Pendanaan', 0, 0.00),
    (303, 1, 300, '3-2100', 'SHU Ditahan', 'Ekuitas', 'Kredit', NULL, 0, 9000000.00),

-- Pendapatan
(400, 1, NULL, '4', 'Pendapatan', 'Pendapatan', 'Kredit', NULL, 0, 0.00),
    (401, 1, 400, '4-1000', 'Pendapatan Penjualan Barang', 'Pendapatan', 'Kredit', 'Operasi', 0, 0.00),
    (402, 1, 400, '4-2000', 'Pendapatan Konsinyasi', 'Pendapatan', 'Kredit', 'Operasi', 0, 0.00),

-- Beban Pokok Penjualan (COGS)
(500, 1, NULL, '5', 'Beban Pokok Penjualan', 'Beban', 'Debit', 'Operasi', 0, 0.00),

-- Beban Operasional
(600, 1, NULL, '6', 'Beban Operasional', 'Beban', 'Debit', NULL, 0, 0.00),
    (601, 1, 600, '6-1100', 'Beban Gaji Karyawan', 'Beban', 'Debit', 'Operasi', 0, 0.00),
    (602, 1, 600, '6-1200', 'Beban Listrik & Air', 'Beban', 'Debit', 'Operasi', 0, 0.00),
    (603, 1, 600, '6-1300', 'Beban Perlengkapan Toko', 'Beban', 'Debit', 'Operasi', 0, 0.00),
    (108, 1, 106, '1-2101', 'Akumulasi Penyusutan - Peralatan', 'Aset', 'Kredit', NULL, 0, 0.00),
    (604, 1, 600, '6-1400', 'Beban Penyusutan - Peralatan', 'Beban', 'Debit', 'Operasi', 0, 0.00);

-- Data Demo Transaksi
-- Transaksi Sederhana (Pemasukan & Pengeluaran Kas)
INSERT INTO `transaksi` (`user_id`, `tanggal`, `jenis`, `jumlah`, `keterangan`, `nomor_referensi`, `account_id`, `kas_account_id`, `kas_tujuan_account_id`) VALUES
-- JAN
(1, CONCAT(YEAR(CURDATE()), '-01-15'), 'pemasukan', 7500000.00, 'Penjualan tunai ATK dan seragam', 'INV/2024/01/001', 401, 103, NULL),
(1, CONCAT(YEAR(CURDATE()), '-01-28'), 'pengeluaran', 250000.00, 'Pembayaran listrik dan air Januari', 'BILL/2024/01/01', 602, 103, NULL),
-- FEB
(1, CONCAT(YEAR(CURDATE()), '-02-10'), 'pengeluaran', 2000000.00, 'Pembayaran sebagian utang ke Supplier Buku', 'PAY/2024/02/01', 202, 104, NULL),
(1, CONCAT(YEAR(CURDATE()), '-02-20'), 'pemasukan', 8200000.00, 'Penjualan tunai Februari', 'INV/2024/02/001', 401, 103, NULL),
-- MAR
(1, CONCAT(YEAR(CURDATE()), '-03-25'), 'pengeluaran', 1500000.00, 'Gaji karyawan toko Maret', 'PAY/2024/03/02', 601, 104, NULL),
-- APR
(1, CONCAT(YEAR(CURDATE()), '-04-18'), 'pemasukan', 9500000.00, 'Penjualan tunai April', 'INV/2024/04/001', 401, 103, NULL),
-- MEI
(1, CONCAT(YEAR(CURDATE()), '-05-05'), 'pengeluaran', 150000.00, 'Pembelian perlengkapan toko (kantong plastik, dll)', 'EXP/2024/05/01', 603, 103, NULL),
-- JUN
(1, CONCAT(YEAR(CURDATE()), '-06-25'), 'pengeluaran', 1500000.00, 'Gaji karyawan toko Juni', 'PAY/2024/06/01', 601, 104, NULL),
-- JUL (Tahun Ajaran Baru)
(1, CONCAT(YEAR(CURDATE()), '-07-15'), 'pemasukan', 25000000.00, 'Penjualan buku dan seragam tahun ajaran baru', 'INV/2024/07/001', 401, 104, NULL),
-- AGU
(1, CONCAT(YEAR(CURDATE()), '-08-10'), 'pemasukan', 11000000.00, 'Penjualan tunai Agustus', 'INV/2024/08/001', 401, 103, NULL),
-- SEP
(1, CONCAT(YEAR(CURDATE()), '-09-25'), 'pengeluaran', 1500000.00, 'Gaji karyawan toko September', 'PAY/2024/09/01', 601, 104, NULL);

-- Transaksi Majemuk (Jurnal Umum)
INSERT INTO `jurnal_entries` (`id`, `user_id`, `tanggal`, `keterangan`) VALUES
(101, 1, CONCAT(YEAR(CURDATE()), '-01-10'), 'Pembelian barang dagang (buku tulis) dari Supplier A secara kredit'),
(102, 1, CONCAT(YEAR(CURDATE()), '-01-15'), 'Pencatatan HPP atas penjualan tunai Januari'),
(103, 1, CONCAT(YEAR(CURDATE()), '-02-20'), 'Pencatatan HPP atas penjualan tunai Februari'),
(104, 1, CONCAT(YEAR(CURDATE()), '-04-05'), 'Pembelian barang dagang (seragam) dari Supplier B secara kredit'),
(105, 1, CONCAT(YEAR(CURDATE()), '-04-18'), 'Pencatatan HPP atas penjualan tunai April'),
(106, 1, CONCAT(YEAR(CURDATE()), '-07-15'), 'Pencatatan HPP atas penjualan tahun ajaran baru'),
(107, 1, CONCAT(YEAR(CURDATE()), '-08-10'), 'Pencatatan HPP atas penjualan tunai Agustus'),
(108, 1, CONCAT(YEAR(CURDATE()), '-03-01'), 'Pembelian Komputer Baru untuk Kantor');

INSERT INTO `jurnal_details` (`jurnal_entry_id`, `account_id`, `debit`, `kredit`) VALUES
-- Jurnal 101: Beli persediaan kredit
(101, 105, 5000000.00, 0.00), -- (Db) Persediaan Barang Dagang
(101, 202, 0.00, 5000000.00), -- (Cr) Utang Dagang
-- Jurnal 102: HPP Januari (asumsi 60% dari penjualan 7.5jt)
(102, 500, 4500000.00, 0.00), -- (Db) Beban Pokok Penjualan
(102, 105, 0.00, 4500000.00), -- (Cr) Persediaan Barang Dagang
-- Jurnal 103: HPP Februari (asumsi 60% dari penjualan 8.2jt)
(103, 500, 4920000.00, 0.00), -- (Db) Beban Pokok Penjualan
(103, 105, 0.00, 4920000.00), -- (Cr) Persediaan Barang Dagang
-- Jurnal 104: Beli persediaan kredit
(104, 105, 10000000.00, 0.00), -- (Db) Persediaan Barang Dagang
(104, 202, 0.00, 10000000.00), -- (Cr) Utang Dagang
-- Jurnal 105: HPP April (asumsi 60% dari penjualan 9.5jt)
(105, 500, 5700000.00, 0.00), -- (Db) Beban Pokok Penjualan
(105, 105, 0.00, 5700000.00), -- (Cr) Persediaan Barang Dagang
-- Jurnal 106: HPP Juli (asumsi 60% dari penjualan 25jt)
(106, 500, 15000000.00, 0.00), -- (Db) Beban Pokok Penjualan
(106, 105, 0.00, 15000000.00), -- (Cr) Persediaan Barang Dagang
-- Jurnal 107: HPP Agustus (asumsi 60% dari penjualan 11jt)
(107, 500, 6600000.00, 0.00), -- (Db) Beban Pokok Penjualan
(107, 105, 0.00, 6600000.00), -- (Cr) Persediaan Barang Dagang
-- Jurnal 108: Beli Aset
(108, 107, 7000000.00, 0.00), -- (Db) Peralatan Toko
(108, 104, 0.00, 7000000.00); -- (Cr) Kas di Bank

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('app_name', 'UangKu'),
('notification_interval', '60000'),
('retained_earnings_account_id', '303'),
('period_lock_date', CONCAT(YEAR(CURDATE())-1, '-12-31')),
('consignment_cash_account', '103'),
('consignment_revenue_account', '402'),
('consignment_cogs_account', '501'),
('consignment_payable_account', '203');

-- Data Demo Konsinyasi
INSERT INTO `suppliers` (`id`, `user_id`, `nama_pemasok`, `kontak`) VALUES
(1, 1, 'Penerbit Erlangga', '021-8717006'),
(2, 1, 'CV. Seragam Jaya', '08123456789');

INSERT INTO `consignment_items` (`id`, `user_id`, `supplier_id`, `nama_barang`, `harga_jual`, `harga_beli`, `stok_awal`, `tanggal_terima`) VALUES
(1, 1, 1, 'Buku Tulis Sinar Dunia 38 Lbr', 3500.00, 2500.00, 100, CONCAT(YEAR(CURDATE()), '-01-05')),
(2, 1, 2, 'Seragam SD Merah Putih', 75000.00, 60000.00, 50, CONCAT(YEAR(CURDATE()), '-01-05'));

-- Data Demo Anggaran
INSERT INTO `anggaran` (`user_id`, `account_id`, `periode_tahun`, `jumlah_anggaran`) VALUES
(1, 601, YEAR(CURDATE()), 18000000.00), -- Gaji: 1.5jt/bulan * 12
(1, 602, YEAR(CURDATE()), 3600000.00),  -- Listrik: 300rb/bulan * 12
(1, 603, YEAR(CURDATE()), 1200000.00); -- Perlengkapan: 100rb/bulan * 12

-- Data Demo Transaksi Berulang
INSERT INTO `recurring_templates` (`user_id`, `name`, `frequency_unit`, `frequency_interval`, `start_date`, `next_run_date`, `template_type`, `template_data`) VALUES
(1, 'Beban Sewa Toko Bulanan', 'month', 1, CONCAT(YEAR(CURDATE()), '-01-25'), CONCAT(YEAR(CURDATE()), '-01-25'), 'jurnal', '{"keterangan": "Pembayaran sewa toko bulanan", "lines": [{"account_id": "605", "debit": 500000, "kredit": 0}, {"account_id": "104", "debit": 0, "kredit": 500000}]}');

ALTER TABLE `general_ledger`
ADD COLUMN `is_reconciled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=Belum, 1=Sudah direkonsiliasi',
ADD COLUMN `reconciliation_date` DATE DEFAULT NULL COMMENT 'Tanggal proses rekonsiliasi dilakukan';
ALTER TABLE `general_ledger` ADD COLUMN `reconciliation_id` INT(11) DEFAULT NULL AFTER `reconciliation_date`;
ALTER TABLE `general_ledger` ADD KEY `idx_reconciliation_id` (`reconciliation_id`);

-- Tambahkan index untuk mempercepat query
CREATE INDEX `idx_reconciliation` ON `general_ledger` (`account_id`, `is_reconciled`, `tanggal`);

ALTER TABLE `users`
ADD COLUMN `remember_selector` VARCHAR(255) NULL DEFAULT NULL AFTER `reset_token_expires_at`,
ADD COLUMN `remember_validator_hash` VARCHAR(255) NULL DEFAULT NULL AFTER `remember_selector`;

CREATE INDEX `remember_selector_idx` ON `users` (`remember_selector`);
