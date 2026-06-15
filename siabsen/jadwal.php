<?php
require_once __DIR__ . '/includes/functions.php';
requireRole('dosen');
$db = getDB();
$user = currentUser();
$pageTitle = 'Jadwal Mata Kuliah';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $editId = $_POST['edit_id'] ?? '';
        $mk = trim($_POST['jd_mk'] ?? '');
        $kode = trim($_POST['jd_kode'] ?? '');
        $hari = $_POST['jd_hari'] ?? 'Senin';
        $mulai = $_POST['jd_mulai'] ?? '08:00';
        $selesai = $_POST['jd_selesai'] ?? '09:40';
        $toleransi = (int)($_POST['jd_toleransi'] ?? 15);
        $ruang = trim($_POST['jd_ruang'] ?? '');

        if ($mk && $kode) {
            if ($editId) {
                $stmt = $db->prepare("UPDATE jadwal SET mk=?,kode=?,hari=?,mulai=?,selesai=?,toleransi=?,ruang=? WHERE id=? AND dosen_id=?");
                $stmt->execute([$mk,$kode,$hari,$mulai,$selesai,$toleransi,$ruang,$editId,$user['id']]);
            } else {
                $id = uid('J');
                $stmt = $db->prepare("INSERT INTO jadwal (id,mk,kode,dosen_id,hari,mulai,selesai,toleransi,ruang) VALUES (?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$id,$mk,$kode,$user['id'],$hari,$mulai,$selesai,$toleransi,$ruang]);
            }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        $db->prepare("DELETE FROM jadwal WHERE id=? AND dosen_id=?")->execute([$id, $user['id']]);
    }
    header('Location: jadwal.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM jadwal WHERE dosen_id=? ORDER BY hari, mulai");
$stmt->execute([$user['id']]);
$myJadwal = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="card-header" style="background:var(--white);border-radius:10px;border:1px solid var(--gray-200);margin-bottom:20px;padding:14px 18px">
  <h3>Jadwal Mata Kuliah Saya</h3>
  <button class="btn-sm bp" onclick="openJadwalModal()">+ Atur Jadwal</button>
</div>

<div class="jadwal-grid">
  <?php if (!$myJadwal): ?>
    <div class="empty"><div class="eico">📆</div>Belum ada jadwal</div>
  <?php else: foreach ($myJadwal as $j): ?>
    <div class="jadwal-card">
      <div class="mk-name"><?= htmlspecialchars($j['mk']) ?></div>
      <div class="mk-meta">
        📆 <?= $j['hari'] ?><br>
        🕐 <?= substr($j['mulai'],0,5) ?> – <?= substr($j['selesai'],0,5) ?><br>
        🏫 <?= htmlspecialchars($j['ruang']) ?><br>
        ⏱ Toleransi: <b><?= $j['toleransi'] ?> menit</b>
      </div>
      <div class="mk-dosen">Kode: <?= htmlspecialchars($j['kode']) ?></div>
      <div class="mk-actions">
        <button class="btn-sm bw" onclick='editJadwal(<?= json_encode($j) ?>)'>✏️ Edit</button>
        <form method="POST" style="display:inline" onsubmit="return confirm('Hapus jadwal ini?')">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $j['id'] ?>">
          <button class="btn-sm bd">Hapus</button>
        </form>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<!-- MODAL: Atur Jadwal -->
<div class="modal-overlay" id="modal-jadwal">
  <div class="modal">
    <h3>Atur Jadwal Mata Kuliah</h3>
    <form method="POST" id="form-jadwal">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="edit_id" id="jd-edit-id" value="">
      <div class="field"><label>Mata Kuliah</label><input id="jd-mk" name="jd_mk" type="text" placeholder="Nama mata kuliah" required></div>
      <div class="field"><label>Kode MK</label><input id="jd-kode" name="jd_kode" type="text" placeholder="MK001" required></div>
      <div class="field"><label>Hari</label>
        <select id="jd-hari" name="jd_hari">
          <option>Senin</option><option>Selasa</option><option>Rabu</option>
          <option>Kamis</option><option>Jumat</option><option>Sabtu</option><option>Minggu</option>
        </select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="field"><label>Jam Mulai</label><input id="jd-mulai" name="jd_mulai" type="time" value="08:00" required></div>
        <div class="field"><label>Jam Selesai</label><input id="jd-selesai" name="jd_selesai" type="time" value="09:40" required></div>
      </div>
      <div class="field"><label>Toleransi Keterlambatan (menit)</label><input id="jd-toleransi" name="jd_toleransi" type="number" value="15" min="0" max="60"></div>
      <div class="field"><label>Ruangan</label><input id="jd-ruang" name="jd_ruang" type="text" placeholder="GD-A 101"></div>
      <div class="modal-footer">
        <button class="btn-sm bo" type="button" onclick="closeModal('modal-jadwal')">Batal</button>
        <button class="btn-sm bp" type="submit">Simpan Jadwal</button>
      </div>
    </form>
  </div>
</div>

<script>
function openJadwalModal() {
  document.getElementById('form-jadwal').reset();
  document.getElementById('jd-edit-id').value = '';
  document.getElementById('jd-mulai').value = '08:00';
  document.getElementById('jd-selesai').value = '09:40';
  document.getElementById('jd-toleransi').value = '15';
  openModal('modal-jadwal');
}
function editJadwal(j) {
  document.getElementById('jd-edit-id').value = j.id;
  document.getElementById('jd-mk').value = j.mk;
  document.getElementById('jd-kode').value = j.kode;
  document.getElementById('jd-hari').value = j.hari;
  document.getElementById('jd-mulai').value = j.mulai.slice(0,5);
  document.getElementById('jd-selesai').value = j.selesai.slice(0,5);
  document.getElementById('jd-toleransi').value = j.toleransi;
  document.getElementById('jd-ruang').value = j.ruang;
  openModal('modal-jadwal');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
