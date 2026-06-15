<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$db = getDB();
$user = currentUser();

// Mapping nama hari Indonesia
$hariMap = [0=>'Minggu',1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu'];
$hariIni = $hariMap[date('w')];
$today = date('Y-m-d');

if ($user['role'] === 'dosen') {
    $stmt = $db->prepare("SELECT * FROM jadwal WHERE dosen_id=? AND hari=? ORDER BY mulai");
    $stmt->execute([$user['id'], $hariIni]);
} elseif ($user['role'] === 'mahasiswa') {
    $stmt = $db->prepare("
        SELECT j.* FROM jadwal j
        INNER JOIN enrollment e ON e.jadwal_id = j.id
        WHERE e.nim = ? AND j.hari = ?
        ORDER BY j.mulai
    ");
    $stmt->execute([$user['id'], $hariIni]);
} else {
    $stmt = $db->prepare("SELECT * FROM jadwal WHERE hari=? ORDER BY mulai");
    $stmt->execute([$hariIni]);
}
$rows = $stmt->fetchAll();

// Cek absensi yang sudah dilakukan hari ini untuk user ini
$type = $user['role'] === 'dosen' ? 'dosen' : 'mahasiswa';
$absStmt = $db->prepare("SELECT mk, status, checkin, checkout FROM absensi WHERE user_id=? AND type=? AND tanggal=?");
$absStmt->execute([$user['id'], $type, $today]);
$absMap = [];
foreach ($absStmt->fetchAll() as $a) { $absMap[$a['mk']] = $a; }

$nowMin = (int)date('H')*60 + (int)date('i');

$result = array_map(function($j) use ($absMap, $nowMin) {
    $mulaiMin = (int)substr($j['mulai'],0,2)*60 + (int)substr($j['mulai'],3,2);
    $selesaiMin = (int)substr($j['selesai'],0,2)*60 + (int)substr($j['selesai'],3,2);
    $batasMin = $mulaiMin + (int)$j['toleransi'];

    // status countdown: upcoming, ontime, late, closed
    $cdStatus = 'upcoming';
    if ($nowMin >= $mulaiMin && $nowMin <= $batasMin) $cdStatus = 'ontime';
    elseif ($nowMin > $batasMin && $nowMin <= $selesaiMin) $cdStatus = 'late';
    elseif ($nowMin > $selesaiMin) $cdStatus = 'closed';

    return [
        'id' => $j['id'],
        'mk' => $j['mk'],
        'kode' => $j['kode'],
        'hari' => $j['hari'],
        'mulai' => substr($j['mulai'],0,5),
        'selesai' => substr($j['selesai'],0,5),
        'ruang' => $j['ruang'],
        'toleransi' => (int)$j['toleransi'],
        'mulaiMin' => $mulaiMin,
        'selesaiMin' => $selesaiMin,
        'batasMin' => $batasMin,
        'cdStatus' => $cdStatus,
        'sudahAbsen' => isset($absMap[$j['mk']]),
        'absen' => $absMap[$j['mk']] ?? null,
    ];
}, $rows);

jsonResponse(['hari' => $hariIni, 'jadwal' => $result, 'nowMin' => $nowMin]);
