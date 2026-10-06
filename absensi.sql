-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 01, 2026 at 08:58 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `absensi`
--

-- --------------------------------------------------------

--
-- Table structure for table `absensi`
--

CREATE TABLE `absensi` (
  `id` int NOT NULL,
  `karyawan_id` int NOT NULL,
  `proyek_id` int NOT NULL DEFAULT '1',
  `tanggal` date NOT NULL,
  `jam_masuk` time NOT NULL,
  `lat_masuk` decimal(10,6) NOT NULL,
  `long_masuk` decimal(10,6) NOT NULL,
  `foto_masuk` varchar(255) DEFAULT '',
  `status` varchar(50) NOT NULL,
  `jam_pulang` time DEFAULT NULL,
  `foto_pulang` varchar(255) DEFAULT '',
  `total_jam_kerja` varchar(50) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `lokasi` varchar(255) DEFAULT NULL,
  `lat_pulang` varchar(50) DEFAULT NULL,
  `long_pulang` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `karyawan`
--

CREATE TABLE `karyawan` (
  `id` int NOT NULL,
  `nama_lengkap` varchar(150) DEFAULT NULL,
  `nip` varchar(50) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `upah_harian` decimal(10,2) DEFAULT '150000.00',
  `upah_lembur` decimal(10,2) DEFAULT '40000.00',
  `nik` varchar(20) DEFAULT NULL,
  `no_hp` varchar(15) DEFAULT NULL,
  `alamat` text,
  `foto_ktp` varchar(255) DEFAULT NULL,
  `foto_profil` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kasbon_pekerja`
--

CREATE TABLE `kasbon_pekerja` (
  `id` int NOT NULL,
  `karyawan_id` int NOT NULL,
  `tanggal` date NOT NULL,
  `jumlah_kasbon` decimal(10,2) DEFAULT '0.00',
  `keterangan` text,
  `jumlah` int DEFAULT '0',
  `status` enum('Pending','Disetujui','Ditolak') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kunjungan_lapangan`
--

CREATE TABLE `kunjungan_lapangan` (
  `id` int NOT NULL,
  `karyawan_id` int NOT NULL,
  `nama_pelanggan` varchar(150) NOT NULL,
  `alamat_pelanggan` text NOT NULL,
  `catatan` text,
  `latitude` varchar(50) DEFAULT NULL,
  `longitude` varchar(50) DEFAULT NULL,
  `waktu_masuk` datetime NOT NULL,
  `waktu_keluar` datetime DEFAULT NULL,
  `status` enum('Proses','Selesai') DEFAULT 'Proses',
  `foto_dokumentasi` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kunjungan_tim`
--

CREATE TABLE `kunjungan_tim` (
  `id` int NOT NULL,
  `kunjungan_id` int NOT NULL,
  `karyawan_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lembur_manual`
--

CREATE TABLE `lembur_manual` (
  `id` int NOT NULL,
  `karyawan_id` int NOT NULL,
  `bulan_tahun` varchar(7) NOT NULL,
  `nominal` decimal(12,2) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `log_aktivitas`
--

CREATE TABLE `log_aktivitas` (
  `id` int NOT NULL,
  `karyawan_id` int NOT NULL,
  `jenis_aktivitas` varchar(100) NOT NULL,
  `deskripsi` text NOT NULL,
  `waktu` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `lokasi_proyek`
--

CREATE TABLE `lokasi_proyek` (
  `id` int NOT NULL,
  `nama_proyek` varchar(150) NOT NULL,
  `alamat` text,
  `latitude_pusat` decimal(10,6) NOT NULL,
  `longitude_pusat` decimal(10,6) NOT NULL,
  `radius_meter` int NOT NULL,
  `status` enum('Aktif','Selesai') DEFAULT 'Aktif',
  `radius` int DEFAULT '500'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `lokasi_proyek`
--

INSERT INTO `lokasi_proyek` (`id`, `nama_proyek`, `alamat`, `latitude_pusat`, `longitude_pusat`, `radius_meter`, `status`, `radius`) VALUES
(1, 'KANTOR UTAMA', 'Jalan By Pass, Padang, Sumatera Barat, Indonesia', '-0.933813', '100.398666', 250, 'Aktif', 250);

-- --------------------------------------------------------

--
-- Table structure for table `pengaturan_gaji`
--

CREATE TABLE `pengaturan_gaji` (
  `id` int NOT NULL,
  `profesi` varchar(50) NOT NULL,
  `upah_harian` decimal(10,2) DEFAULT '0.00',
  `upah_lembur` decimal(10,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pengaturan_gaji`
--

INSERT INTO `pengaturan_gaji` (`id`, `profesi`, `upah_harian`, `upah_lembur`) VALUES
(1, 'Office', '200000.00', '50000.00'),
(2, 'HVAC', '150000.00', '40000.00'),
(3, 'Electrical', '145000.00', '35000.00'),
(4, 'Plumbing', '155000.00', '40000.00'),
(5, 'Hydrant', '160000.00', '40000.00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `karyawan_id` (`karyawan_id`),
  ADD KEY `proyek_id` (`proyek_id`);

--
-- Indexes for table `karyawan`
--
ALTER TABLE `karyawan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kasbon_pekerja`
--
ALTER TABLE `kasbon_pekerja`
  ADD PRIMARY KEY (`id`),
  ADD KEY `karyawan_id` (`karyawan_id`);

--
-- Indexes for table `kunjungan_lapangan`
--
ALTER TABLE `kunjungan_lapangan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kunjungan_tim`
--
ALTER TABLE `kunjungan_tim`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lembur_manual`
--
ALTER TABLE `lembur_manual`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lokasi_proyek`
--
ALTER TABLE `lokasi_proyek`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pengaturan_gaji`
--
ALTER TABLE `pengaturan_gaji`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `profesi` (`profesi`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `absensi`
--
ALTER TABLE `absensi`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `karyawan`
--
ALTER TABLE `karyawan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `kasbon_pekerja`
--
ALTER TABLE `kasbon_pekerja`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `kunjungan_lapangan`
--
ALTER TABLE `kunjungan_lapangan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `kunjungan_tim`
--
ALTER TABLE `kunjungan_tim`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lembur_manual`
--
ALTER TABLE `lembur_manual`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lokasi_proyek`
--
ALTER TABLE `lokasi_proyek`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pengaturan_gaji`
--
ALTER TABLE `pengaturan_gaji`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `absensi`
--
ALTER TABLE `absensi`
  ADD CONSTRAINT `absensi_ibfk_1` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `absensi_ibfk_2` FOREIGN KEY (`proyek_id`) REFERENCES `lokasi_proyek