<?php
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$db = getDB();
$user = currentUser();
$role = $user['role'];
$pageTitle = $role === 'mahasiswa' ? 'Rekap Saya' : 'Rekap Kehadiran';

// MK options for filter dropdown
if ($role === 'dosen') {
    $mkStmt = $db->prepare("SELECT DISTINCT mk FROM jadwal WHERE dosen_id=? ORDER BY mk");
    $mkStmt->execute([$user['id']]);
} else {
    $mkStmt = $db->query("SELECT DISTINCT mk FROM jadwal ORDER BY mk");
}
$mkList = array_column($mkStmt->fetchAll(), 'mk');

$filterMk = $_GET['mk'] ?? '';
$filterType = $_GET['type'] ?? 'mahasiswa';
if ($role === 'mahasiswa') $filterType = 'mahasiswa';

// Build query
$sql = "SELECT * FROM absensi WHERE type = ?";
$params = [$filterType];
if ($filterMk) { $sql .= " AND mk = ?"; $params[] = $filterMk; }
if ($role === 'mahasiswa') { $sql .= " AND user_id = ?"; $params[] = $user['id']; }
if ($role === 'dosen') {
    if ($mkList) {
        $in = implode(',', array_fill(0, count($mkList), '?'));
        $sql .= " AND mk IN ($in)";
        $params = array_merge($params, $mkList);
        if ($filterType === 'dosen') { $sql .= " AND user_id = ?"; $params[] = $user['id']; }
    } else {
        $sql .= " AND 1=0";
    }
}
$stmt = $db->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

// Aggregate per user+mk
$map = [];
foreach ($data as $a) {
    $key = $a['user_id'] . '|' . $a['mk'];
    if (!isset($map[$key])) $map[$key] = ['userId'=>$a['user_id'],'nama'=>$a['nama'],'mk'=>$a['mk'],'hadir'=>0,'terlambat'=>0,'izin'=>0,'alpha'=>0];
    if ($a['status']==='hadir') $map[$key]['hadir']++;
    elseif ($a['status']==='terlambat') $map[$key]['terlambat']++;
    elseif (strpos($a['status'],'izin')===0) $map[$key]['izin']++;
    elseif ($a['status']==='alpha') $map[$key]['alpha']++;
}
$rows = array_values($map);

// Overall totals for percentage detail & charts
$totalHadir = array_sum(array_column($rows,'hadir'));
$totalTerlambat = array_sum(array_column($rows,'terlambat'));
$totalIzin = array_sum(array_column($rows,'izin'));
$totalAlpha = array_sum(array_column($rows,'alpha'));
$totalAll = $totalHadir + $totalTerlambat + $totalIzin + $totalAlpha;
$pctHadir = $totalAll ? round(($totalHadir+$totalTerlambat)/$totalAll*100) : 0;
$pctTerlambat = $totalAll ? round($totalTerlambat/$totalAll*100) : 0;
$pctIzin = $totalAll ? round($totalIzin/$totalAll*100) : 0;
$pctAlpha = $totalAll ? round($totalAlpha/$totalAll*100) : 0;

// Trend per bulan (mahasiswa: untuk diri sendiri; lainnya: agregat)
$trendSql = "SELECT DATE_FORMAT(tanggal,'%Y-%m') as bulan,
                SUM(status='hadir') as hadir, SUM(status='terlambat') as terlambat,
                SUM(status LIKE 'izin%') as izin, SUM(status='alpha') as alpha
              FROM absensi WHERE type = ?";
$trendParams = [$filterType];
if ($role === 'mahasiswa') { $trendSql .= " AND user_id = ?"; $trendParams[] = $user['id']; }
if ($role === 'dosen' && $mkList) {
    $in = implode(',', array_fill(0, count($mkList), '?'));
    $trendSql .= " AND mk IN ($in)";
    $trendParams = array_merge($trendParams, $mkList);
}
$trendSql .= " GROUP BY bulan ORDER BY bulan ASC LIMIT 12";
$trendStmt = $db->prepare($trendSql);
$trendStmt->execute($trendParams);
$trend = $trendStmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- DETAIL PERSENTASE -->
<div class="pct-grid">
  <div class="pct-circle-card">
    <div class="pct-val" style="color:var(--success)"><?= $pctHadir ?>%</div>
    <div class="pct-lbl">Tingkat Kehadiran</div>
    <div class="prog"><div class="prog-fill" style="width:<?= $pctHadir ?>%;background:var(--success)"></div></div>
  </div>
  <div class="pct-circle-card">
    <div class="pct-val" style="color:var(--purple)"><?= $pctTerlambat ?>%</div>
    <div class="pct-lbl">Terlambat (<?= $totalTerlambat ?>)</div>
    <div class="prog"><div class="prog-fill" style="width:<?= $pctTerlambat ?>%;background:var(--purple)"></div></div>
  </div>
  <div class="pct-circle-card">
    <div class="pct-val" style="color:var(--warning)"><?= $pctIzin ?>%</div>
    <div class="pct-lbl">Izin (<?= $totalIzin ?>)</div>
    <div class="prog"><div class="prog-fill" style="width:<?= $pctIzin ?>%;background:var(--warning)"></div></div>
  </div>
  <div class="pct-circle-card">
    <div class="pct-val" style="color:var(--danger)"><?= $pctAlpha ?>%</div>
    <div class="pct-lbl">Alpha (<?= $totalAlpha ?>)</div>
    <div class="prog"><div class="prog-fill" style="width:<?= $pctAlpha ?>%;background:var(--danger)"></div></div>
  </div>
