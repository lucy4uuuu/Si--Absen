<?php
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$db = getDB();
$user = currentUser();
$role = $user['role'];
$pageTitle = 'Dashboard';
$today = date('Y-m-d');

include __DIR__ . '/includes/header.php';
?>

<?php if ($role === 'mahasiswa'): ?>
  <?php
    $mine = $db->prepare("SELECT * FROM absensi WHERE type='mahasiswa' AND user_id=?");
    $mine->execute([$user['id']]);
    $mine = $mine->fetchAll();
    $hadir = count(array_filter($mine, fn($a)=>in_array($a['status'],['hadir','terlambat'])));
    $tel   = count(array_filter($mine, fn($a)=>$a['status']==='terlambat'));
    $izin  = count(array_filter($mine, fn($a)=>strpos($a['status'],'izin')===0));

    $myToday = $db->prepare("SELECT * FROM absensi WHERE type='mahasiswa' AND user_id=? AND tanggal=?");
    $myToday->execute([$user['id'], $today]);
    $myToday = $myToday->fetchAll();
  ?>
  <div class="stats-grid">
    <div class="stat-card c-blue"><div class="lbl">Total Pertemuan</div><div class="val"><?= count($mine) ?></div></div>
    <div class="stat-card c-green"><div class="lbl">Hadir</div><div class="val"><?= $hadir ?></div></div>
    <div class="stat-card c-purple"><div class="lbl">Terlambat</div><div class="val"><?= $tel ?></div></div>
    <div class="stat-card c-yellow"><div class="lbl">Izin</div><div class="val"><?= $izin ?></div></div>
  </div>
  <div class="card">
    <div class="card-header"><h3>Absensi Saya Hari Ini</h3></div>
    <table>
      <thead><tr><th>Mata Kuliah</th><th>Check-in</th><th>Check-out</th><th>Status</th></tr></thead>
      <tbody>
        <?php if (!$myToday): ?>
          <tr><td colspan="4"><div class="empty"><div class="eico">📭</div>Belum ada absensi hari ini</div></td></tr>
        <?php else: foreach ($myToday as $a): ?>
          <tr>
            <td><?= htmlspecialchars($a['mk']) ?></td>
            <td><?= $a['checkin'] ? substr($a['checkin'],0,5) : '–' ?></td>
            <td><?= $a['checkout'] ? substr($a['checkout'],0,5) : '–' ?></td>
            <td><?= statusBadge($a['status']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

<?php elseif ($role === 'dosen'): ?>
  <?php
    $myMksStmt = $db->prepare("SELECT mk FROM jadwal WHERE dosen_id=?");
    $myMksStmt->execute([$user['id']]);
    $myMks = array_column($myMksStmt->fetchAll(), 'mk');
    $myMksCount = count($myMks);

    $myAbsen = [];
    if ($myMks) {
        $in = implode(',', array_fill(0, count($myMks), '?'));
        $stmt = $db->prepare("SELECT * FROM absensi WHERE mk IN ($in) AND tanggal=?");
        $stmt->execute([...$myMks, $today]);
        $myAbsen = $stmt->fetchAll();
    }
    $hadirMhs = count(array_filter($myAbsen, fn($a)=>$a['type']==='mahasiswa' && in_array($a['status'],['hadir','terlambat'])));
    $alphaMhs = count(array_filter($myAbsen, fn($a)=>$a['type']==='mahasiswa' && $a['status']==='alpha'));
    $myDosenAbsen = array_filter($myAbsen, fn($a)=>$a['type']==='dosen' && $a['user_id']===$user['id']);
  ?>
  <div class="stats-grid">
    <div class="stat-card c-blue"><div class="lbl">MK Diampu</div><div class="val"><?= $myMksCount ?></div></div>
    <div class="stat-card c-green"><div class="lbl">Mhs Hadir</div><div class="val"><?= $hadirMhs ?></div></div>
    <div class="stat-card c-red"><div class="lbl">Mhs Alpha</div><div class="val"><?= $alphaMhs ?></div></div>
    <div class="stat-card c-purple"><div class="lbl">Absensi Saya</div><div class="val"><?= count($myDosenAbsen)>0?'✓':'–' ?></div></div>
  </div>
  <div class="card">
    <div class="card-header"><h3>Absensi Kelas Saya Hari Ini</h3></div>
    <table>
      <thead><tr><th>Nama</th><th>Tipe</th><th>Mata Kuliah</th><th>Check-in</th><th>Status</th></tr></thead>
      <tbody>
        <?php if (!$myAbsen): ?>
          <tr><td colspan="5"><div class="empty"><div class="eico">📭</div>Belum ada absensi di kelas Anda hari ini</div></td></tr>
        <?php else: foreach ($myAbsen as $a): ?>
          <tr>
            <td><?= htmlspecialchars($a['nama']) ?></td>
            <td><span class="badge <?= $a['type']==='dosen'?'bg-purple':'bg-blue' ?>"><?= $a['type'] ?></span></td>
            <td><?= htmlspecialchars($a['mk']) ?></td>
            <td><?= $a['checkin'] ? substr($a['checkin'],0,5) : '–' ?></td>
            <td><?= statusBadge($a['status']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

<?php else: // admin ?>
  <?php
    $totalMhs = $db->query("SELECT COUNT(*) FROM mahasiswa WHERE aktif=1")->fetchColumn();
    $todayList = $db->prepare("SELECT * FROM absensi WHERE tanggal=?");
    $todayList->execute([$today]);
    $todayList = $todayList->fetchAll();
    $mhsToday = array_filter($todayList, fn($a)=>$a['type']==='mahasiswa');
    $dosToday = array_filter($todayList, fn($a)=>$a['type']==='dosen');
    $hadirMhs = count(array_filter($mhsToday, fn($a)=>in_array($a['status'],['hadir','terlambat'])));
    $hadirDos = count(array_filter($dosToday, fn($a)=>$a['status']==='hadir'));
    $pending = $db->query("SELECT COUNT(*) FROM izin WHERE status='pending'")->fetchColumn();
  ?>
  <div class="stats-grid">
    <div class="stat-card c-blue"><div class="lbl">Total Mahasiswa</div><div class="val"><?= $totalMhs ?></div></div>
    <div class="stat-card c-green"><div class="lbl">Mhs Hadir</div><div class="val"><?= $hadirMhs ?></div></div>
    <div class="stat-card c-purple"><div class="lbl">Dosen Hadir</div><div class="val"><?= $hadirDos ?></div></div>
    <div class="stat-card c-yellow"><div class="lbl">Izin Pending</div><div class="val"><?= $pending ?></div></div>
  </div>
  <div class="card">
    <div class="card-header"><h3>Absensi Hari Ini (Semua)</h3></div>
    <table>
      <thead><tr><th>Nama</th><th>Tipe</th><th>Mata Kuliah</th><th>Check-in</th><th>Status</th></tr></thead>
      <tbody>
        <?php if (!$todayList): ?>
          <tr><td colspan="5"><div class="empty"><div class="eico">📭</div>Belum ada absensi hari ini</div></td></tr>
        <?php else: foreach ($todayList as $a): ?>
          <tr>
            <td><?= htmlspecialchars($a['nama']) ?></td>
            <td><span class="badge <?= $a['type']==='dosen'?'bg-purple':'bg-blue' ?>"><?= $a['type'] ?></span></td>
            <td><?= htmlspecialchars($a['mk']) ?></td>
            <td><?= $a['checkin'] ? substr($a['checkin'],0,5) : '–' ?></td>
            <td><?= statusBadge($a['status']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
