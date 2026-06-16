-- ============================================
-- MIGRATION: Tambah kolom prodi ke dosen & jadwal
-- Jalankan ini di phpMyAdmin tab SQL jika sudah
-- pernah import database.sql sebelumnya.
-- Jika fresh install, cukup import database.sql
-- ============================================
USE siabsen;

-- Tambah kolom prodi ke users (untuk dosen)
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS prodi VARCHAR(100) DEFAULT NULL AFTER name;

-- Tambah kolom prodi ke jadwal
ALTER TABLE jadwal
  ADD COLUMN IF NOT EXISTS prodi VARCHAR(100) DEFAULT NULL AFTER ruang;

-- Set prodi dosen sample
UPDATE users SET prodi = 'Teknik Informatika' WHERE id = 'dosen01';
UPDATE users SET prodi = 'Sistem Informasi'   WHERE id = 'dosen02';

-- Set prodi jadwal sample
UPDATE jadwal SET prodi = 'Teknik Informatika' WHERE dosen_id = 'dosen01';
UPDATE jadwal SET prodi = 'Sistem Informasi'   WHERE dosen_id = 'dosen02';

-- Reset & rebuild enrollment sesuai prodi
DELETE FROM enrollment;
INSERT INTO enrollment (nim, jadwal_id)
SELECT m.nim, j.id
FROM mahasiswa m
JOIN jadwal j ON m.prodi = j.prodi
WHERE m.aktif = 1
ON DUPLICATE KEY UPDATE nim = nim;

SELECT 'Migration selesai!' AS status;
