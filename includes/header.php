<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_login();
$u = current_user();
$active = $active ?? '';
$role = current_role();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= e($pageTitle ?? 'PatientSys') ?></title>
<link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/logo.png">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<aside class="sidebar">
  <div class="brand">
    <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="Clinic Logo" class="brand-logo-sm">

  </div>
  <nav class="nav">
    <a class="<?= $active==='dashboard'?'active':'' ?>"    href="<?= BASE_URL ?>/index.php">🏠 Dashboard</a>
    <a class="<?= $active==='patients'?'active':'' ?>"     href="<?= BASE_URL ?>/patients/index.php">👥 Patients</a>
    <a class="<?= $active==='appointments'?'active':'' ?>" href="<?= BASE_URL ?>/appointments/index.php">📅 Appointments</a>
    <a class="<?= $active==='qr'?'active':'' ?>"           href="<?= BASE_URL ?>/qr/scanner.php">🔳 QR Scanner</a>
    <a class="<?= $active==='reports'?'active':'' ?>"      href="<?= BASE_URL ?>/reports/index.php">📊 Reports</a>
    <?php if ($role === 'admin'): ?>
      <a class="<?= $active==='users'?'active':'' ?>"      href="<?= BASE_URL ?>/users/index.php">👤 Users</a>
      <a class="<?= $active==='audit'?'active':'' ?>"      href="<?= BASE_URL ?>/audit.php">🛡 Audit Log</a>
    <?php endif; ?>
    <a target="_blank" href="<?= BASE_URL ?>/book.php">🌐 Online Booking</a>
    <a class="<?= $active==='settings'?'active':'' ?>"     href="<?= BASE_URL ?>/settings.php">⚙️ Settings</a>
  </nav>
  <a class="logout" href="<?= BASE_URL ?>/logout.php">↪ Logout</a>
</aside>

<main class="main">
  <header class="topbar">
    <button class="hamburger" onclick="document.body.classList.toggle('sidebar-open')">☰</button>
    <div class="topbar-title"><?= e(CLINIC_NAME) ?></div>
    <form class="search" method="get" action="<?= BASE_URL ?>/patients/index.php">
      <input type="text" name="q" placeholder="Search patient..." value="<?= e($_GET['q'] ?? '') ?>">
      <button type="submit">🔍</button>
    </form>
    <div class="topbar-right">
      <div class="bell-wrap">
        <button type="button" class="bell" id="bellBtn" title="Notifications" onclick="document.getElementById('bellMenu').classList.toggle('open')">
          🔔<span class="badge" id="bellBadge">3</span>
        </button>
        <div class="bell-menu" id="bellMenu">
          <div class="bell-menu-head">
            <strong>Notifications</strong>
            <button type="button" onclick="document.getElementById('bellBadge').remove();document.getElementById('bellMenu').classList.remove('open')">Mark all read</button>
          </div>
          <a href="<?= BASE_URL ?>/appointments/index.php" class="bell-item">📅 Today's appointments to confirm</a>
          <a href="<?= BASE_URL ?>/appointments/index.php" class="bell-item">⏰ Reminders due in next 24h</a>
          <a href="<?= BASE_URL ?>/audit.php" class="bell-item">🛡 New audit events</a>
        </div>
      </div>
      <div class="user">
        <div class="avatar"><?= strtoupper(substr($u['full_name'],0,1)) ?></div>
        <div>
          <div class="user-name"><?= e($u['full_name']) ?></div>
          <div class="user-role"><?= e(ucfirst($u['role'])) ?></div>
        </div>
      </div>
    </div>
  </header>
  <script>
    document.addEventListener('click', function(e){
      var m = document.getElementById('bellMenu');
      var b = document.getElementById('bellBtn');
      if (m && !m.contains(e.target) && e.target !== b && !b.contains(e.target)) m.classList.remove('open');
    });
  </script>
  <section class="content">
