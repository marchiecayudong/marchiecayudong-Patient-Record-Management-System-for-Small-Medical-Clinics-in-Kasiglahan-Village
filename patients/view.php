<?php
$pageTitle = 'Patient Details';
$active = 'patients';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id=?");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) { echo "<div class='panel'>Patient not found.</div>"; require __DIR__ . '/../includes/footer.php'; exit; }
audit_log('ui.patient.view', "patient/$id", 200);
?>
<div class="panel" style="max-width:720px">
  <div class="panel-head"><h2>Patient #<?= $p['id'] ?></h2>
    <div style="display:flex;gap:8px">
      <?php if (role_can('generate_qr')): ?><a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/qr/generate.php?id=<?= $p['id'] ?>">Generate QR</a><?php endif; ?>
      <?php if (role_can('edit_patient')): ?><a class="btn btn-sm" href="<?= BASE_URL ?>/patients/edit.php?id=<?= $p['id'] ?>">Edit</a><?php endif; ?>
    </div>
  </div>
  <table class="table">
    <tr><th>Name</th><td><?= e($p['name']) ?></td></tr>
    <tr><th>Age</th><td><?= e($p['age']) ?></td></tr>
    <tr><th>Gender</th><td><?= e($p['gender']) ?></td></tr>
    <tr><th>Contact</th><td><?= e($p['contact']) ?></td></tr>
    <tr><th>Email</th><td><?= e($p['email']) ?></td></tr>
    <tr><th>Address</th><td><?= e($p['address']) ?></td></tr>
    <tr><th>Date Added</th><td><?= e($p['date_added']) ?></td></tr>
    <tr><th>Consent — Data Sharing (FHIR)</th><td><?= $p['consent_share']?'✅ Granted':'❌ <span style=color:#dc2626>Not granted — FHIR API will return 403</span>' ?></td></tr>
    <tr><th>Consent — SMS reminders</th><td><?= $p['consent_sms']?'✅':'❌' ?></td></tr>
    <tr><th>Consent — Email reminders</th><td><?= $p['consent_email']?'✅':'❌' ?></td></tr>
    <tr><th>Consent updated</th><td><?= e($p['consent_updated_at'] ?? '—') ?></td></tr>
  </table>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
