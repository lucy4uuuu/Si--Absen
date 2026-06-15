<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$db = getDB();
$user = currentUser();

$data = json_decode(file_get_contents('php://input'), true);
$jadwalId = $data['jadwal_id'] ?? '';
$lat = $data['lat'] ?? null;
$lng = $data['lng'] ?? null;
$rawId = trim($data['raw_id'] ?? ''); // for admin manual entry
$ciType = $data['ci_type'] ?? 'mahasiswa'; // for admin

if (!$jadwalId) jsonResponse(['ok'=>false,'message'=>'Pilih mata kuliah / jadwal!'], 400);

// ===== GPS validation (required for mahasiswa & dosen, admin manual entry can skip) =====
if ($user['role'] !== 'admin') {
    if ($lat === null || $lng === null) {
        jsonResponse(['ok'=>false,'message'=>'Lokasi GPS tidak terdeteksi. Aktifkan GPS dan izinkan akses lokasi.'], 400);
    }
    $check = isWithinCampus((float)$lat, (float)$lng);
    if (!$check['valid']) {
        $jarakInfo = $check['jarak'] !== null ? "Jarak Anda: {$check['jarak']} m dari {$check['lokasi']} (maks {$check['radius']} m)." : '';
        jsonResponse(['ok'=>false,'message'=>"Absensi ditolak — Anda di luar area kampus. $jarakInfo"], 403);
    }
}

// ===== Determine type/userId/nama =====
$jadwalStmt = $db->prepare("SELECT * FROM jadwal WHERE id=?");
$jadwalStmt->execute([$jadwalId]);
$jadwal = $jadwalStmt->fetch();
if (!$jadwal) jsonResponse(['ok'=>false,'message'=>'Jadwal tidak ditemukan'], 404);

if ($user['role'] === 'mahasiswa') {
    $type = 'mahasiswa'; $userId = $user['id']; $nama = $user['name'];
} elseif ($user['role'] === 'dosen') {
    $type = 'dosen'; $userId = $user['id']; $nama = $user['name'];
} else {
    // admin
    $type = $ciType;
    if (!$rawId) jsonResponse(['ok'=>false,'message'=>'Masukkan NIM/NIP!'], 400);
    if ($type === 'mahasiswa') {
        $stmt = $db->prepare("SELECT * FROM mahasiswa WHERE nim=?");
        $stmt->execute([$rawId]);
        $m = $stmt->fetch();
        if (!$m) jsonResponse(['ok'=>false,'message'=>'NIM tidak ditemukan!'], 404);
        $userId = $rawId; $nama = $m['nama'];
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE id=? AND role='dosen'");
        $stmt->execute([$rawId]);
        $d = $stmt->fetch();
        if (!$d) jsonResponse(['ok'=>false,'message'=>'NIP dosen tidak ditemukan!'], 404);
        $userId = $rawId; $nama = $d['name'];
    }
}

$today = date('Y-m-d');

// Check duplicate
$stmt = $db->prepare("SELECT id FROM absensi WHERE user_id=? AND mk=? AND tanggal=? AND type=?");
$stmt->execute([$userId, $jadwal['mk'], $today, $type]);
if ($stmt->fetch()) {
    jsonResponse(['ok'=>false,'message'=>'Sudah check-in untuk mata kuliah ini hari ini!'], 409);
}

// Determine status (late check)
$nowMin = (int)date('H')*60 + (int)date('i');
$mulaiMin = (int)substr($jadwal['mulai'],0,2)*60 + (int)substr($jadwal['mulai'],3,2);
$batasMin = $mulaiMin + (int)$jadwal['toleransi'];

$status = 'hadir';
if ($nowMin > $batasMin && $type === 'mahasiswa') $status = 'terlambat';

$id = uid('A');
$nowTime = date('H:i:s');
$stmt = $db->prepare("INSERT INTO absensi (id, type, user_id, nama, mk, jadwal_id, tanggal, checkin, checkout, checkin_lat, checkin_lng, status) VALUES (?,?,?,?,?,?,?,?,NULL,?,?,?)");
$stmt->execute([$id, $type, $userId, $nama, $jadwal['mk'], $jadwal['id'], $today, $nowTime, $lat, $lng, $status]);

$msg = $status === 'terlambat' ? "⚠️ Check-in TERLAMBAT: $nama" : "✅ Check-in berhasil: $nama – {$jadwal['mk']}";
jsonResponse(['ok'=>true, 'message'=>$msg, 'status'=>$status, 'is_self'=>$type !== 'dosen' || $user['role']==='dosen' ? ($userId === $user['id']) : false]);
