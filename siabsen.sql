-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 15, 2026 at 08:31 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `siabsen`
--

-- --------------------------------------------------------

--
-- Table structure for table `absensi`
--

CREATE TABLE `absensi` (
  `id` varchar(30) NOT NULL,
  `type` enum('mahasiswa','dosen') NOT NULL,
  `user_id` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `mk` varchar(100) NOT NULL,
  `jadwal_id` varchar(20) DEFAULT NULL,
  `tanggal` date NOT NULL,
  `checkin` time DEFAULT NULL,
  `checkout` time DEFAULT NULL,
  `checkin_lat` decimal(10,7) DEFAULT NULL,
  `checkin_lng` decimal(10,7) DEFAULT NULL,
  `status` enum('hadir','terlambat','izin-sakit','izin-keluarga','izin-akademik','izin-lain','alpha') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `absensi`
--

INSERT INTO `absensi` (`id`, `type`, `user_id`, `nama`, `mk`, `jadwal_id`, `tanggal`, `checkin`, `checkout`, `checkin_lat`, `checkin_lng`, `status`, `created_at`) VALUES
('A1', 'mahasiswa', '2021001', 'Andi Firmansyah', 'Algoritma & Pemrograman', 'J1', '2026-06-15', '08:05:00', '09:40:00', NULL, NULL, 'hadir', '2026-06-15 05:50:38'),
('A1781542896dda5bb', 'mahasiswa', '2021001', 'Andi Firmansyah', 'Jaringan Komputer', 'J3', '2026-06-16', '01:01:36', NULL, -4.0347295, 119.6269197, 'hadir', '2026-06-15 17:01:36'),
('A2', 'mahasiswa', '2021002', 'Citra Lestari', 'Algoritma & Pemrograman', 'J1', '2026-06-15', '08:22:00', NULL, NULL, NULL, 'terlambat', '2026-06-15 05:50:38'),
('A3', 'mahasiswa', '2021003', 'Dian Permata', 'Algoritma & Pemrograman', 'J1', '2026-06-15', NULL, NULL, NULL, NULL, 'alpha', '2026-06-15 05:50:38'),
('A4', 'dosen', 'dosen01', 'Dr. Sari Rahayu', 'Algoritma & Pemrograman', 'J1', '2026-06-15', '07:58:00', '09:42:00', NULL, NULL, 'hadir', '2026-06-15 05:50:38'),
('A5', 'mahasiswa', '2021001', 'Andi Firmansyah', 'Basis Data', 'J2', '2026-06-14', '10:02:00', '11:40:00', NULL, NULL, 'hadir', '2026-06-15 05:50:38'),
('A6', 'dosen', 'dosen01', 'Dr. Sari Rahayu', 'Basis Data', 'J2', '2026-06-14', '10:00:00', '11:40:00', NULL, NULL, 'hadir', '2026-06-15 05:50:38');

-- --------------------------------------------------------

--
-- Table structure for table `enrollment`
--

CREATE TABLE `enrollment` (
  `id` int(11) NOT NULL,
  `nim` varchar(20) NOT NULL,
  `jadwal_id` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `enrollment`
--

INSERT INTO `enrollment` (`id`, `nim`, `jadwal_id`) VALUES
(1, '2021001', 'J1'),
(4, '2021001', 'J2'),
(7, '2021001', 'J3'),
(10, '2021001', 'J4'),
(2, '2021002', 'J1'),
(5, '2021002', 'J2'),
(8, '2021002', 'J3'),
(11, '2021002', 'J4'),
(3, '2021003', 'J1'),
(6, '2021003', 'J2'),
(9, '2021003', 'J3'),
(12, '2021003', 'J4');

-- --------------------------------------------------------

--
-- Table structure for table `izin`
--

CREATE TABLE `izin` (
  `id` varchar(30) NOT NULL,
  `nim` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `mk` varchar(100) NOT NULL,
  `jadwal_id` varchar(20) DEFAULT NULL,
  `tanggal` date NOT NULL,
  `jenis` enum('izin-sakit','izin-keluarga','izin-akademik','izin-lain','alpha') NOT NULL,
  `keterangan` text DEFAULT NULL,
  `bukti_file` varchar(255) DEFAULT NULL,
  `status` enum('pending','disetujui','ditolak') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `izin`
--

INSERT INTO `izin` (`id`, `nim`, `nama`, `mk`, `jadwal_id`, `tanggal`, `jenis`, `keterangan`, `bukti_file`, `status`, `created_at`) VALUES
('IZ1', '2021003', 'Dian Permata', 'Algoritma & Pemrograman', 'J1', '2026-06-15', 'alpha', 'Tidak bisa hadir', NULL, 'disetujui', '2026-06-15 05:50:38'),
('IZ1781543937bfc9bc', '2021001', 'Andi Firmansyah', 'Algoritma & Pemrograman', 'J1', '2026-06-16', 'izin-lain', '', 'uploads/izin/izin_2021001_1781543937.png', 'ditolak', '2026-06-15 17:18:57');

-- --------------------------------------------------------

--
-- Table structure for table `jadwal`
--

CREATE TABLE `jadwal` (
  `id` varchar(20) NOT NULL,
  `mk` varchar(100) NOT NULL,
  `kode` varchar(20) NOT NULL,
  `dosen_id` varchar(20) NOT NULL,
  `hari` enum('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu') NOT NULL,
  `mulai` time NOT NULL,
  `selesai` time NOT NULL,
  `toleransi` int(11) DEFAULT 15,
  `ruang` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jadwal`
--

INSERT INTO `jadwal` (`id`, `mk`, `kode`, `dosen_id`, `hari`, `mulai`, `selesai`, `toleransi`, `ruang`) VALUES
('J1', 'Algoritma & Pemrograman', 'MK101', 'dosen01', 'Senin', '08:00:00', '09:40:00', 15, 'GD-A 101'),
('J2', 'Basis Data', 'MK102', 'dosen01', 'Rabu', '10:00:00', '11:40:00', 10, 'GD-B 203'),
('J3', 'Jaringan Komputer', 'MK103', 'dosen02', 'Selasa', '13:00:00', '14:40:00', 15, 'Lab NET'),
('J4', 'Rekayasa Perangkat Lunak', 'MK104', 'dosen02', 'Kamis', '08:00:00', '09:40:00', 20, 'GD-C 301');

-- --------------------------------------------------------

--
-- Table structure for table `lokasi_kampus`
--

CREATE TABLE `lokasi_kampus` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `radius_meter` int(11) NOT NULL DEFAULT 200,
  `aktif` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lokasi_kampus`
--

INSERT INTO `lokasi_kampus` (`id`, `nama`, `latitude`, `longitude`, `radius_meter`, `aktif`) VALUES
(1, 'Institut Teknologi Bacharuddin Jusuf Habibie - Kampus 2', -4.0346860, 119.6272683, 300, 1);

-- --------------------------------------------------------

--
-- Table structure for table `mahasiswa`
--

CREATE TABLE `mahasiswa` (
  `nim` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `prodi` varchar(100) NOT NULL,
  `angkatan` varchar(10) NOT NULL,
  `aktif` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mahasiswa`
--

INSERT INTO `mahasiswa` (`nim`, `nama`, `prodi`, `angkatan`, `aktif`) VALUES
('2021001', 'Andi Firmansyah', 'Teknik Informatika', '2021', 1),
('2021002', 'Citra Lestari', 'Sistem Informasi', '2021', 1),
('2021003', 'Dian Permata', 'Ilmu Komputer', '2022', 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','dosen','mahasiswa') NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `password`, `role`, `name`, `created_at`) VALUES
('2021001', 'pass', 'mahasiswa', 'Andi Firmansyah', '2026-06-15 05:50:38'),
('2021002', 'pass', 'mahasiswa', 'Citra Lestari', '2026-06-15 05:50:38'),
('2021003', 'pass', 'mahasiswa', 'Dian Permata', '2026-06-15 05:50:38'),
('admin', 'admin123', 'admin', 'Administrator', '2026-06-15 05:50:38'),
('dosen01', 'pass', 'dosen', 'Dr. Sari Rahayu', '2026-06-15 05:50:38'),
('dosen02', 'pass', 'dosen', 'Prof. Budi Hartono', '2026-06-15 05:50:38');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jadwal_id` (`jadwal_id`),
  ADD KEY `idx_user_date` (`user_id`,`tanggal`),
  ADD KEY `idx_mk_date` (`mk`,`tanggal`);

--
-- Indexes for table `enrollment`
--
ALTER TABLE `enrollment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_enroll` (`nim`,`jadwal_id`),
  ADD KEY `jadwal_id` (`jadwal_id`);

--
-- Indexes for table `izin`
--
ALTER TABLE `izin`
  ADD PRIMARY KEY (`id`),
  ADD KEY `nim` (`nim`),
  ADD KEY `jadwal_id` (`jadwal_id`);

--
-- Indexes for table `jadwal`
--
ALTER TABLE `jadwal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dosen_id` (`dosen_id`);

--
-- Indexes for table `lokasi_kampus`
--
ALTER TABLE `lokasi_kampus`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mahasiswa`
--
ALTER TABLE `mahasiswa`
  ADD PRIMARY KEY (`nim`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `enrollment`
--
ALTER TABLE `enrollment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `lokasi_kampus`
--
ALTER TABLE `lokasi_kampus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `absensi`
--
ALTER TABLE `absensi`
  ADD CONSTRAINT `absensi_ibfk_1` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `enrollment`
--
ALTER TABLE `enrollment`
  ADD CONSTRAINT `enrollment_ibfk_1` FOREIGN KEY (`nim`) REFERENCES `mahasiswa` (`nim`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollment_ibfk_2` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `izin`
--
ALTER TABLE `izin`
  ADD CONSTRAINT `izin_ibfk_1` FOREIGN KEY (`nim`) REFERENCES `mahasiswa` (`nim`) ON DELETE CASCADE,
  ADD CONSTRAINT `izin_ibfk_2` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `jadwal`
--
ALTER TABLE `jadwal`
  ADD CONSTRAINT `jadwal_ibfk_1` FOREIGN KEY (`dosen_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mahasiswa`
--
ALTER TABLE `mahasiswa`
  ADD CONSTRAINT `mahasiswa_ibfk_1` FOREIGN KEY (`nim`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
