<?php
require_once __DIR__ . '/includes/functions.php';
requireRole(['admin','dosen']);
$db = getDB();
$user = currentUser();
$pageTitle = 'Pengajuan Izin';

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? '';
    $stmt = $db->prepare("SELECT * FROM izin WHERE id=?");
    $stmt->execute([$id]);
    $iz = $stmt->fetch();

    if ($iz) {
        if ($action === 'approve') {
            $db->prepare("UPDATE izin SET status='disetujui' WHERE id=?")->execute([$id]);
            // update/create absensi record
            $chk = $db->prepare("SELECT id FROM absensi WHERE user_id=? AND mk=? AND tanggal=? AND type='mahasiswa'");
            $chk->execute([$iz['nim'], $iz['mk'], $iz['tanggal']]);
            $existing = $chk->fetch();
            if ($existing) {
                $db->prepare("UPDATE absensi SET status=? WHERE id=?")->execute([$iz['jenis'], $existing['id']]);
            } else {
                $aid = uid('A');
                $db->prepare("INSERT INTO absensi (id,type,user_id,nama,mk,jadwal_id,tanggal,checkin,checkout,status) VALUES (?,'mahasiswa',?,?,?,?,?,NULL,NULL,?)")
                   ->execute([$aid, $iz['nim'], $iz['nama'], $iz['mk'], $iz['jadwal_id'], $iz['tanggal'], $iz['jenis']]);
            }
        } elseif ($action === 'reject') {
            $db->prepare("UPDATE izin SET status='ditolak' WHERE id=?")->execute([$id]);
        }
    }
    header('Location: izin_masuk.php');
    exit;
}

// List
if ($user['role'] === 'dosen') {
    $myMksStmt = $db->prepare("SELECT mk FROM jadwal WHERE dosen_id=?");
    $myMksStmt->execute([$user['id']]);
    $myMks = array_column($myMksStmt->fetchAll(), 'mk');
    if ($myMks) {
        $in = implode(',', array_fill(0, count($myMks), '?'));
        $stmt = $db->prepare("SELECT * FROM izin WHERE mk IN ($in) ORDER BY created_at DESC");
        $stmt->execute($myMks);
        $list = $stmt->fetchAll();
    } else {
        $list = [];
    }
} else {
    $list = $db->query("SELECT * FROM izin ORDER BY created_at DESC")->fetchAll();
}

include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-header"><h3>Pengajuan Izin Masuk</h3></div>
  <table>
    <thead><tr><th>Tanggal</th><th>NIM</th><th>Nama</th><th>Mata Kuliah</th><th>Jenis</th><th>Keterangan</th><th>Bukti</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
      <?php if (!$list): ?>
        <tr><td colspan="9"><div class="empty"><div class="eico">📩</div>Tidak ada pengajuan</div></td></tr>
      <?php else: foreach ($list as $i): ?>
        <tr>
          <td><?= $i['tanggal'] ?></td>
          <td><?= htmlspecialchars($i['nim']) ?></td>
          <td><?= htmlspecialchars($i['nama']) ?></td>
          <td><?= htmlspecialchars($i['mk']) ?></td>
          <td><span class="badge bg-yellow"><?= jenisLabel($i['jenis']) ?></span></td>
          <td><?= htmlspecialchars($i['keterangan'] ?: '–') ?></td>
          <td>
            <?php if ($i['bukti_file']): ?>
              <a class="bukti-link" href="<?= htmlspecialchars($i['bukti_file']) ?>" target="_blank">📎 Lihat</a>
            <?php else: ?>–<?php endif; ?>
          </td>
          <td><?= izinStatusBadge($i['status']) ?></td>
          <td>
            <?php if ($i['status']==='pending'): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="id" value="<?= $i['id'] ?>">
                <button class="btn-sm bs" name="action" value="approve" style="margin-right:4px">✓ Setuju</button>
                <button class="btn-sm bd" name="action" value="reject">✗ Tolak</button>
              </form>
            <?php else: ?>–<?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
