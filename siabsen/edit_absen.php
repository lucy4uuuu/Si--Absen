<?php
require_once __DIR__ . '/includes/functions.php';
requireRole('admin');
$db = getDB();
$pageTitle = 'Edit Kehadiran';
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $ci = $_POST['ea_ci'] ?: null;
    $co = $_POST['ea_co'] ?: null;
    $status = $_POST['ea_status'] ?? 'hadir';
    $stmt = $db->prepare("UPDATE absensi SET checkin=?, checkout=?, status=? WHERE id=?");
    $stmt->execute([$ci, $co, $status, $id]);
    header('Location: edit_absen.php?' . $_SERVER['QUERY_STRING']);
    exit;
}

$mk = $_GET['mk'] ?? '';
$type = $_GET['type'] ?? 'mahasiswa';
$date = $_GET['date'] ?? $today;

$mkList = array_column($db->query("SELECT DISTINCT mk FROM jadwal ORDER BY mk")->fetchAll(), 'mk');

$sql = "SELECT * FROM absensi WHERE type=?";
$params = [$type];
if ($mk) { $sql .= " AND mk=?"; $params[] = $mk; }
if ($date) { $sql .= " AND tanggal=?"; $params[] = $date; }
$sql .= " ORDER BY nama";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <h3>Edit Kehadiran</h3>
    <form method="GET" class="card-actions">
      <select class="sinput" name="mk" onchange="this.form.submit()" style="width:220px">
        <option value="">Semua Mata Kuliah</option>
        <?php foreach ($mkList as $m): ?>
          <option value="<?= htmlspecialchars($m) ?>" <?= $mk===$m?'selected':'' ?>><?= htmlspecialchars($m) ?></option>
        <?php endforeach; ?>
      </select>
      <select class="sinput" name="type" onchange="this.form.submit()" style="width:130px">
        <option value="mahasiswa" <?= $type==='mahasiswa'?'selected':'' ?>>Mahasiswa</option>
        <option value="dosen" <?= $type==='dosen'?'selected':'' ?>>Dosen</option>
      </select>
      <input class="sinput" type="date" name="date" value="<?= htmlspecialchars($date) ?>" onchange="this.form.submit()">
    </form>
  </div>
  <table>
    <thead><tr><th>Nama</th><th>ID</th><th>Mata Kuliah</th><th>Tanggal</th><th>Check-in</th><th>Check-out</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
      <?php if (!$list): ?>
        <tr><td colspan="8"><div class="empty"><div class="eico">📭</div>Tidak ada data</div></td></tr>
      <?php else: foreach ($list as $a): ?>
        <tr>
          <td><?= htmlspecialchars($a['nama']) ?></td>
          <td><span style="font-size:12px;color:var(--gray-400)"><?= htmlspecialchars($a['user_id']) ?></span></td>
          <td><?= htmlspecialchars($a['mk']) ?></td>
          <td><?= $a['tanggal'] ?></td>
          <td><?= $a['checkin'] ? substr($a['checkin'],0,5) : '–' ?></td>
          <td><?= $a['checkout'] ? substr($a['checkout'],0,5) : '–' ?></td>
          <td><?= statusBadge($a['status']) ?></td>
          <td><button class="btn-sm bw" onclick='openEdit(<?= json_encode($a) ?>)'>✏️ Edit</button></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<!-- MODAL: Edit -->
<div class="modal-overlay" id="modal-edit-absen">
  <div class="modal">
    <h3>Edit Kehadiran</h3>
    <form method="POST">
      <input type="hidden" name="id" id="ea-id">
      <div class="field"><label>Nama</label><input id="ea-nama" type="text" disabled style="background:var(--gray-50)"></div>
      <div class="field"><label>Check-in</label><input name="ea_ci" id="ea-ci" type="time"></div>
      <div class="field"><label>Check-out</label><input name="ea_co" id="ea-co" type="time"></div>
      <div class="field"><label>Status</label>
        <select name="ea_status" id="ea-status">
          <option value="hadir">Hadir</option>
          <option value="terlambat">Terlambat</option>
          <option value="izin-sakit">Izin – Sakit</option>
          <option value="izin-keluarga">Izin – Keluarga</option>
          <option value="izin-akademik">Izin – Akademik</option>
          <option value="izin-lain">Izin – Lainnya</option>
          <option value="alpha">Alpha</option>
        </select>
      </div>
      <div class="modal-footer">
        <button class="btn-sm bo" type="button" onclick="closeModal('modal-edit-absen')">Batal</button>
        <button class="btn-sm bp" type="submit">Simpan</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEdit(a) {
  document.getElementById('ea-id').value = a.id;
  document.getElementById('ea-nama').value = a.nama + ' (' + a.mk + ')';
  document.getElementById('ea-ci').value = a.checkin ? a.checkin.slice(0,5) : '';
  document.getElementById('ea-co').value = a.checkout ? a.checkout.slice(0,5) : '';
  document.getElementById('ea-status').value = a.status;
  openModal('modal-edit-absen');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
