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
        'izin-sakit'    => 'Izin Sakit',
        'izin-keluarga' => 'Izin Keluarga',
        'izin-akademik' => 'Izin Akademik',
        'izin-lain'     => 'Izin Lainnya',
        'alpha'         => 'Alpha',
        'hadir'         => 'Hadir',
        'terlambat'     => 'Terlambat',
    ];
    return $map[$j] ?? $j;
}

function statusBadge($s) {
    $map = [
        'hadir'     => ['bg-green',  'Hadir'],
        'terlambat' => ['bg-purple', 'Terlambat'],
        'alpha'     => ['bg-red',    'Alpha'],
    ];
    if (strpos($s, 'izin') === 0)
        return '<span class="badge bg-yellow">' . jenisLabel($s) . '</span>';
    $v = $map[$s] ?? ['bg-gray', $s];
    return '<span class="badge ' . $v[0] . '">' . $v[1] . '</span>';
}

function izinStatusBadge($s) {
    $map = [
        'pending'   => ['bg-yellow', 'Pending'],
        'disetujui' => ['bg-green',  'Disetujui'],
        'ditolak'   => ['bg-red',    'Ditolak'],
    ];
    $v = $map[$s] ?? ['bg-gray', $s];
    return '<span class="badge ' . $v[0] . '">' . $v[1] . '</span>';
}

// ══════════════════════════════════════════
// GPS — Haversine formula
// ══════════════════════════════════════════
function distanceMeters($lat1, $lon1, $lat2, $lon2) {
    $R   = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a    = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2)**2;
    return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
}

function isWithinCampus($lat, $lng) {
    $db   = getDB();
    $locs = $db->query("SELECT * FROM lokasi_kampus WHERE aktif = 1")->fetchAll();
    $nearest = null; $nearestDist = null;
    foreach ($locs as $loc) {
        $dist = distanceMeters($lat, $lng, $loc['latitude'], $loc['longitude']);
        if ($nearestDist === null || $dist < $nearestDist) {
            $nearestDist = $dist; $nearest = $loc;
        }
        if ($dist <= $loc['radius_meter'])
            return ['valid'=>true, 'jarak'=>round($dist), 'lokasi'=>$loc['nama']];
    }
    return [
        'valid'  => false,
        'jarak'  => $nearest ? round($nearestDist) : null,
        'lokasi' => $nearest['nama'] ?? null,
        'radius' => $nearest['radius_meter'] ?? null,
    ];
}

// ══════════════════════════════════════════
// Enrollment: daftarkan mahasiswa ke semua jadwal sesuai prodinya
// Dipanggil saat mahasiswa baru dibuat ATAU jadwal baru dibuat
// ══════════════════════════════════════════
function autoEnrollByProdi($db, $nim = null, $jadwalId = null) {
    $ins = $db->prepare("INSERT IGNORE INTO enrollment (nim, jadwal_id) VALUES (?,?)");

    if ($nim && !$jadwalId) {
        // Mahasiswa baru: enroll ke semua jadwal sesuai prodinya
        $prodiStmt = $db->prepare("SELECT prodi FROM mahasiswa WHERE nim=?");
        $prodiStmt->execute([$nim]);
        $prodi = $prodiStmt->fetchColumn();

        $jadwals = $db->prepare("SELECT id FROM jadwal WHERE prodi=?");
        $jadwals->execute([$prodi]);
        foreach ($jadwals->fetchAll() as $j) $ins->execute([$nim, $j['id']]);

    } elseif ($jadwalId && !$nim) {
        // Jadwal baru: enroll semua mahasiswa aktif sesuai prodi jadwal
        $prodiStmt = $db->prepare("SELECT prodi FROM jadwal WHERE id=?");
        $prodiStmt->execute([$jadwalId]);
        $prodi = $prodiStmt->fetchColumn();

        $mhsList = $db->prepare("SELECT nim FROM mahasiswa WHERE prodi=? AND aktif=1");
        $mhsList->execute([$prodi]);
        foreach ($mhsList->fetchAll() as $m) $ins->execute([$m['nim'], $jadwalId]);
    }
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
