<?php
require_once __DIR__ . '/includes/functions.php';
requireRole('mahasiswa');
$db   = getDB();
$user = currentUser();
$pageTitle = 'Detail Kehadiran Saya';

// Ambil semua jadwal yang diikuti mahasiswa ini (sesuai prodi)
$stmt = $db->prepare("
    SELECT j.* , u.name AS dosen_nama
    FROM jadwal j
    JOIN enrollment e ON e.jadwal_id = j.id
    JOIN users u ON u.id = j.dosen_id
    WHERE e.nim = ?
    ORDER BY j.mk
");
$stmt->execute([$user['id']]);
$jadwalList = $stmt->fetchAll();

// Ambil semua absensi mahasiswa ini
$absStmt = $db->prepare("
    SELECT * FROM absensi
    WHERE type = 'mahasiswa' AND user_id = ?
    ORDER BY tanggal ASC
");
$absStmt->execute([$user['id']]);
$allAbs = $absStmt->fetchAll();

// Kelompokkan absensi per MK
$absByMk = [];
foreach ($allAbs as $a) {
    $absByMk[$a['mk']][] = $a;
}

// Hitung total statistik keseluruhan
$grandHadir = 0; $grandTel = 0; $grandIzin = 0; $grandAlpha = 0;
foreach ($allAbs as $a) {
    if ($a['status'] === 'hadir')           $grandHadir++;
    elseif ($a['status'] === 'terlambat')   $grandTel++;
    elseif (strpos($a['status'],'izin')===0) $grandIzin++;
    elseif ($a['status'] === 'alpha')        $grandAlpha++;
}
$grandTotal = $grandHadir + $grandTel + $grandIzin + $grandAlpha;
$grandPct   = $grandTotal ? round(($grandHadir+$grandTel)/$grandTotal*100) : 0;

include __DIR__ . '/includes/header.php';
?>

<!-- RINGKASAN KESELURUHAN -->
<div class="pct-grid" style="margin-bottom:20px">
  <div class="pct-circle-card">
    <div class="pct-val" style="color:var(--success)"><?= $grandPct ?>%</div>
    <div class="pct-lbl">Kehadiran Total</div>
    <div class="prog"><div class="prog-fill"
      style="width:<?= $grandPct ?>%;background:<?= $grandPct>=75?'var(--success)':($grandPct>=50?'var(--warning)':'var(--danger)') ?>">
    </div></div>
  </div>
  <div class="pct-circle-card">
    <div class="pct-val" style="color:var(--success)"><?= $grandHadir + $grandTel ?></div>
    <div class="pct-lbl">Total Hadir (termasuk terlambat)</div>
  </div>
  <div class="pct-circle-card">
    <div class="pct-val" style="color:var(--warning)"><?= $grandIzin ?></div>
    <div class="pct-lbl">Total Izin</div>
  </div>
  <div class="pct-circle-card">
    <div class="pct-val" style="color:var(--danger)"><?= $grandAlpha ?></div>
    <div class="pct-lbl">Total Alpha</div>
  </div>
</div>

<!-- PERINGATAN jika kehadiran rendah -->
<?php if ($grandTotal > 0 && $grandPct < 75): ?>
<div style="background:var(--danger-light);border:1px solid #FECACA;border-radius:9px;
            padding:12px 16px;margin-bottom:20px;font-size:13px;color:var(--danger)">
  ⚠️ <b>Perhatian:</b> Kehadiran Anda secara keseluruhan hanya <b><?= $grandPct ?>%</b>.
  Minimal kehadiran yang disyaratkan biasanya <b>75%</b>. Segera perbaiki kehadiran Anda!
</div>
<?php endif; ?>

<!-- DETAIL PER MATA KULIAH -->
<?php if (!$jadwalList): ?>
  <div class="empty"><div class="eico">📭</div>Anda belum terdaftar di mata kuliah mana pun.</div>
<?php else: foreach ($jadwalList as $j):
    $absMk = $absByMk[$j['mk']] ?? [];
    $hadir  = count(array_filter($absMk, fn($a)=>$a['status']==='hadir'));
    $tel    = count(array_filter($absMk, fn($a)=>$a['status']==='terlambat'));
    $izin   = count(array_filter($absMk, fn($a)=>strpos($a['status'],'izin')===0));
    $alpha  = count(array_filter($absMk, fn($a)=>$a['status']==='alpha'));
    $total  = $hadir + $tel + $izin + $alpha;
    $pct    = $total ? round(($hadir+$tel)/$total*100) : 0;
    $col    = $pct>=75 ? 'var(--success)' : ($pct>=50 ? 'var(--warning)' : 'var(--danger)');
    $mkId   = 'mk-' . md5($j['mk']);
?>
<div class="card" style="margin-bottom:16px">
  <!-- Header MK -->
  <div class="card-header" style="cursor:pointer" onclick="toggleMk('<?= $mkId ?>')">
    <div>
      <div style="font-size:14px;font-weight:700"><?= htmlspecialchars($j['mk']) ?></div>
      <div style="font-size:12px;color:var(--gray-500);margin-top:3px">
        <?= $j['hari'] ?>, <?= substr($j['mulai'],0,5) ?>–<?= substr($j['selesai'],0,5) ?> ·
        👨‍🏫 <?= htmlspecialchars($j['dosen_nama']) ?> ·
        🏫 <?= htmlspecialchars($j['ruang'] ?: '-') ?>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:16px">
      <!-- Mini stat badges -->
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <span class="badge bg-green">✅ <?= $hadir ?> hadir</span>
        <?php if ($tel): ?>
          <span class="badge bg-purple">⏰ <?= $tel ?> terlambat</span>
        <?php endif; ?>
        <?php if ($izin): ?>
          <span class="badge bg-yellow">📝 <?= $izin ?> izin</span>
        <?php endif; ?>
        <?php if ($alpha): ?>
          <span class="badge bg-red">❌ <?= $alpha ?> alpha</span>
        <?php endif; ?>
      </div>
      <!-- Persentase -->
      <div style="text-align:right;min-width:70px">
        <div style="font-size:20px;font-weight:800;color:<?= $col ?>"><?= $pct ?>%</div>
        <div class="prog" style="width:70px">
          <div class="prog-fill" style="width:<?= $pct ?>%;background:<?= $col ?>"></div>
        </div>
      </div>
      <div style="color:var(--gray-400);font-size:18px" id="arr-<?= $mkId ?>">▼</div>
    </div>
  </div>

  <!-- Tabel pertemuan (collapsible) -->
  <div id="<?= $mkId ?>" style="display:none">
    <?php if (!$absMk): ?>
      <div class="empty" style="padding:20px">
        <div class="eico">📭</div>Belum ada catatan absensi untuk mata kuliah ini.
      </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Tanggal</th>
          <th>Check-in</th>
          <th>Check-out</th>
          <th>Status</th>
          <th>Keterangan</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($absMk as $i => $a): ?>
          <tr>
            <td style="color:var(--gray-400)"><?= $i+1 ?></td>
            <td><?= date('d M Y', strtotime($a['tanggal'])) ?></td>
            <td><?= $a['checkin']  ? substr($a['checkin'],0,5)  : '–' ?></td>
            <td><?= $a['checkout'] ? substr($a['checkout'],0,5) : '–' ?></td>
            <td><?= statusBadge($a['status']) ?></td>
            <td style="font-size:12px;color:var(--gray-500)">
              <?php
              if ($a['status'] === 'terlambat') echo 'Melewati batas toleransi';
              elseif (strpos($a['status'],'izin')===0) {
                // Cek apakah ada bukti di tabel izin
                $izinRow = $db->prepare("SELECT bukti_file,keterangan FROM izin WHERE nim=? AND mk=? AND tanggal=?");
                $izinRow->execute([$user['id'], $a['mk'], $a['tanggal']]);
                $izinRow = $izinRow->fetch();
                if ($izinRow) {
                  echo htmlspecialchars($izinRow['keterangan'] ?: '–');
                  if ($izinRow['bukti_file'])
                    echo ' <a class="bukti-link" href="'
                         . htmlspecialchars($izinRow['bukti_file'])
                         . '" target="_blank">📎 Bukti</a>';
                }
              }
              ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

    <!-- Mini progress bar bawah -->
    <?php if ($total > 0): ?>
    <div style="padding:12px 16px;background:var(--gray-50);border-top:1px solid var(--gray-100);
                display:flex;gap:6px;align-items:center;font-size:12px">
      <span style="color:var(--gray-500)">Distribusi <?= $total ?> pertemuan:</span>
      <?php
        $bars = [
          [$hadir,  'var(--success)', 'Hadir'],
          [$tel,    'var(--purple)',  'Terlambat'],
          [$izin,   'var(--warning)', 'Izin'],
          [$alpha,  'var(--danger)',  'Alpha'],
        ];
        foreach ($bars as [$n,$c,$lbl]):
          if ($n <= 0) continue;
          $w = round($n/$total*100);
      ?>
        <div title="<?= $lbl ?>: <?= $n ?>"
             style="flex:<?= $w ?>;height:8px;border-radius:4px;background:<?= $c ?>;
                    min-width:<?= min($w,4) ?>px" ></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; endif; ?>

<script>
function toggleMk(id) {
  const el  = document.getElementById(id);
  const arr = document.getElementById('arr-' + id);
  const open = el.style.display === 'block';
  el.style.display  = open ? 'none'  : 'block';
  arr.textContent   = open ? '▼' : '▲';
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
