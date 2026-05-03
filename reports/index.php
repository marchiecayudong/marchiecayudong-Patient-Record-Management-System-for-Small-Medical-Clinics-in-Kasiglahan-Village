<?php
$pageTitle = 'Reports';
$active = 'reports';
require_once __DIR__ . '/../includes/header.php';

$totalPatients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$byGender = $pdo->query("SELECT gender, COUNT(*) c FROM patients GROUP BY gender")->fetchAll();
$byMonth  = $pdo->query("SELECT DATE_FORMAT(date_added,'%Y-%m') ym, COUNT(*) c FROM patients GROUP BY ym ORDER BY ym DESC LIMIT 6")->fetchAll();
?>
<div class="grid-2">
  <div class="panel">
    <h2 style="margin-bottom:14px">Patients by Gender</h2>
    <table>
      <thead><tr><th>Gender</th><th>Count</th></tr></thead>
      <tbody>
      <?php foreach ($byGender as $g): ?>
        <tr><td><?= e($g['gender']) ?></td><td><?= e($g['c']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="panel">
    <h2 style="margin-bottom:14px">Patients Added (last 6 months)</h2>
    <table>
      <thead><tr><th>Month</th><th>Count</th></tr></thead>
      <tbody>
      <?php foreach ($byMonth as $m): ?>
        <tr><td><?= e($m['ym']) ?></td><td><?= e($m['c']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="panel">
  <h2>Export</h2>
  <p style="color:#6b7280;font-size:13px;margin:8px 0 14px">Download all patient records as CSV.</p>
  <a class="btn btn-primary" href="<?= BASE_URL ?>/reports/export.php">⬇ Export CSV</a>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
