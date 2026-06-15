<?php
require_once __DIR__ . '/includes/functions.php';
requireRole('mahasiswa');
$db = getDB();
$user = currentUser();
$pageTitle = 'Izin / Alpha';
$today = date('Y-m-d');

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jadwalId = $_POST['iz_jadwal_id'] ?? '';
    $mk = $_POST['iz_mk'] ?? '';
    $tgl = $_POST['iz_tgl'] ?? $today;
    $jenis = $_POST['iz_jenis'] ?? '';
    $ket = trim($_POST['iz_ket'] ?? '');

    $validJenis = ['izin-sakit','izin-keluarga','izin-akademik','izin-lain','alpha'];
    if (!$mk || !in_array($jenis, $validJenis)) {
        $error = 'Lengkapi data pengajuan (mata kuliah & jenis)!';
    } else {
        $buktiPath = null;

        // Handle file upload (surat dokter / foto kegiatan)
        if (!empty($_FILES['iz_bukti']['name'])) {
            $file = $_FILES['iz_bukti'];
            $allowedExt = ['jpg','jpeg','png','pdf'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'Gagal mengunggah file.';
            } elseif (!in_array($ext, $allowedExt)) {
                $error = 'Format file tidak didukung. Gunakan JPG, PNG, atau PDF.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = 'Ukuran file maksimal 5MB.';
            } else {
                $uploadDir = __DIR__ . '/uploads/izin/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $fname = 'izin_' . $user['id'] . '_' . time() . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $fname)) {
                    $buktiPath = 'uploads/izin/' . $fname;
                } else {
                    $error = 'Gagal menyimpan file.';
                }
            }
        }

        if (!$error) {
            $id = uid('IZ');
            $stmt = $db->prepare("INSERT INTO izin (id, nim, nama, mk, jadwal_id, tanggal, jenis, keterangan, bukti_file, status) VALUES (?,?,?,?,?,?,?,?,?,'pending')");
            $stmt->execute([$id, $user['id'], $user['name'], $mk, $jadwalId ?: null, $tgl, $jenis, $ket, $buktiPath]);
            $success = 'Pengajuan terkirim, menunggu persetujuan!';
        }
    }
}

// Jadwal untuk dropdown
$jadwalStmt = $db->prepare("
    SELECT j.* FROM jadwal j
    INNER JOIN enrollment e ON e.jadwal_id = j.id
    WHERE e.nim = ? ORDER BY j.mk
");
$jadwalStmt->execute([$user['id']]);
$jadwalList = $jadwalStmt->fetchAll();

// History
$histStmt = $db->prepare("SELECT * FROM izin WHERE nim=? ORDER BY created_at DESC");
$histStmt->execute([$user['id']]);
$history = $histStmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:520px">
  <div class="card-header"><h3>Ajukan Izin / Alpha</h3></div>
  <div style="padding:20px">
    <?php if ($error): ?><div class="auth-error" style="margin-bottom:14px">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="info-row" style="background:var(--success-light);color:var(--success)">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <div class="field"><label>Mata Kuliah</label>
        <select name="iz_mk" id="iz-mk" onchange="updateIzJadwalId()" required>
          <?php foreach ($jadwalList as $j): ?>
            <option value="<?= htmlspecialchars($j['mk']) ?>" data-id="<?= $j['id'] ?>"><?= htmlspecialchars($j['mk']) ?> (<?= $j['hari'] ?>)</option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="iz_jadwal_id" id="iz-jadwal-id" value="<?= $jadwalList[0]['id'] ?? '' ?>">
      </div>
      <div class="field"><label>Tanggal</label><input name="iz_tgl" type="date" value="<?= $today ?>" required></div>
      <div class="field"><label>Jenis</label>
        <div class="izin-reasons" id="iz-reasons">
          <div class="izin-btn" onclick="selectIzin(this,'izin-sakit')">🤒 Sakit</div>
          <div class="izin-btn" onclick="selectIzin(this,'izin-keluarga')">👨‍👩‍👧 Keluarga</div>
          <div class="izin-btn" onclick="selectIzin(this,'izin-akademik')">📚 Keperluan Akademik</div>
          <div class="izin-btn" onclick="selectIzin(this,'izin-lain')">📝 Lainnya</div>
          <div class="izin-btn" onclick="selectIzin(this,'alpha')">❌ Alpha (tidak hadir)</div>
        </div>
        <input type="hidden" name="iz_jenis" id="iz-jenis-input" required>
      </div>
      <div class="field"><label>Keterangan</label><textarea name="iz_ket" rows="3" placeholder="Tuliskan keterangan tambahan..."></textarea></div>
      <div class="field">
        <label>Bukti (Surat Dokter / Foto Kegiatan)</label>
        <label class="file-drop" for="iz-bukti-input">
          📎 Klik untuk pilih file (JPG, PNG, atau PDF — maks 5MB)
        </label>
        <input type="file" id="iz-bukti-input" name="iz_bukti" accept=".jpg,.jpeg,.png,.pdf" style="display:none" onchange="showFilePreview(this)">
        <div class="file-preview" id="file-preview-text"></div>
      </div>
      <button class="btn-sm bp" type="submit" style="width:100%;padding:10px">Kirim Pengajuan</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3>Riwayat Pengajuan Saya</h3></div>
  <table>
    <thead><tr><th>Tanggal</th><th>Mata Kuliah</th><th>Jenis</th><th>Keterangan</th><th>Bukti</th><th>Status</th></tr></thead>
    <tbody>
      <?php if (!$history): ?>
        <tr><td colspan="6"><div class="empty"><div class="eico">📝</div>Belum ada pengajuan</div></td></tr>
      <?php else: foreach ($history as $i): ?>
        <tr>
          <td><?= $i['tanggal'] ?></td>
          <td><?= htmlspecialchars($i['mk']) ?></td>
          <td><span class="badge bg-yellow"><?= jenisLabel($i['jenis']) ?></span></td>
          <td><?= htmlspecialchars($i['keterangan'] ?: '–') ?></td>
          <td>
            <?php if ($i['bukti_file']): ?>
              <a class="bukti-link" href="<?= htmlspecialchars($i['bukti_file']) ?>" target="_blank">📎 Lihat</a>
            <?php else: ?>–<?php endif; ?>
          </td>
          <td><?= izinStatusBadge($i['status']) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<script>
function selectIzin(el, jenis) {
  document.querySelectorAll('.izin-btn').forEach(b=>b.classList.remove('sel'));
  el.classList.add('sel');
  document.getElementById('iz-jenis-input').value = jenis;
}
function updateIzJadwalId() {
  const sel = document.getElementById('iz-mk');
  const opt = sel.options[sel.selectedIndex];
  document.getElementById('iz-jadwal-id').value = opt.dataset.id || '';
}
function showFilePreview(input) {
  const preview = document.getElementById('file-preview-text');
  if (input.files && input.files[0]) {
    preview.textContent = '✅ File terpilih: ' + input.files[0].name;
  } else {
    preview.textContent = '';
  }
}

// Validasi sederhana sebelum submit
document.querySelector('form').addEventListener('submit', function(e) {
  if (!document.getElementById('iz-jenis-input').value) {
    e.preventDefault();
    showToast('Pilih jenis izin/alpha!', 'error');
  }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
