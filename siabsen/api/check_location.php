<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$data = json_decode(file_get_contents('php://input'), true);
$lat = $data['lat'] ?? null;
$lng = $data['lng'] ?? null;

if ($lat === null || $lng === null) {
    jsonResponse(['valid' => false, 'message' => 'Koordinat tidak diterima'], 400);
}

$check = isWithinCampus((float)$lat, (float)$lng);

if ($check['valid']) {
    jsonResponse([
        'valid' => true,
        'message' => "Lokasi terverifikasi: {$check['lokasi']} (jarak {$check['jarak']} m)",
        'jarak' => $check['jarak'],
        'lokasi' => $check['lokasi'],
    ]);
} else {
    $jarakInfo = $check['jarak'] !== null ? "Anda berada {$check['jarak']} m dari {$check['lokasi']} (radius diizinkan {$check['radius']} m)." : "Lokasi kampus belum dikonfigurasi.";
    jsonResponse([
        'valid' => false,
        'message' => "Anda berada di luar area kampus. $jarakInfo",
        'jarak' => $check['jarak'],
        'lokasi' => $check['lokasi'],
    ]);
}
