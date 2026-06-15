<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$db = getDB();
$user = currentUser();
$q = trim($_GET['q'] ?? '');

if ($user['role'] === 'dosen') {
    $sql = "SELECT * FROM jadwal WHERE dosen_id=? AND mk LIKE ? ORDER BY mk LIMIT 10";
    $stmt = $db->prepare($sql);
    $stmt->execute([$user['id'], "%$q%"]);
} else {
    $sql = "SELECT * FROM jadwal WHERE mk LIKE ? ORDER BY mk LIMIT 10";
    $stmt = $db->prepare($sql);
    $stmt->execute(["%$q%"]);
}
$rows = $stmt->fetchAll();

$result = array_map(function($j) {
    return [
        'id' => $j['id'],
        'mk' => $j['mk'],
        'kode' => $j['kode'],
        'hari' => $j['hari'],
        'mulai' => substr($j['mulai'],0,5),
        'selesai' => substr($j['selesai'],0,5),
        'ruang' => $j['ruang'],
        'toleransi' => (int)$j['toleransi'],
        'dosenNama' => $j['dosen_id'],
    ];
}, $rows);

jsonResponse($result);
