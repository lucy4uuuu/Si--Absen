-- ============================================
-- SiAbsen Database Schema
-- Import via phpMyAdmin atau: mysql -u root -p < database.sql
-- ============================================
CREATE DATABASE IF NOT EXISTS siabsen CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE siabsen;

-- ============================================
-- USERS
-- ============================================
CREATE TABLE users (
  id VARCHAR(20) PRIMARY KEY,           -- username/nim/nip
  password VARCHAR(255) NOT NULL,       -- plain for demo; gunakan password_hash() di produksi
  role ENUM('admin','dosen','mahasiswa') NOT NULL,
  name VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (id, password, role, name) VALUES
('admin','admin123','admin','Administrator'),
('dosen01','pass','dosen','Dr. Sari Rahayu'),
('dosen02','pass','dosen','Prof. Budi Hartono'),
('2021001','pass','mahasiswa','Andi Firmansyah'),
('2021002','pass','mahasiswa','Citra Lestari'),
('2021003','pass','mahasiswa','Dian Permata');

-- ============================================
-- MAHASISWA (data tambahan)
-- ============================================
CREATE TABLE mahasiswa (
  nim VARCHAR(20) PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  prodi VARCHAR(100) NOT NULL,
  angkatan VARCHAR(10) NOT NULL,
  aktif TINYINT(1) DEFAULT 1,
  FOREIGN KEY (nim) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO mahasiswa (nim, nama, prodi, angkatan, aktif) VALUES
('2021001','Andi Firmansyah','Teknik Informatika','2021',1),
('2021002','Citra Lestari','Sistem Informasi','2021',1),
('2021003','Dian Permata','Ilmu Komputer','2022',1);

-- ============================================
-- LOKASI KAMPUS (untuk validasi GPS absensi)
-- ============================================
CREATE TABLE lokasi_kampus (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  radius_meter INT NOT NULL DEFAULT 200,  -- radius toleransi dalam meter
  aktif TINYINT(1) DEFAULT 1
);

-- Contoh: ganti dengan koordinat kampus Anda yang sebenarnya
INSERT INTO lokasi_kampus (nama, latitude, longitude, radius_meter) VALUES
('Kampus Utama', -4.0135, 119.6255, 300);

-- ============================================
-- JADWAL MATA KULIAH
-- ============================================
CREATE TABLE jadwal (
  id VARCHAR(20) PRIMARY KEY,
  mk VARCHAR(100) NOT NULL,
  kode VARCHAR(20) NOT NULL,
  dosen_id VARCHAR(20) NOT NULL,
  hari ENUM('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu') NOT NULL,
  mulai TIME NOT NULL,
  selesai TIME NOT NULL,
  toleransi INT DEFAULT 15,    -- menit
  ruang VARCHAR(50),
  FOREIGN KEY (dosen_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO jadwal (id, mk, kode, dosen_id, hari, mulai, selesai, toleransi, ruang) VALUES
('J1','Algoritma & Pemrograman','MK101','dosen01','Senin','08:00:00','09:40:00',15,'GD-A 101'),
('J2','Basis Data','MK102','dosen01','Rabu','10:00:00','11:40:00',10,'GD-B 203'),
('J3','Jaringan Komputer','MK103','dosen02','Selasa','13:00:00','14:40:00',15,'Lab NET'),
('J4','Rekayasa Perangkat Lunak','MK104','dosen02','Kamis','08:00:00','09:40:00',20,'GD-C 301');

-- ============================================
-- MAHASISWA <-> MATA KULIAH (enrollment, opsional - jika tidak ada berarti semua mhs ikut semua MK)
-- ============================================
CREATE TABLE enrollment (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nim VARCHAR(20) NOT NULL,
  jadwal_id VARCHAR(20) NOT NULL,
  FOREIGN KEY (nim) REFERENCES mahasiswa(nim) ON DELETE CASCADE,
  FOREIGN KEY (jadwal_id) REFERENCES jadwal(id) ON DELETE CASCADE,
  UNIQUE KEY uq_enroll (nim, jadwal_id)
);

INSERT INTO enrollment (nim, jadwal_id) VALUES
('2021001','J1'),('2021002','J1'),('2021003','J1'),
('2021001','J2'),('2021002','J2'),('2021003','J2'),
('2021001','J3'),('2021002','J3'),('2021003','J3'),
('2021001','J4'),('2021002','J4'),('2021003','J4');

-- ============================================
-- ABSENSI
-- ============================================
CREATE TABLE absensi (
  id VARCHAR(30) PRIMARY KEY,
  type ENUM('mahasiswa','dosen') NOT NULL,
  user_id VARCHAR(20) NOT NULL,
  nama VARCHAR(100) NOT NULL,
  mk VARCHAR(100) NOT NULL,
  jadwal_id VARCHAR(20),
  tanggal DATE NOT NULL,
  checkin TIME NULL,
  checkout TIME NULL,
  checkin_lat DECIMAL(10,7) NULL,
  checkin_lng DECIMAL(10,7) NULL,
  status ENUM('hadir','terlambat','izin-sakit','izin-keluarga','izin-akademik','izin-lain','alpha') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (jadwal_id) REFERENCES jadwal(id) ON DELETE SET NULL,
  INDEX idx_user_date (user_id, tanggal),
  INDEX idx_mk_date (mk, tanggal)
);

-- Sample data (today/yesterday set dynamically not possible in pure SQL easily; using CURDATE())
INSERT INTO absensi (id, type, user_id, nama, mk, jadwal_id, tanggal, checkin, checkout, status) VALUES
('A1','mahasiswa','2021001','Andi Firmansyah','Algoritma & Pemrograman','J1', CURDATE(), '08:05:00','09:40:00','hadir'),
('A2','mahasiswa','2021002','Citra Lestari','Algoritma & Pemrograman','J1', CURDATE(), '08:22:00', NULL,'terlambat'),
('A3','mahasiswa','2021003','Dian Permata','Algoritma & Pemrograman','J1', CURDATE(), NULL, NULL,'alpha'),
('A4','dosen','dosen01','Dr. Sari Rahayu','Algoritma & Pemrograman','J1', CURDATE(), '07:58:00','09:42:00','hadir'),
('A5','mahasiswa','2021001','Andi Firmansyah','Basis Data','J2', DATE_SUB(CURDATE(), INTERVAL 1 DAY), '10:02:00','11:40:00','hadir'),
('A6','dosen','dosen01','Dr. Sari Rahayu','Basis Data','J2', DATE_SUB(CURDATE(), INTERVAL 1 DAY), '10:00:00','11:40:00','hadir');

-- ============================================
-- PENGAJUAN IZIN / ALPHA (dengan upload bukti)
-- ============================================
CREATE TABLE izin (
  id VARCHAR(30) PRIMARY KEY,
  nim VARCHAR(20) NOT NULL,
  nama VARCHAR(100) NOT NULL,
  mk VARCHAR(100) NOT NULL,
  jadwal_id VARCHAR(20),
  tanggal DATE NOT NULL,
  jenis ENUM('izin-sakit','izin-keluarga','izin-akademik','izin-lain','alpha') NOT NULL,
  keterangan TEXT,
  bukti_file VARCHAR(255) NULL,   -- path file upload (surat dokter / foto kegiatan)
  status ENUM('pending','disetujui','ditolak') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (nim) REFERENCES mahasiswa(nim) ON DELETE CASCADE,
  FOREIGN KEY (jadwal_id) REFERENCES jadwal(id) ON DELETE SET NULL
);

INSERT INTO izin (id, nim, nama, mk, jadwal_id, tanggal, jenis, keterangan, status) VALUES
('IZ1','2021003','Dian Permata','Algoritma & Pemrograman','J1', CURDATE(), 'alpha','Tidak bisa hadir','pending');
