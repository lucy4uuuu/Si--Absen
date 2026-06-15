# SiAbsen — Sistem Absensi (PHP + MySQL untuk XAMPP)

## Fitur Baru pada Versi Ini
- 🔍 **Search mata kuliah autocomplete** (untuk admin saat input absensi manual)
- 📅 **Jadwal hari ini otomatis** — mahasiswa/dosen tinggal klik kelas yang tersedia hari itu, tidak perlu pilih sendiri dari semua MK
- ⏳ **Countdown kelas dimulai/terlambat** — real-time, berubah warna sesuai status (akan dimulai, tepat waktu, terlambat, selesai)
- 📊 **Grafik riwayat kehadiran** (pie chart distribusi status + line chart tren bulanan) menggunakan Chart.js
- 🗓️ **Kalender kehadiran bulanan** untuk mahasiswa, dengan kode warna per status
- 📈 **Detail persentase kehadiran** (hadir, terlambat, izin, alpha) dalam bentuk kartu progress
- 🔔 **Reminder otomatis** 10 menit sebelum kelas dimulai (toast + banner)
- 📍 **Validasi GPS/lokasi kampus** — absensi mahasiswa/dosen ditolak otomatis jika berada di luar radius kampus yang ditentukan
- 📎 **Upload bukti izin** (surat dokter saat sakit, foto kegiatan, dll) — mendukung JPG/PNG/PDF maks 5MB

---

## 1. Instalasi

1. Pastikan **XAMPP** sudah terinstal dan **Apache** + **MySQL** berjalan.
2. Salin seluruh folder `siabsen` ke dalam folder `htdocs` XAMPP, contoh:
   - Windows: `C:\xampp\htdocs\siabsen`
   - Linux/Mac: `/opt/lampp/htdocs/siabsen`
3. Buka **phpMyAdmin** (`http://localhost/phpmyadmin`).
4. Buat database baru atau langsung import file `database.sql` (database `siabsen` akan otomatis dibuat oleh skrip ini):
   - Klik tab **Import** → pilih file `database.sql` → klik **Go**.
5. Buka browser ke `http://localhost/siabsen/`.

---

## 2. Konfigurasi Database

Jika kredensial MySQL Anda berbeda dari default XAMPP (`root` tanpa password), edit file:

```
config/database.php
```

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'siabsen');
define('DB_USER', 'root');
define('DB_PASS', '');
```

---

## 3. Konfigurasi Lokasi Kampus (GPS)

Fitur absensi berbasis lokasi memvalidasi koordinat GPS pengguna terhadap titik kampus yang tersimpan di tabel `lokasi_kampus`.

**Cara mengatur koordinat kampus Anda:**

1. Buka Google Maps, klik kanan pada lokasi kampus Anda → catat koordinat **latitude, longitude** yang muncul.
2. Buka phpMyAdmin → tabel `lokasi_kampus` → edit baris yang ada (atau tambah baris baru):

```sql
UPDATE lokasi_kampus 
SET latitude = -4.0135, longitude = 119.6255, radius_meter = 300, nama='Kampus Utama'
WHERE id = 1;
```

- `radius_meter`: radius toleransi dalam meter dari titik pusat kampus. Mahasiswa/dosen di luar radius ini akan ditolak saat check-in.
- Bisa menambahkan beberapa lokasi (misalnya beberapa gedung/kampus) — sistem akan menerima jika pengguna berada di dalam radius **salah satu** lokasi yang aktif (`aktif = 1`).

**Catatan penting:**
- Browser akan meminta izin akses lokasi (GPS). Pengguna **harus mengizinkan** akses lokasi, atau tombol check-in tidak akan aktif.
- GPS browser umumnya bekerja lebih akurat di HP dibanding laptop/PC tanpa GPS hardware (PC biasanya estimasi via IP/WiFi, kurang presisi).
- Saat testing di `localhost`, sebagian browser membatasi akses geolocation hanya pada koneksi **HTTPS** atau `localhost` — `http://localhost/siabsen` umumnya tetap diizinkan oleh Chrome/Firefox.
- Admin (input manual via NIM/NIP) **tidak** dikenai validasi GPS, karena dianggap melakukan pencatatan manual/koreksi.

---

## 4. Akun Default

| Role      | Username  | Password   |
|-----------|-----------|------------|
| Admin     | admin     | admin123   |
| Dosen 1   | dosen01   | pass       |
| Dosen 2   | dosen02   | pass       |
| Mahasiswa | 2021001   | pass       |
| Mahasiswa | 2021002   | pass       |
| Mahasiswa | 2021003   | pass       |

> ⚠️ Password disimpan plain-text demi kesederhanaan demo. Untuk produksi, gunakan `password_hash()` / `password_verify()`.

---

## 5. Struktur Folder

```
siabsen/
├── index.php              # Halaman login
├── logout.php
├── dashboard.php
├── absensi.php             # Check-in/out + countdown + GPS + autocomplete
├── izin.php                # Pengajuan izin/alpha + upload bukti (mahasiswa)
├── izin_masuk.php          # Approval izin (admin/dosen)
├── jadwal.php              # Atur jadwal MK (dosen)
├── rekap.php               # Rekap + grafik + persentase
├── kalender.php            # Kalender kehadiran (mahasiswa)
├── mahasiswa.php            # Data mahasiswa (admin)
├── edit_absen.php           # Edit kehadiran (admin)
├── laporan.php              # Laporan per MK
├── database.sql             # Skema + data awal
├── config/database.php
├── includes/
│   ├── functions.php        # Helper, session, GPS distance, dll.
│   ├── header.php / footer.php / sidebar.php
├── api/
│   ├── search_mk.php         # Autocomplete pencarian MK
│   ├── jadwal_hari_ini.php    # Jadwal MK hari ini untuk user login
│   ├── check_location.php     # Validasi GPS vs lokasi kampus
│   ├── checkin.php             # Proses check-in (+validasi GPS & waktu)
│   └── checkout.php
├── assets/css/style.css
├── assets/js/common.js
└── uploads/izin/             # File bukti izin (surat dokter, foto, dll)
```

---

## 6. Catatan Pengembangan Lanjutan

- **Enrollment**: tabel `enrollment` menentukan mata kuliah mana yang muncul di "Jadwal Hari Ini" mahasiswa. Tambahkan baris baru di tabel ini saat menambahkan mahasiswa ke kelas tertentu.
- **Reminder kelas**: saat ini reminder ditampilkan di halaman Absensi (10 menit sebelum kelas via JS interval). Untuk notifikasi push/WhatsApp/email, perlu integrasi tambahan (cron job + API pihak ketiga).
- **Keamanan**: tambahkan CSRF token pada form-form POST dan validasi input lebih lanjut untuk produksi.