</div>

<!-- CHARTS -->
<div class="chart-wrap">
  <div class="chart-card">
    <h4>Distribusi Status Kehadiran</h4>
    <canvas id="chart-pie" height="220"></canvas>
  </div>
  <div class="chart-card">
    <h4>Tren Kehadiran per Bulan</h4>
    <canvas id="chart-line" height="220"></canvas>
  </div>
</div>

<!-- TABLE -->
<div class="card">
  <div class="card-header">
    <h3>Rekap Kehadiran</h3>
    <form method="GET" class="card-actions">
      <?php if ($role !== 'mahasiswa'): ?>
      <select class="sinput" name="type" onchange="this.form.submit()" style="width:130px">
        <option value="mahasiswa" <?= $filterType==='mahasiswa'?'selected':'' ?>>Mahasiswa</option>
        <option value="dosen" <?= $filterType==='dosen'?'selected':'' ?>>Dosen</option>
      </select>
      <?php endif; ?>
      <select class="sinput" name="mk" onchange="this.form.submit()" style="width:220px">
        <option value="">Semua MK</option>
        <?php foreach ($mkList as $mk): ?>
          <option value="<?= htmlspecialchars($mk) ?>" <?= $filterMk===$mk?'selected':'' ?>><?= htmlspecialchars($mk) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <table>
    <thead><tr><th>ID</th><th>Nama</th><th>Mata Kuliah</th><th>Hadir</th><th>Terlambat</th><th>Izin</th><th>Alpha</th><th>% Hadir</th></tr></thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8"><div class="empty"><div class="eico">📭</div>Tidak ada data</div></td></tr>
      <?php else: foreach ($rows as $r):
        $tot = $r['hadir']+$r['terlambat']+$r['izin']+$r['alpha'];
        $pct = $tot ? round(($r['hadir']+$r['terlambat'])/$tot*100) : 0;
        $col = $pct>=75 ? 'var(--success)' : ($pct>=50 ? 'var(--warning)' : 'var(--danger)');
      ?>
        <tr>
          <td><span style="font-size:12px;color:var(--gray-400)"><?= htmlspecialchars($r['userId']) ?></span></td>
          <td><?= htmlspecialchars($r['nama']) ?></td>
          <td><?= htmlspecialchars($r['mk']) ?></td>
          <td><span class="badge bg-green"><?= $r['hadir'] ?></span></td>
          <td><span class="badge bg-purple"><?= $r['terlambat'] ?></span></td>
          <td><span class="badge bg-yellow"><?= $r['izin'] ?></span></td>
          <td><span class="badge bg-red"><?= $r['alpha'] ?></span></td>
          <td>
            <span style="font-weight:800;color:<?= $col ?>"><?= $pct ?>%</span>
            <div class="prog"><div class="prog-fill" style="width:<?= $pct ?>%;background:<?= $col ?>"></div></div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<script>
const pieData = {
  labels: ['Hadir','Terlambat','Izin','Alpha'],
  datasets: [{
    data: [<?= $totalHadir ?>, <?= $totalTerlambat ?>, <?= $totalIzin ?>, <?= $totalAlpha ?>],
    backgroundColor: ['#16A34A','#7C3AED','#D97706','#DC2626'],
    borderWidth: 0
  }]
};
new Chart(document.getElementById('chart-pie'), {
  type: 'doughnut',
  data: pieData,
  options: { plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
});

const trendLabels = <?= json_encode(array_map(function($t){
  $parts = explode('-', $t['bulan']);
  $bulanNama = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
  return $bulanNama[(int)$parts[1]] . ' ' . substr($parts[0],2);
}, $trend)) ?>;
const trendHadir = <?= json_encode(array_map(fn($t)=>(int)$t['hadir']+(int)$t['terlambat'], $trend)) ?>;
const trendAlpha = <?= json_encode(array_map(fn($t)=>(int)$t['alpha'], $trend)) ?>;
const trendIzin = <?= json_encode(array_map(fn($t)=>(int)$t['izin'], $trend)) ?>;

new Chart(document.getElementById('chart-line'), {
  type: 'line',
  data: {
    labels: trendLabels.length ? trendLabels : ['Belum ada data'],
    datasets: [
      { label: 'Hadir/Terlambat', data: trendHadir, borderColor: '#16A34A', backgroundColor: 'rgba(22,163,74,.1)', tension: .3, fill: true },
      { label: 'Izin', data: trendIzin, borderColor: '#D97706', backgroundColor: 'rgba(217,119,6,.1)', tension: .3, fill: true },
      { label: 'Alpha', data: trendAlpha, borderColor: '#DC2626', backgroundColor: 'rgba(220,38,38,.1)', tension: .3, fill: true },
    ]
  },
  options: { plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
