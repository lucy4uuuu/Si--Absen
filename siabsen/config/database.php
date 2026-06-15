<?php
// ============================================
// Konfigurasi Database — sesuaikan dengan XAMPP Anda
// ============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'siabsen');
define('DB_USER', 'root');
define('DB_PASS', '');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER, DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            die("Koneksi database gagal: " . $e->getMessage() . "<br>Pastikan database 'siabsen' sudah diimport via phpMyAdmin.");
        }
    }
    return $pdo;
}

// Zona waktu
date_default_timezone_set('Asia/Makassar');
