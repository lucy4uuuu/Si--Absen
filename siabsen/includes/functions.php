<?php
require_once __DIR__ . '/../config/database.php';

session_start();

function requireLogin() {
    if (!isset($_SESSION['user'])) {
        header('Location: /siabsen/index.php');
        exit;
    }
}

function currentUser() {
    return $_SESSION['user'] ?? null;
}

function requireRole($roles) {
    requireLogin();
    $roles = is_array($roles) ? $roles : [$roles];
    if (!in_array($_SESSION['user']['role'], $roles)) {
        http_response_code(403);
        die('Akses ditolak.');
    }
}

function uid($prefix = 'ID') {
    return $prefix . time() . substr(md5(mt_rand()), 0, 6);
}

function jenisLabel($j) {
    $map = [
        'izin-sakit' => 'Izin Sakit',
        'izin-keluarga' => 'Izin Keluarga',
        'izin-akademik' => 'Izin Akademik',
        'izin-lain' => 'Izin Lainnya',
        'alpha' => 'Alpha'
    ];
    return $map[$j] ?? $j;
}

function statusBadge($s) {
    $map = [
        'hadir' => ['bg-green', 'Hadir'],
        'terlambat' => ['bg-purple', 'Terlambat'],
        'alpha' => ['bg-red', 'Alpha'],
    ];
    if (strpos($s, 'izin') === 0) {
        return '<span class="badge bg-yellow">' . jenisLabel($s) . '</span>';
    }
    $v = $map[$s] ?? ['bg-gray', $s];
    return '<span class="badge ' . $v[0] . '">' . $v[1] . '</span>';
}

function izinStatusBadge($s) {
    $map = [
        'pending' => ['bg-yellow', 'Pending'],
        'disetujui' => ['bg-green', 'Disetujui'],
        'ditolak' => ['bg-red', 'Ditolak'],
    ];
    $v = $map[$s] ?? ['bg-gray', $s];
    return '<span class="badge ' . $v[0] . '">' . $v[1] . '</span>';
}

// Hitung jarak antara dua koordinat (meter) - Haversine formula
function distanceMeters($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // meter
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earthRadius * $c;
}

// Cek apakah koordinat berada dalam radius kampus
function isWithinCampus($lat, $lng) {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM lokasi_kampus WHERE aktif = 1");
    $locations = $stmt->fetchAll();
    foreach ($locations as $loc) {
        $dist = distanceMeters($lat, $lng, $loc['latitude'], $loc['longitude']);
        if ($dist <= $loc['radius_meter']) {
            return ['valid' => true, 'jarak' => round($dist), 'lokasi' => $loc['nama']];
        }
    }
    // return nearest info for messaging
    $nearest = null; $nearestDist = null;
    foreach ($locations as $loc) {
        $dist = distanceMeters($lat, $lng, $loc['latitude'], $loc['longitude']);
        if ($nearestDist === null || $dist < $nearestDist) { $nearestDist = $dist; $nearest = $loc; }
    }
    return ['valid' => false, 'jarak' => $nearest ? round($nearestDist) : null, 'lokasi' => $nearest['nama'] ?? null, 'radius' => $nearest['radius_meter'] ?? null];
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
