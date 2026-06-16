<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$db   = getDB();
$user = currentUser();

$data     = json_decode(file_get_contents('php://input'), true);
$jadwalId = $data['jadwal_id'] ?? '';
$lat      = $data['lat'] ?? null;
$lng      = $data['lng'] ?? null;
$rawId    = trim($data['raw_id']  ?? '');
$ciType   = $data['ci_type'] ?? 'mahasiswa';

if (!$jadwalId) jsonResponse(['ok'=>false,'message'=>'Pilih mata kuliah!'], 400);

// GPS validation (wajib untuk mahasiswa & dosen, kecuali admin)
if ($user['role'] !== 'admin') {
    if ($lat === null || $lng === null)
        jsonResponse(['ok'=>false,'message'=>'Lokasi GPS tidak terdeteksi. Aktifkan GPS dan izinkan akses lokasi.'], 400);
    $check = isWithinCampus((float)$lat, (float)$lng);
    if (!$check['valid']) {
        $info = $check['jarak'] !== null
            ? "Jarak Anda: {$check['jarak']} m dari {$check['lokasi']} (maks {$check['radius']} m)."
            : '';
        jsonResponse(['ok'=>false,'message'=>"Absensi ditolak — Anda di luar area kampus. $info"], 403);
    }
}

// Ambil jadwal
$jadwalStmt = $db->prepare("SELECT * FROM jadwal WHERE id=?");
$jadwalStmt->execute([$jadwalId]);
$jadwal = $jadwalStmt->fetch();
if (!$jadwal) jsonResponse(['ok'=>false,'message'=>'Jadwal tidak ditemukan'], 404);

// Tentukan userId, nama, type
if ($user['role'] === 'mahasiswa') {
    $type = 'mahasiswa'; $userId = $user['id']; $nama = $user['name'];

    // Validasi: mahasiswa harus terdaftar di jadwal ini (enrollment + prodi)
    $enroll = $db->prepare("SELECT id FROM enrollment WHERE nim=? AND jadwal_id=?");
    $enroll->execute([$userId, $jadwalId]);
    if (!$enroll->fetch())
        jsonResponse(['ok'=>false,'message'=>'Anda tidak terdaftar di mata kuliah ini.'], 403);

} elseif ($user['role'] === 'dosen') {
    $type = 'dosen'; $userId = $user['id']; $nama = $user['name'];

    // Validasi: dosen hanya bisa absen di kelasnya sendiri
    if ($jadwal['dosen_id'] !== $userId)
        jsonResponse(['ok'=>false,'message'=>'Anda bukan pengampu mata kuliah ini.'], 403);

} else {
    // Admin — input manual
    $type = $ciType;
    if (!$rawId) jsonResponse(['ok'=>false,'message'=>'Masukkan NIM/NIP!'], 400);
    if ($type === 'mahasiswa') {
        $m = $db->prepare("SELECT * FROM mahasiswa WHERE nim=?");
        $m->execute([$rawId]);
        $m = $m->fetch();
        if (!$m) jsonResponse(['ok'=>false,'message'=>'NIM tidak ditemukan!'], 404);

        // Cek enrollment
        $enroll = $db->prepare("SELECT id FROM enrollment WHERE nim=? AND jadwal_id=?");
        $enroll->execute([$rawId, $jadwalId]);
        if (!$enroll->fetch()) {
            // Admin boleh override — auto-enroll dulu
            $db->prepare("INSERT IGNORE INTO enrollment (nim, jadwal_id) VALUES (?,?)")
               ->execute([$rawId, $jadwalId]);
        }
        $userId = $rawId; $nama = $m['nama'];
    } else {
        $d = $db->prepare("SELECT * FROM users WHERE id=? AND role='dosen'");
        $d->execute([$rawId]);
        $d = $d->fetch();
        if (!$d) jsonResponse(['ok'=>false,'message'=>'NIP dosen tidak ditemukan!'], 404);
        $userId = $rawId; $nama = $d['name'];
    }
}

$today = date('Y-m-d');

// Cek duplikat
$dup = $db->prepare("SELECT id FROM absensi WHERE user_id=? AND mk=? AND tanggal=? AND type=?");
$dup->execute([$userId, $jadwal['mk'], $today, $type]);
if ($dup->fetch())
    jsonResponse(['ok'=>false,'message'=>'Sudah check-in untuk mata kuliah ini hari ini!'], 409);

// Tentukan status (hadir / terlambat)
$nowMin   = (int)date('H')*60 + (int)date('i');
$mulaiMin = (int)substr($jadwal['mulai'],0,2)*60 + (int)substr($jadwal['mulai'],3,2);
$batasMin = $mulaiMin + (int)$jadwal['toleransi'];
$status   = ($nowMin > $batasMin && $type === 'mahasiswa') ? 'terlambat' : 'hadir';

$id      = uid('A');
$nowTime = date('H:i:s');
$db->prepare("
    INSERT INTO absensi
      (id, type, user_id, nama, mk, jadwal_id, tanggal, checkin, checkout, checkin_lat, checkin_lng, status)
    VALUES (?,?,?,?,?,?,?,?,NULL,?,?,?)
")->execute([$id, $type, $userId, $nama, $jadwal['mk'], $jadwal['id'], $today, $nowTime, $lat, $lng, $status]);

$msg = $status === 'terlambat'
    ? "⚠️ Check-in TERLAMBAT: $nama"
    : "✅ Check-in berhasil: $nama – {$jadwal['mk']}";

jsonResponse(['ok'=>true, 'message'=>$msg, 'status'=>$status]);
