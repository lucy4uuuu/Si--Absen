<?php
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$db = getDB();
$user = currentUser();
$pageTitle = 'Ganti Sandi';

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sandiLama  = $_POST['sandi_lama']  ?? '';
    $sandiBaru  = $_POST['sandi_baru']  ?? '';
    $konfirmasi = $_POST['konfirmasi']  ?? '';

    // Cek sandi lama
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();

    if ($row['password'] !== $sandiLama) {
        $error = 'Sandi lama tidak tepat!';
    } elseif (strlen($sandiBaru) < 4) {
        $error = 'Sandi baru minimal 4 karakter!';
    } elseif ($sandiBaru !== $konfirmasi) {
        $error = 'Konfirmasi sandi tidak cocok!';
    } else {
        $db->prepare("UPDATE users SET password = ? WHERE id = ?")
           ->execute([$sandiBaru, $user['id']]);
        $success = 'Sandi berhasil diubah!';
    }
}

include __DIR__ . '/includes/header.php';
?>

<div style="max-width:440px">
  <div class="card">
    <div class="card-header"><h3>🔒 Ganti Sandi</h3></div>
    <div style="padding:24px">

      <?php if ($error): ?>
        <div class="auth-error" style="margin-bottom:14px">⚠️ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div style="background:var(--success-light);color:var(--success);border:1px solid #BBF7D0;border-radius:7px;padding:10px 14px;font-size:13px;margin-bottom:14px">
          ✅ <?= htmlspecialchars($success) ?>
        </div>
      <?php endif; ?>

      <form method="POST">
        <div class="field">
          <label>Sandi Lama</label>
          <input name="sandi_lama" type="password" placeholder="Masukkan sandi lama" required>
        </div>
        <div class="field">
          <label>Sandi Baru</label>
          <input name="sandi_baru" type="password" placeholder="Minimal 4 karakter" required>
        </div>
        <div class="field">
          <label>Konfirmasi Sandi Baru</label>
          <input name="konfirmasi" type="password" placeholder="Ulangi sandi baru" required>
        </div>
        <button class="btn-full" type="submit">Simpan Perubahan</button>
      </form>

      <div style="margin-top:14px;font-size:12px;color:var(--gray-400);text-align:center">
        Login sebagai: <b style="color:var(--gray-700)"><?= htmlspecialchars($user['name']) ?></b>
        (<?= ucfirst($user['role']) ?>)
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
