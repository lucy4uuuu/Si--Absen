<?php
require_once __DIR__ . '/includes/functions.php';
requireRole(['admin','dosen']);
$db  = getDB();
$user = currentUser();
$role = $user['role'];
$pageTitle = 'Kelola Jadwal';

// Daftar prodi tetap (bisa diperluas)
$PRODI_LIST = [
    'Teknik Informatika',
    'Sistem Informasi',
    'Ilmu Komputer',
    'Teknik Elektro',
];

// Prodi yang bisa dipilih dosen (hanya prodi yang dia ajar)
// Admin bisa semua prodi
$prodiDosen = $user['prodi'] ?? null; // null = belum diset

// ============================================
// POST HANDLER
// ============================================
$flashOk = ''; $flashErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $editId    = trim($_POST['edit_id']     ?? '');
        $mk        = trim($_POST['jd_mk']       ?? '');
        $kode      = trim($_POST['jd_kode']     ?? '');
        $hari      =      $_POST['jd_hari']     ?? 'Senin';
        $mulai     =      $_POST['jd_mulai']    ?? '08:00';
        $selesai   =      $_POST['jd_selesai']  ?? '09:40';
        $toleransi = (int)($_POST['jd_toleransi'] ?? 15);
        $ruang     = trim($_POST['jd_ruang']    ?? '');
        $prodi     =      $_POST['jd_prodi']    ?? '';

        // Dosen: validasi prodi harus miliknya
        if ($role === 'dosen' && $prodiDosen && $prodi !== $prodiDosen) {
            $flashErr = 'Anda hanya bisa membuat jadwal untuk prodi yang Anda ajar.';
        } elseif (!$mk || !$kode || !$prodi) {
            $flashErr = 'Nama MK, Kode, dan Prodi wajib diisi!';
        } else {
            $dosenId = ($role === 'dosen') ? $user['id'] : ($_POST['jd_dosen'] ?? $user['id']);

            if ($editId) {
                $cond = $role === 'admin' ? '' : 'AND dosen_id = ?';
                $params = [$mk,$kode,$hari,$mulai,$selesai,$toleransi,$ruang,$prodi,$editId];
                if ($role === 'dosen') $params[] = $user['id'];
                $db->prepare("UPDATE jadwal SET mk=?,kode=?,hari=?,mulai=?,selesai=?,toleransi=?,ruang=?,prodi=?
                              WHERE id=? $cond")->execute($params);
                $flashOk = "Jadwal \"$mk\" berhasil diperbarui.";
            } else {
                $newId = uid('J');
                $db->prepare("INSERT INTO jadwal (id,mk,kode,dosen_id,hari,mulai,selesai,toleransi,ruang,prodi)
                              VALUES (?,?,?,?,?,?,?,?,?,?)")
                   ->execute([$newId,$mk,$kode,$dosenId,$hari,$mulai,$selesai,$toleransi,$ruang,$prodi]);

                // Auto-enroll semua mahasiswa sesuai prodi
                autoEnrollByProdi($db, null, $newId);
                $countEnroll = $db->prepare("SELECT COUNT(*) FROM enrollment WHERE jadwal_id=?");
                $countEnroll->execute([$newId]);
                $enrolled = $countEnroll->fetchColumn();
                $flashOk = "Jadwal \"$mk\" ditambahkan. $enrolled mahasiswa prodi $prodi otomatis terdaftar.";
            }
        }

    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        $cond = $role === 'admin' ? '' : 'AND dosen_id = ?';
        $params = [$id];
        if ($role === 'dosen') $params[] = $user['id'];
        $db->prepare("DELETE FROM jadwal WHERE id=? $cond")->execute($params);
        $flashOk = 'Jadwal dihapus.';

    } elseif ($action === 'set_prodi' && $role === 'dosen') {
        // Dosen update prodi yang dia ajar
        $prodi = $_POST['dosen_prodi'] ?? '';
        if (in_array($prodi, $PRODI_LIST)) {
            $db->prepare("UPDATE users SET prodi=? WHERE id=?")->execute([$prodi, $user['id']]);
            $_SESSION['user']['prodi'] = $prodi;
            $prodiDosen = $prodi;
            $flashOk = "Prodi Anda diset ke: $prodi";
        }
    }

    $_SESSION['flash_ok']  = $flashOk;
    $_SESSION['flash_err'] = $flashErr;
    header('Location: jadwal.php');
    exit;
}

