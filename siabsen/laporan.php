<?php
require_once __DIR__ . '/includes/functions.php';
requireRole(['admin','dosen']);
$db = getDB();
$user = currentUser();
$role = $user['role'];
$pageTitle = 'Laporan';

if ($role === 'dosen') {
    $stmt = $db->prepare("SELECT * FROM jadwal WHERE dosen_id=? ORDER BY mk");
    $stmt->execute([$user['id']]);
} else {
    $stmt = $db->query("SELECT * FROM jadwal ORDER BY mk");
}
$myJadwal = $stmt->fetchAll();
$mks = array_unique(array_column($myJadwal, 'mk'));

$mhsData = []; $dosData = [];
if ($mks) {
    $in = implode(',', array_fill(0, count($mks), '?'));
    $stmt = $db->prepare("SELECT * FROM absensi WHERE mk IN ($in)");
    $stmt->execute(array_values($mks));
    $allData = $stmt->fetchAll();
    $mhsData = array_filter($allData, fn($a)=>$a['type']==='mahasiswa');
    $dosData = array_filter($allData, fn($a)=>$a['type']==='dosen');
}
$hadir = count(array_filter($mhsData, fn($a)=>in_array($a['status'],['hadir','terlambat'])));
$total = count($mhsData);
$pct = $total ? round($hadir/$total*100) : 0;
$hadirDosen = count(array_filter($dosData, fn($a)=>$a['status']==='hadir'));

// Get dosen names for table
$dosenNames = [];
foreach ($myJadwal as $j) { $dosenNames[$j['mk']] = $j['dosen_id']; }
if ($dosenNames) {
    $stmt = $db->query("SELECT id, name FROM users WHERE role='dosen'");
    $dosenMap = [];
    foreach ($stmt->fetchAll() as $u) $dosenMap[$u['id']] = $u['name'];
}

include __DIR__ . '/includes/header.php';
?>

<div class="stats-grid">
  <div class="stat-card c-blue"><div class="lbl">Total MK</div><div class="val"><?= count($mks) ?></div></div>
  <div class="stat-card c-green"><div class="lbl">Total Hadir Mhs</div><div class="val"><?= $hadir ?></div></div>
  <div class="stat-card c-purple"><div class="lbl">Total Hadir Dosen</div><div class="val"><?= $hadirDosen ?></div></div>
  <div class="stat-card <?= $pct>=75?'c-green':'c-red' ?>"><div class="lbl">Rata-rata Kehadiran</div><div class="val"><?= $pct ?>%</div></div>
</div>

<div class="card">
  <div class="card-header"><h3>Laporan per Mata Kuliah</h3>
    <button class="btn-sm bo" onclick="exportLaporanCSV()">⬇ Export CSV</button>
  </div>
  <table>
    <thead><tr><th>Mata Kuliah</th><th>Dosen</th><th>Pertemuan</th><th>Rata-rata Hadir</th><th>Tingkat Kehadiran</th></tr></thead>
    <tbody>
      <?php if (!$mks): ?>
        <tr><td colspan="5"><div class="empty"><div class="eico">📭</div>Tidak ada data</div></td></tr>
      <?php else: foreach ($mks as $mk):
        $mkData = array_filter($mhsData, fn($a)=>$a['mk']===$mk);
        $mkHadir = count(array_filter($mkData, fn($a)=>in_array($a['status'],['hadir','terlambat'])));
        $mkTotal = count($mkData);
        $p = $mkTotal ? round($mkHadir/$mkTotal*100) : 0;
        $col = $p>=75 ? 'var(--success)' : ($p>=50 ? 'var(--warning)' : 'var(--danger)');
        $dosenId = $dosenNames[$mk] ?? '';
        $dosenName = $dosenMap[$dosenId] ?? '–';
      ?>
        <tr>
          <td><b><?= htmlspecialchars($mk) ?></b></td>
          <td><?= htmlspecialchars($dosenName) ?></td>
          <td><?= $mkTotal ?></td>
          <td><?= $mkHadir ?></td>
          <td>
            <span style="font-weight:800;color:<?= $col ?>"><?= $p ?>%</span>
            <div class="prog"><div class="prog-fill" style="width:<?= $p ?>%;background:<?= $col ?>"></div></div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<script>
const laporanRows = [
  ['Mata Kuliah','Dosen','Pertemuan','Rata-rata Hadir','Tingkat Kehadiran (%)'],
  <?php foreach ($mks as $mk):
    $mkData = array_filter($mhsData, fn($a)=>$a['mk']===$mk);
    $mkHadir = count(array_filter($mkData, fn($a)=>in_array($a['status'],['hadir','terlambat'])));
    $mkTotal = count($mkData);
    $p = $mkTotal ? round($mkHadir/$mkTotal*100) : 0;
    $dosenId = $dosenNames[$mk] ?? '';
    $dosenName = $dosenMap[$dosenId] ?? '–';
  ?>
  [<?= json_encode($mk) ?>, <?= json_encode($dosenName) ?>, <?= $mkTotal ?>, <?= $mkHadir ?>, <?= $p ?>],
  <?php endforeach; ?>
];
function exportLaporanCSV() { exportCSV(laporanRows, 'laporan-absensi.csv'); }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
