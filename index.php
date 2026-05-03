<?php
$pageTitle = 'Dashboard • PatientSys';
$active = 'dashboard';
require_once __DIR__ . '/includes/header.php';

$totalPatients = (int)$pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$totalAppts    = (int)$pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$totalReports  = (int)$pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn();
$qrGenerated   = $totalPatients; // 1 QR per patient

$recent = $pdo->query("SELECT * FROM patients ORDER BY id DESC LIMIT 5")->fetchAll();
$today  = $pdo->query("SELECT a.*, p.name FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE a.appt_date = CURDATE() ORDER BY a.appt_time")->fetchAll();
?>
<div class="hero">
  <div>
    <h1>Welcome back, <?= e($u['full_name']) ?>! 👋</h1>
    <p>Patient Record Manager System</p>
  </div>
  <blockquote>" Sometimes successful people are not talented, they just work hard, then succeed on purpose. "</blockquote>
  <div class="date">
    <div><?= date('F j, Y') ?></div>
    <div><?= date('l, h:i A') ?></div>
  </div>
</div>

<div class="stats">
  <div class="stat"><div class="icon green">👥</div><div><div class="label">Total Patients</div><div class="value"><?= $totalPatients ?></div><div class="delta">↑ this month</div></div></div>
  <div class="stat"><div class="icon amber">📅</div><div><div class="label">Total Appointments</div><div class="value"><?= $totalAppts ?></div><div class="delta">↑ this month</div></div></div>
  <div class="stat"><div class="icon violet">🔳</div><div><div class="label">QR Generated</div><div class="value"><?= $qrGenerated ?></div><div class="delta">↑ this month</div></div></div>
  <div class="stat"><div class="icon blue">📊</div><div><div class="label">Reports Generated</div><div class="value"><?= $totalReports ?></div><div class="delta">↑ this month</div></div></div>
</div>

<div class="grid-2">
  <div class="panel">
    <div class="panel-head"><h2>Recent Patients</h2><a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/patients/add.php">+ Add Patient</a></div>
    <table>
      <thead><tr><th>ID</th><th>Name</th><th>Age</th><th>Gender</th><th>Contact</th><th>Date Added</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td>#<?= e($r['id']) ?></td>
          <td><?= e($r['name']) ?></td>
          <td><?= e($r['age']) ?></td>
          <td><?= e($r['gender']) ?></td>
          <td><?= e($r['contact']) ?></td>
          <td><?= e(date('M j, Y', strtotime($r['date_added']))) ?></td>
          <td class="actions">
            <a class="view" title="View" href="<?= BASE_URL ?>/patients/view.php?id=<?= $r['id'] ?>">👁</a>
            <a class="edit" title="Edit" href="<?= BASE_URL ?>/patients/edit.php?id=<?= $r['id'] ?>">✏</a>
            <a class="del del-confirm" title="Delete" href="<?= BASE_URL ?>/patients/delete.php?id=<?= $r['id'] ?>">🗑</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div style="text-align:center;margin-top:12px"><a href="<?= BASE_URL ?>/patients/index.php" style="color:#0f5132;font-size:13px">View all patients →</a></div>
  </div>

  <div>
    <div class="panel">
      <div class="panel-head"><h2>Today's Appointments</h2><a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/appointments/index.php">View Calendar</a></div>
      <?php if (!$today): ?><p style="color:#6b7280;font-size:13px">No appointments scheduled for today.</p><?php endif; ?>
      <?php foreach ($today as $a): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f3f4f6">
          <span class="time-pill"><?= e(date('h:i A', strtotime($a['appt_time']))) ?></span>
          <span style="flex:1;margin-left:12px;font-size:14px"><?= e($a['name']) ?></span>
          <span style="font-size:13px;color:#6b7280;margin-right:12px"><?= e($a['purpose']) ?></span>
          <span class="badge-status badge-<?= strtolower($a['status']) ?>"><?= e($a['status']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="panel">
      <h2 style="margin-bottom:14px">System Information</h2>
      <ul class="sys-list">
        <li><span>System Status</span><span>Online <span class="dot"></span></span></li>
        <li><span>Database</span><span>Connected <span class="dot"></span></span></li>
        <li><span>API Status</span><span>Active <span class="dot"></span></span></li>
        <li><span>Last Backup</span><span><?= date('M j, Y h:i A') ?></span></li>
      </ul>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