// Ambil flash dari session
if (!empty($_SESSION['flash_ok']))  { $flashOk  = $_SESSION['flash_ok'];  unset($_SESSION['flash_ok']); }
if (!empty($_SESSION['flash_err'])) { $flashErr = $_SESSION['flash_err']; unset($_SESSION['flash_err']); }

// Ambil jadwal sesuai role
if ($role === 'dosen') {
    $stmt = $db->prepare("SELECT j.*, u.name as dosen_nama FROM jadwal j
                          JOIN users u ON u.id = j.dosen_id
                          WHERE j.dosen_id=? ORDER BY j.hari, j.mulai");
    $stmt->execute([$user['id']]);
} else {
    $stmt = $db->query("SELECT j.*, u.name as dosen_nama FROM jadwal j
                        JOIN users u ON u.id = j.dosen_id
                        ORDER BY j.prodi, j.hari, j.mulai");
}
$myJadwal = $stmt->fetchAll();

// Dosen list untuk admin
$dosenList = [];
if ($role === 'admin') {
    $dosenList = $db->query("SELECT id, name, prodi FROM users WHERE role='dosen' ORDER BY name")->fetchAll();
}

// Refresh prodi dari DB (kalau baru di-set)
if ($role === 'dosen') {
    $row = $db->prepare("SELECT prodi FROM users WHERE id=?");
    $row->execute([$user['id']]);
    $prodiDosen = $row->fetchColumn() ?: null;
    if ($prodiDosen) $_SESSION['user']['prodi'] = $prodiDosen;
}

include __DIR__ . '/includes/header.php';
?>

<?php if ($flashOk): ?>
  <div style="background:var(--success-light);color:var(--success);border:1px solid #BBF7D0;
              border-radius:9px;padding:11px 16px;margin-bottom:18px;font-size:13px">
    ✅ <?= htmlspecialchars($flashOk) ?>
  </div>
<?php endif; ?>
<?php if ($flashErr): ?>
  <div class="auth-error" style="margin-bottom:18px">⚠️ <?= htmlspecialchars($flashErr) ?></div>
<?php endif; ?>

<?php if ($role === 'dosen' && !$prodiDosen): ?>
<!-- Dosen belum set prodinya -->
<div style="background:var(--warning-light);border:1px solid #FDE68A;border-radius:10px;padding:18px 20px;margin-bottom:20px">
  <div style="font-size:14px;font-weight:700;color:var(--warning);margin-bottom:12px">
    ⚠️ Anda belum mengatur prodi yang Anda ajar
  </div>
  <p style="font-size:13px;color:var(--gray-700);margin-bottom:14px">
    Atur dulu prodi Anda agar bisa membuat jadwal dan mahasiswa terdaftar dengan benar.
  </p>
  <form method="POST" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <input type="hidden" name="action" value="set_prodi">
    <select name="dosen_prodi" class="sinput" style="width:220px">
      <?php foreach ($PRODI_LIST as $p): ?>
        <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn-sm bw" type="submit">Simpan Prodi Saya</button>
  </form>
</div>
<?php elseif ($role === 'dosen'): ?>
<div style="background:var(--primary-light);border:1px solid #BFDBFE;border-radius:9px;
            padding:10px 16px;margin-bottom:18px;font-size:13px;display:flex;align-items:center;justify-content:space-between">
  <span>📚 Prodi yang Anda ajar: <b><?= htmlspecialchars($prodiDosen) ?></b></span>
  <form method="POST" style="display:flex;gap:8px;align-items:center">
    <input type="hidden" name="action" value="set_prodi">
    <select name="dosen_prodi" class="sinput" style="width:200px">
      <?php foreach ($PRODI_LIST as $p): ?>
        <option value="<?= htmlspecialchars($p) ?>" <?= $p===$prodiDosen?'selected':'' ?>>
          <?= htmlspecialchars($p) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button class="btn-sm bo" type="submit" style="font-size:11px">Ubah</button>
  </form>
</div>
<?php endif; ?>

<div class="card-header" style="background:var(--white);border-radius:10px;border:1px solid var(--gray-200);
                                margin-bottom:20px;padding:14px 18px">
  <h3><?= $role==='admin' ? 'Semua Jadwal Mata Kuliah' : 'Jadwal Mata Kuliah Saya' ?></h3>
  <button class="btn-sm bp" onclick="openJadwalModal()">+ Tambah Jadwal</button>
</div>

<?php if ($role === 'admin'):
  // Kelompokkan per prodi untuk admin
  $byProdi = [];
  foreach ($myJadwal as $j) $byProdi[$j['prodi'] ?: 'Belum diset'][] = $j;
?>
  <?php foreach ($byProdi as $prodi => $jadwals): ?>
    <div style="font-size:12px;font-weight:700;color:var(--gray-400);text-transform:uppercase;
                letter-spacing:.6px;margin-bottom:10px;margin-top:4px">
      📚 <?= htmlspecialchars($prodi) ?>
    </div>
    <div class="jadwal-grid" style="margin-bottom:24px">
      <?php foreach ($jadwals as $j): ?>
        <div class="jadwal-card">
          <div class="mk-name"><?= htmlspecialchars($j['mk']) ?></div>
          <div class="mk-meta">
            📆 <?= $j['hari'] ?><br>
            🕐 <?= substr($j['mulai'],0,5) ?> – <?= substr($j['selesai'],0,5) ?><br>
            🏫 <?= htmlspecialchars($j['ruang'] ?: '-') ?><br>
            ⏱ Toleransi: <b><?= $j['toleransi'] ?> menit</b>
          </div>
          <div class="mk-dosen" style="margin-top:6px">👨‍🏫 <?= htmlspecialchars($j['dosen_nama']) ?></div>
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
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$myJadwal): ?>
    <div class="empty"><div class="eico">📆</div>Belum ada jadwal</div>
  <?php endif; ?>

<?php else: // dosen ?>
  <div class="jadwal-grid">
    <?php if (!$myJadwal): ?>
      <div class="empty"><div class="eico">📆</div>Belum ada jadwal</div>
    <?php else: foreach ($myJadwal as $j): ?>
      <div class="jadwal-card">
        <div class="mk-name"><?= htmlspecialchars($j['mk']) ?></div>
        <div class="mk-meta">
          📆 <?= $j['hari'] ?><br>
          🕐 <?= substr($j['mulai'],0,5) ?> – <?= substr($j['selesai'],0,5) ?><br>
          🏫 <?= htmlspecialchars($j['ruang'] ?: '-') ?><br>
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
<?php endif; ?>

<!-- ══ MODAL: Tambah/Edit Jadwal ══ -->
<div class="modal-overlay" id="modal-jadwal">
  <div class="modal">
    <h3 id="modal-jadwal-title">Tambah Jadwal Mata Kuliah</h3>
    <form method="POST" id="form-jadwal">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="edit_id" id="jd-edit-id" value="">

      <?php if ($role === 'admin'): ?>
      <div class="field">
        <label>Prodi</label>
        <select id="jd-prodi" name="jd_prodi" required onchange="filterDosenByProdi(this.value)">
          <option value="">— Pilih Prodi —</option>
          <?php foreach ($PRODI_LIST as $p): ?>
            <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Dosen Pengampu</label>
        <select id="jd-dosen" name="jd_dosen">
          <?php foreach ($dosenList as $d): ?>
            <option value="<?= htmlspecialchars($d['id']) ?>"
                    data-prodi="<?= htmlspecialchars($d['prodi'] ?? '') ?>">
              <?= htmlspecialchars($d['name']) ?>
              <?= $d['prodi'] ? '(' . $d['prodi'] . ')' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php else: ?>
      <input type="hidden" name="jd_prodi" id="jd-prodi" value="<?= htmlspecialchars($prodiDosen ?? '') ?>">
      <div class="field">
        <label>Prodi</label>
        <input type="text" value="<?= htmlspecialchars($prodiDosen ?? 'Belum diset') ?>" disabled
               style="background:var(--gray-50)">
      </div>
      <?php endif; ?>

      <div class="field"><label>Nama Mata Kuliah</label>
        <input id="jd-mk" name="jd_mk" type="text" placeholder="cth. Pemrograman Web" required>
      </div>
      <div class="field"><label>Kode MK</label>
        <input id="jd-kode" name="jd_kode" type="text" placeholder="MK201" required>
      </div>
      <div class="field"><label>Hari</label>
        <select id="jd-hari" name="jd_hari">
          <option>Senin</option><option>Selasa</option><option>Rabu</option>
          <option>Kamis</option><option>Jumat</option><option>Sabtu</option><option>Minggu</option>
        </select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="field"><label>Jam Mulai</label>
          <input id="jd-mulai" name="jd_mulai" type="time" value="08:00" required>
        </div>
        <div class="field"><label>Jam Selesai</label>
          <input id="jd-selesai" name="jd_selesai" type="time" value="09:40" required>
        </div>
      </div>
      <div class="field"><label>Toleransi Keterlambatan (menit)</label>
        <input id="jd-toleransi" name="jd_toleransi" type="number" value="15" min="0" max="60">
      </div>
      <div class="field"><label>Ruangan</label>
        <input id="jd-ruang" name="jd_ruang" type="text" placeholder="GD-A 101">
      </div>
      <div class="modal-footer">
        <button class="btn-sm bo" type="button" onclick="closeModal('modal-jadwal')">Batal</button>
        <button class="btn-sm bp" type="submit" id="btn-save-jadwal">Simpan Jadwal</button>
      </div>
    </form>
  </div>
</div>

<script>
const ROLE = '<?= $role ?>';
const dosenData = <?= json_encode(array_map(fn($d)=>['id'=>$d['id'],'name'=>$d['name'],'prodi'=>$d['prodi']??''], $dosenList)) ?>;

function openJadwalModal() {
  document.getElementById('modal-jadwal-title').textContent = 'Tambah Jadwal Mata Kuliah';
  document.getElementById('form-jadwal').reset();
  document.getElementById('jd-edit-id').value = '';
  document.getElementById('jd-mulai').value = '08:00';
  document.getElementById('jd-selesai').value = '09:40';
  document.getElementById('jd-toleransi').value = '15';
  openModal('modal-jadwal');
}

function editJadwal(j) {
  document.getElementById('modal-jadwal-title').textContent = 'Edit Jadwal';
  document.getElementById('jd-edit-id').value = j.id;
  document.getElementById('jd-mk').value      = j.mk;
  document.getElementById('jd-kode').value    = j.kode;
  document.getElementById('jd-hari').value    = j.hari;
  document.getElementById('jd-mulai').value   = j.mulai.slice(0,5);
  document.getElementById('jd-selesai').value = j.selesai.slice(0,5);
  document.getElementById('jd-toleransi').value = j.toleransi;
  document.getElementById('jd-ruang').value   = j.ruang || '';
  if (ROLE === 'admin') {
    document.getElementById('jd-prodi').value  = j.prodi || '';
    document.getElementById('jd-dosen').value  = j.dosen_id || '';
  }
  openModal('modal-jadwal');
}

// Admin: filter dosen sesuai prodi yang dipilih
function filterDosenByProdi(prodi) {
  const sel = document.getElementById('jd-dosen');
  if (!sel) return;
  Array.from(sel.options).forEach(opt => {
    opt.style.display = (!prodi || opt.dataset.prodi === prodi || opt.dataset.prodi === '') ? '' : 'none';
  });
  // Auto-select first visible dosen
  const first = Array.from(sel.options).find(o => o.style.display !== 'none');
  if (first) sel.value = first.value;
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
