<?php
// Expects $pageTitle to be set before include
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SiAbsen — <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></title>
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main">
  <div class="topbar">
    <h1><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
    <div class="date-badge" id="topbar-date">–</div>
  </div>
