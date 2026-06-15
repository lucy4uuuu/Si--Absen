<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$db = getDB();
$user = currentUser();

$data = json_decode(file_get_contents('php://input'), true);
$jadwalId = $data['jadwal_id'] ?? '';

if (!$jadwalId) jsonResponse(['ok'=>false,'message'=>'Pilih mata kuliah!'], 400);

$jadwalStmt = $db->prepare("SELECT * FROM jadwal WHERE id=?");
$jadwalStmt->execute([$jadwalId]);
$jadwal = $jadwalStmt->fetch();
if (!$jadwal) jsonResponse(['ok'=>false,'message'=>'Jadwal tidak ditemukan'], 404);

$type = $user['role'] === 'dosen' ? 'dosen' : 'mahasiswa';
$today = date('Y-m-d');

$stmt = $db->prepare("SELECT * FROM absensi WHERE user_id=? AND mk=? AND tanggal=? AND type=?");
$stmt->execute([$user['id'], $jadwal['mk'], $today, $type]);
$rec = $stmt->fetch();

if (!$rec) jsonResponse(['ok'=>false,'message'=>'Belum check-in!'], 404);
if ($rec['checkout']) jsonResponse(['ok'=>false,'message'=>'Sudah check-out!'], 409);

$nowTime = date('H:i:s');
$upd = $db->prepare("UPDATE absensi SET checkout=? WHERE id=?");
$upd->execute([$nowTime, $rec['id']]);

jsonResponse(['ok'=>true, 'message'=>'Check-out berhasil!']);
