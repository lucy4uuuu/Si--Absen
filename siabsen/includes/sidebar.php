<?php
$NAV = [
    'admin' => [
        ['id'=>'dashboard', 'label'=>'Dashboard', 'ico'=>'📊', 'file'=>'dashboard.php'],
        ['id'=>'absensi',   'label'=>'Absensi',   'ico'=>'✅', 'file'=>'absensi.php'],
        ['id'=>'izin-masuk','label'=>'Pengajuan Izin','ico'=>'📩', 'file'=>'izin_masuk.php'],
        ['id'=>'rekap',     'label'=>'Rekap Kehadiran','ico'=>'📅', 'file'=>'rekap.php'],
        ['id'=>'editabsen', 'label'=>'Edit Kehadiran','ico'=>'✏️', 'file'=>'edit_absen.php'],
        ['id'=>'mahasiswa', 'label'=>'Data Mahasiswa','ico'=>'👥', 'file'=>'mahasiswa.php'],
        ['id'=>'laporan',   'label'=>'Laporan',   'ico'=>'📈', 'file'=>'laporan.php'],
    ],
    'dosen' => [
        ['id'=>'dashboard', 'label'=>'Dashboard', 'ico'=>'📊', 'file'=>'dashboard.php'],
        ['id'=>'absensi',   'label'=>'Absensi',   'ico'=>'✅', 'file'=>'absensi.php'],
        ['id'=>'jadwal',    'label'=>'Jadwal Saya','ico'=>'📆', 'file'=>'jadwal.php'],
        ['id'=>'izin-masuk','label'=>'Pengajuan Izin','ico'=>'📩', 'file'=>'izin_masuk.php'],
        ['id'=>'rekap',     'label'=>'Rekap Kehadiran','ico'=>'📅', 'file'=>'rekap.php'],
        ['id'=>'laporan',   'label'=>'Laporan',   'ico'=>'📈', 'file'=>'laporan.php'],
    ],
    'mahasiswa' => [
        ['id'=>'dashboard', 'label'=>'Dashboard', 'ico'=>'📊', 'file'=>'dashboard.php'],
        ['id'=>'absensi',   'label'=>'Check-in',  'ico'=>'✅', 'file'=>'absensi.php'],
        ['id'=>'izin',      'label'=>'Izin / Alpha','ico'=>'📝', 'file'=>'izin.php'],
        ['id'=>'rekap',     'label'=>'Rekap Saya','ico'=>'📅', 'file'=>'rekap.php'],
        ['id'=>'kalender',  'label'=>'Kalender Kehadiran','ico'=>'🗓️', 'file'=>'kalender.php'],
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
