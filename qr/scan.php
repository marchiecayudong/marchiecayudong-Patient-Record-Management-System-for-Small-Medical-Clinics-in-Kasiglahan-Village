<?php
// Scan landing page - validates one-time token, then renders record (requires staff login)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/qr_token.php';
require_once __DIR__ . '/../includes/audit.php';
require_login();

$pageTitle = 'Scanned Patient Record';
$active = 'qr';

$token = $_GET['t'] ?? '';
$legacyId = (int)($_GET['id'] ?? 0); // legacy fallback (won't be issued anymore)
$pid = 0; $error = null;

if ($token) {
    $r = qr_token_consume($token, $_SERVER['REMOTE_ADDR'] ?? '');
    if (!$r['ok']) {
        $error = match ($r['reason']) {
            'already_used' => 'This QR code has already been used. QR tokens are single-use only — ask staff to generate a new one.',
            'not_found'    => 'Invalid QR token.',
            default        => 'Invalid QR code.',
        };
        audit_log('qr.token.rejected', 'token/'.substr($token,0,8).'...', 400, ['reason'=>$r['reason']]);
    } else {
        $pid = $r['patient_id'];
        audit_log('qr.token.consumed', "patient/$pid", 200);
    }
} elseif ($legacyId > 0) {
    $error = 'This system now requires single-use QR tokens. Ask staff to generate a fresh QR.';
}

require_once __DIR__ . '/../includes/header.php';

if ($error || $pid <= 0):
?>
  <div class="panel" style="max-width:560px;border-left:4px solid #dc2626">
    <h2 style="margin-top:0">⚠ QR Access Denied</h2>
    <p style="color:#991b1b"><?= e($error ?? 'Missing token') ?></p>
    <a class="btn" href="<?= BASE_URL ?>/qr/scanner.php">Back to Scanner</a>
  </div>
<?php
  require_once __DIR__ . '/../includes/footer.php'; exit;
endif;

$stmt = $pdo->prepare("SELECT * FROM patients WHERE id=?");
$stmt->execute([$pid]);
$p = $stmt->fetch();
if (!$p) { echo "<div class='panel'>Patient not found.</div>"; require __DIR__ . '/../includes/footer.php'; exit; }

$a = $pdo->prepare("SELECT * FROM appointments WHERE patient_id=? ORDER BY appt_date DESC, appt_time DESC LIMIT 10");
$a->execute([$pid]);
$appts = $a->fetchAll();
?>
<div class="panel" style="max-width:720px">
  <h2 style="margin-top:0">📇 <?= e($p['name']) ?></h2>
  <p style="color:#6b7280">Quick access via single-use QR • <?= date('M d, Y h:i A') ?> (PHT) • Token consumed</p>
  <table class="table">
    <tr><th>Patient ID</th><td>#<?= (int)$p['id'] ?></td></tr>
    <tr><th>Age / Gender</th><td><?= (int)$p['age'] ?> • <?= e($p['gender']) ?></td></tr>
    <tr><th>Contact</th><td><?= e($p['contact']) ?></td></tr>
    <?php if (!empty($p['email'])): ?><tr><th>Email</th><td><?= e($p['email']) ?></td></tr><?php endif; ?>
    <?php if (!empty($p['address'])): ?><tr><th>Address</th><td><?= e($p['address']) ?></td></tr><?php endif; ?>
    <tr><th>Consent (sharing/SMS/email)</th><td><?= $p['consent_share']?'✅':'❌' ?> / <?= $p['consent_sms']?'✅':'❌' ?> / <?= $p['consent_email']?'✅':'❌' ?></td></tr>
    <tr><th>Registered</th><td><?= e($p['date_added']) ?></td></tr>
  </table>

  <h3 style="margin-top:22px">Recent Appointments</h3>
  <?php if (!$appts): ?>
    <p style="color:#6b7280">No appointments yet.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Date</th><th>Time</th><th>Purpose</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($appts as $ap): ?>
        <tr>
          <td><?= e($ap['appt_date']) ?></td>
          <td><?= e(date('h:i A', strtotime($ap['appt_time']))) ?></td>
          <td><?= e($ap['purpose']) ?></td>
          <td><?= e($ap['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <div style="margin-top:18px;display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn btn-primary" href="<?= BASE_URL ?>/patients/view.php?id=<?= $p['id'] ?>">Open Full Record</a>
    <a class="btn" href="<?= BASE_URL ?>/qr/scanner.php">Scan Another</a>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
