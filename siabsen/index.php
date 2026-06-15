<?php
require_once __DIR__ . '/includes/functions.php';

if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = trim($_POST['login_user'] ?? '');
    $pw = $_POST['login_pass'] ?? '';
    $role = $_POST['login_role'] ?? '';

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND password = ? AND role = ?");
    $stmt->execute([$id, $pw, $role]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'role' => $user['role'],
        ];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Username atau password salah!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SiAbsen — Masuk</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div id="auth-screen">
  <div class="auth-card">
    <div class="auth-logo"><div class="icon">📋</div><span>SiAbsen</span></div>
    <h2>Masuk ke Sistem</h2>
    <p class="sub">Sistem Absensi Digital — Mahasiswa, Dosen & Admin</p>
    <?php if ($error): ?><div class="auth-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <div class="field"><label>Login sebagai</label>
        <select name="login_role">
          <option value="mahasiswa">Mahasiswa</option>
          <option value="dosen">Dosen</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <div class="field"><label>Username / NIM / NIP</label><input name="login_user" type="text" placeholder="Masukkan username" required></div>
      <div class="field"><label>Password</label><input name="login_pass" type="password" placeholder="••••••••" required></div>
      <button class="btn-full" type="submit">Masuk</button>
    </form>
    <div class="auth-hint">
      <b>Admin:</b> admin / admin123 &nbsp;|&nbsp; <b>Dosen:</b> dosen01 / pass<br>
      <b>Mahasiswa:</b> 2021001 / pass &nbsp;|&nbsp; dosen02 / pass (Dosen 2)
    </div>
  </div>
</div>
</body>
</html>
