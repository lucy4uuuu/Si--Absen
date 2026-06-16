<?php
require_once __DIR__ . '/includes/functions.php';
requireRole('admin');
$db = getDB();
$pageTitle = 'Data Mahasiswa';

$flashOk  = '';
$flashErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $nim      = trim($_POST['mm_nim']      ?? '');
        $nama     = trim($_POST['mm_nama']     ?? '');
        $prodi    =      $_POST['mm_prodi']    ?? '';
        $angkatan = trim($_POST['mm_angkatan'] ?? '');

        if (!$nim || !$nama) {
            $flashErr = 'NIM dan Nama wajib diisi!';
        } else {
            $chk = $db->prepare("SELECT nim FROM mahasiswa WHERE nim=?");
            $chk->execute([$nim]);
            if ($chk->fetch()) {
                $flashErr = "NIM $nim sudah terdaftar!";
            } else {
                // Tambah ke tabel users
                $db->prepare("INSERT INTO users (id,password,role,name) VALUES (?,?,?,?)")
                   ->execute([$nim, 'pass', 'mahasiswa', $nama]);
                // Tambah ke tabel mahasiswa
                $db->prepare("INSERT INTO mahasiswa (nim,nama,prodi,angkatan,aktif) VALUES (?,?,?,?,1)")
                   ->execute([$nim, $nama, $prodi, $angkatan]);

                // ══ AUTO-ENROLL ke semua jadwal sesuai prodi ══
                autoEnrollByProdi($db, $nim, null);
                $count = $db->prepare("SELECT COUNT(*) FROM enrollment WHERE nim=?");
                $count->execute([$nim]);
                $jumlah = $count->fetchColumn();
                $flashOk = "Mahasiswa $nama berhasil ditambahkan dan otomatis terdaftar ke $jumlah mata kuliah sesuai prodi $prodi.";
            }
        }

    } elseif ($action === 'toggle') {
        $nim = $_POST['nim'] ?? '';
        $db->prepare("UPDATE mahasiswa SET aktif = NOT aktif WHERE nim=?")->execute([$nim]);

    } elseif ($action === 'delete') {
        $nim = $_POST['nim'] ?? '';
        $db->prepare("DELETE FROM mahasiswa WHERE nim=?")->execute([$nim]);
        $db->prepare("DELETE FROM users WHERE id=?")->execute([$nim]);
    }

    if (!$flashErr) {
        // simpan flash ke session supaya bisa muncul setelah redirect
        $_SESSION['flash_ok']  = $flashOk;
        $_SESSION['flash_err'] = $flashErr;
        header('Location: mahasiswa.php');
        exit;
    }
}

// ambil flash dari session
if (empty($flashOk) && !empty($_SESSION['flash_ok'])) {
    $flashOk = $_SESSION['flash_ok'];
    unset($_SESSION['flash_ok']);
}
if (empty($flashErr) && !empty($_SESSION['flash_err'])) {
    $flashErr = $_SESSION['flash_err'];
    unset($_SESSION['flash_err']);
}

$q = trim($_GET['q'] ?? '');
if ($q) {
    $stmt = $db->prepare("SELECT * FROM mahasiswa WHERE nim LIKE ? OR nama LIKE ? ORDER BY nim");
    $stmt->execute(["%$q%", "%$q%"]);
} else {
    $stmt = $db->query("SELECT * FROM mahasiswa ORDER BY nim");
}
$list = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <h3>Data Mahasiswa</h3>
    <div class="card-actions">
      <form method="GET" style="display:flex;gap:8px">
        <input class="sinput" type="text" name="q" placeholder="Cari nama/NIM…"
               value="<?= htmlspecialchars($q) ?>">
        <button class="btn-sm bo" type="submit">Cari</button>
      </form>
      <button class="btn-sm bp" onclick="openModal('modal-mhs')">+ Tambah</button>
    </div>
  </div>

  <?php if ($flashOk): ?>
    <div style="margin:0 18px 0;padding:10px 14px;background:var(--success-light);color:var(--success);
                border-bottom:1px solid #BBF7D0;font-size:13px">✅ <?= htmlspecialchars($flashOk) ?></div>
  <?php endif; ?>
  <?php if ($flashErr): ?>
    <div style="margin:0 18px 0;padding:10px 14px;background:var(--danger-light);color:var(--danger);
                border-bottom:1px solid #FECACA;font-size:13px">⚠️ <?= htmlspecialchars($flashErr) ?></div>
  <?php endif; ?>

  <table>
    <thead>
      <tr>
        <th>NIM</th><th>Nama</th><th>Prodi</th><th>Angkatan</th>
        <th>Terdaftar MK</th><th>Status</th><th>Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$list): ?>
        <tr><td colspan="7">
          <div class="empty"><div class="eico">👥</div>Tidak ada mahasiswa</div>
        </td></tr>
      <?php else: foreach ($list as $m):
        $mkCount = $db->prepare("SELECT COUNT(*) FROM enrollment WHERE nim=?");
        $mkCount->execute([$m['nim']]);
        $mkCount = $mkCount->fetchColumn();
      ?>
        <tr>
          <td><b><?= htmlspecialchars($m['nim']) ?></b></td>
          <td><?= htmlspecialchars($m['nama']) ?></td>
          <td><?= htmlspecialchars($m['prodi']) ?></td>
          <td><?= htmlspecialchars($m['angkatan']) ?></td>
          <td><span class="badge bg-blue"><?= $mkCount ?> MK</span></td>
          <td><span class="badge <?= $m['aktif'] ? 'bg-green' : 'bg-gray' ?>">
            <?= $m['aktif'] ? 'Aktif' : 'Non-aktif' ?></span>
          </td>
          <td>
            <form method="POST" style="display:inline">
              <input type="hidden" name="nim" value="<?= htmlspecialchars($m['nim']) ?>">
              <button class="btn-sm bo" name="action" value="toggle" style="margin-right:4px">
                <?= $m['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?>
              </button>
              <button class="btn-sm bd" name="action" value="delete"
                      onclick="return confirm('Hapus mahasiswa ini? Semua data absensinya juga akan terhapus.')">
                Hapus
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<!-- MODAL: Tambah Mahasiswa -->
<div class="modal-overlay" id="modal-mhs">
  <div class="modal">
    <h3>Tambah Mahasiswa</h3>
    <p style="font-size:12px;color:var(--gray-400);margin-bottom:16px">
      Mahasiswa baru akan otomatis terdaftar ke semua mata kuliah yang ada.
      Password default: <b>pass</b>
    </p>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="field">
        <label>NIM</label>
        <input name="mm_nim" type="text" placeholder="2024001" required>
      </div>
      <div class="field">
        <label>Nama Lengkap</label>
        <input name="mm_nama" type="text" required>
      </div>
      <div class="field">
        <label>Program Studi</label>
        <select name="mm_prodi">
          <option>Teknik Informatika</option>
          <option>Sistem Informasi</option>
          <option>Ilmu Komputer</option>
          <option>Teknik Elektro</option>
        </select>
      </div>
      <div class="field">
        <label>Angkatan</label>
        <input name="mm_angkatan" type="text" placeholder="2024">
      </div>
      <div class="modal-footer">
        <button class="btn-sm bo" type="button" onclick="closeModal('modal-mhs')">Batal</button>
        <button class="btn-sm bp" type="submit">Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
