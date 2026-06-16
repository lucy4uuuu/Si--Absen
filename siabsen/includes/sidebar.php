<?php
$NAV = [
    'admin' => [
        ['label'=>'Dashboard',        'ico'=>'📊', 'file'=>'dashboard.php'],
        ['label'=>'Absensi',           'ico'=>'✅', 'file'=>'absensi.php'],
        ['label'=>'Pengajuan Izin',    'ico'=>'📩', 'file'=>'izin_masuk.php'],
        ['label'=>'Rekap Kehadiran',   'ico'=>'📅', 'file'=>'rekap.php'],
        ['label'=>'Edit Kehadiran',    'ico'=>'✏️', 'file'=>'edit_absen.php'],
        ['label'=>'Data Mahasiswa',    'ico'=>'👥', 'file'=>'mahasiswa.php'],
        ['label'=>'Kelola Jadwal',     'ico'=>'📆', 'file'=>'jadwal.php'],
        ['label'=>'Laporan',           'ico'=>'📈', 'file'=>'laporan.php'],
        ['label'=>'Ganti Sandi',       'ico'=>'🔒', 'file'=>'ganti_sandi.php'],
    ],
    'dosen' => [
        ['label'=>'Dashboard',         'ico'=>'📊', 'file'=>'dashboard.php'],
        ['label'=>'Absensi',            'ico'=>'✅', 'file'=>'absensi.php'],
        ['label'=>'Jadwal Saya',        'ico'=>'📆', 'file'=>'jadwal.php'],
        ['label'=>'Pengajuan Izin',     'ico'=>'📩', 'file'=>'izin_masuk.php'],
        ['label'=>'Rekap Kehadiran',    'ico'=>'📅', 'file'=>'rekap.php'],
        ['label'=>'Laporan',            'ico'=>'📈', 'file'=>'laporan.php'],
        ['label'=>'Ganti Sandi',        'ico'=>'🔒', 'file'=>'ganti_sandi.php'],
    ],
    'mahasiswa' => [
        ['label'=>'Dashboard',          'ico'=>'📊', 'file'=>'dashboard.php'],
        ['label'=>'Check-in',            'ico'=>'✅', 'file'=>'absensi.php'],
        ['label'=>'Izin / Alpha',        'ico'=>'📝', 'file'=>'izin.php'],
        ['label'=>'Detail Kehadiran',    'ico'=>'📋', 'file'=>'rekap_detail.php'],
        ['label'=>'Rekap & Grafik',      'ico'=>'📅', 'file'=>'rekap.php'],
        ['label'=>'Kalender Kehadiran',  'ico'=>'🗓️', 'file'=>'kalender.php'],
        ['label'=>'Ganti Sandi',         'ico'=>'🔒', 'file'=>'ganti_sandi.php'],
    ],
];

$user = currentUser();
$role = $user['role'];
$currentFile = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
  <div class="sidebar-logo"><div class="icon">📋</div><span>SiAbsen</span></div>
  <nav class="nav">
    <?php foreach ($NAV[$role] as $item): ?>
      <a href="<?= $item['file'] ?>">
        <div class="nav-item <?= $currentFile === $item['file'] ? 'active' : '' ?>">
          <span class="ico"><?= $item['ico'] ?></span><span><?= $item['label'] ?></span>
        </div>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-user">
    <div class="avatar <?= $role ?>"><?= htmlspecialchars(mb_substr($user['name'],0,1)) ?></div>
    <div class="user-info">
      <div class="uname"><?= htmlspecialchars($user['name']) ?></div>
      <div class="urole"><?= ucfirst($role) ?></div>
    </div>
    <a href="logout.php" class="logout-btn" title="Keluar">⏻</a>
  </div>
</aside>
