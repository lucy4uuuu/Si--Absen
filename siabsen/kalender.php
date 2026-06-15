<?php
require_once __DIR__ . '/includes/functions.php';
requireRole('mahasiswa');
$db = getDB();
$user = currentUser();
$pageTitle = 'Kalender Kehadiran';

$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$firstDay = sprintf('%04d-%02d-01', $year, $month);
$daysInMonth = (int)date('t', strtotime($firstDay));
$startWeekday = (int)date('N', strtotime($firstDay)); // 1=Mon..7=Sun

$stmt = $db->prepare("SELECT tanggal, status FROM absensi WHERE type='mahasiswa' AND user_id=? AND tanggal BETWEEN ? AND ?");
$lastDay = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
$stmt->execute([$user['id'], $firstDay, $lastDay]);
$records = [];
foreach ($stmt->fetchAll() as $r) {
    $key = $r['tanggal'];
    $records[$key] = $r['status'];
}

$bulanNama = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$today = date('Y-m-d');

include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <h3>Kalender Kehadiran</h3>
    <div class="cal-nav">
      <a href="?month=<?= $month-1 ?>&year=<?= $year ?>"><button>‹</button></a>
      <div class="cal-month-label"><?= $bulanNama[$month] ?> <?= $year ?></div>
      <a href="?month=<?= $month+1 ?>&year=<?= $year ?>"><button>›</button></a>
    </div>
  </div>
  <div style="padding:18px">
    <div class="cal-wrap">
      <?php foreach (['Sen','Sel','Rab','Kam','Jum','Sab','Min'] as $d): ?>
        <div class="cal-day-name"><?= $d ?></div>
      <?php endforeach; ?>

      <?php
      // Empty cells before day 1
      $emptyBefore = $startWeekday - 1;
      for ($i = 0; $i < $emptyBefore; $i++) echo '<div class="cal-cell empty-cell"></div>';

      for ($d = 1; $d <= $daysInMonth; $d++):
          $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
          $status = $records[$dateStr] ?? null;
          $cls = 'cal-cell';
          $icon = '';
          if ($status === 'hadir') { $cls .= ' hadir'; $icon = '✅'; }
          elseif ($status === 'terlambat') { $cls .= ' terlambat'; $icon = '⏰'; }
          elseif (strpos($status ?? '', 'izin') === 0) { $cls .= ' izin'; $icon = '📝'; }
          elseif ($status === 'alpha') { $cls .= ' alpha'; $icon = '❌'; }
          elseif ($dateStr > $today) { $cls .= ' future'; }
      ?>
        <div class="<?= $cls ?>" title="<?= $status ? jenisLabel($status) : '' ?>">
          <div><?= $d ?></div>
          <?php if ($icon): ?><div class="cal-dot"><?= $icon ?></div><?php endif; ?>
        </div>
      <?php endfor; ?>
    </div>

    <div class="cal-legend">
      <div class="cal-legend-item"><span class="cal-legend-dot" style="background:var(--success-light);border:1px solid #BBF7D0"></span>Hadir</div>
      <div class="cal-legend-item"><span class="cal-legend-dot" style="background:var(--purple-light);border:1px solid #DDD6FE"></span>Terlambat</div>
      <div class="cal-legend-item"><span class="cal-legend-dot" style="background:var(--warning-light);border:1px solid #FDE68A"></span>Izin</div>
      <div class="cal-legend-item"><span class="cal-legend-dot" style="background:var(--danger-light);border:1px solid #FECACA"></span>Alpha</div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
