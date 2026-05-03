<?php
// QR generator - now uses one-time, short-lived tokens
$pageTitle = 'QR Code';
$active = 'qr';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/jwt.php';
require_once __DIR__ . '/../includes/qr_token.php';
require_once __DIR__ . '/../includes/audit.php';

require_login();
if (!role_can('generate_qr')) { echo "<div class='panel'>Access denied.</div>"; require __DIR__ . '/../includes/footer.php'; exit; }

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id=?");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) { echo "<div class='panel'>Patient not found.</div>"; require __DIR__ . '/../includes/footer.php'; exit; }

// Issue a one-time token (10 min)
$tok = qr_token_issue($p['id'], (int)$_SESSION['user_id']);
audit_log('qr.token.issued.ui', "patient/{$p['id']}", 200, ['one_time'=>true]);
$proto = isset($_SERVER['HTTPS']) ? 'https' : 'http';
$scanUrl = $proto . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/qr/scan.php?t=' . $tok['token'];
$qrSrc   = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . urlencode($scanUrl);

$apiToken = jwt_encode(['sub' => (int)$_SESSION['user_id'], 'role' => current_role(), 'name' => current_user()['full_name'] ?? ''], 900);
$apiUrl   = $proto . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/api/patient.php?id=' . $p['id'];
?>
<div class="qr-box">
  <h2>QR Code for <?= e($p['name']) ?></h2>
  <img src="<?= e($qrSrc) ?>" alt="QR Code">
  <p style="color:#dc2626;font-size:13px;font-weight:600">⚠ Single-use only. Once scanned, this QR cannot be reused — generate a new one if needed.</p>
  <p style="color:#6b7280;font-size:11px;word-break:break-all"><?= e($scanUrl) ?></p>
  <a class="btn btn-primary" download="patient-<?= $p['id'] ?>.png" href="<?= e($qrSrc) ?>">Download QR</a>
  <a class="btn" href="<?= BASE_URL ?>/qr/generate.php?id=<?= $p['id'] ?>">↻ Regenerate</a>

  <details style="margin-top:18px;text-align:left">
    <summary style="cursor:pointer;color:#374151">Developer: FHIR / REST API access</summary>
    <p style="font-size:13px;margin-top:8px"><strong>REST:</strong> <code><?= e($apiUrl) ?></code></p>
    <p style="font-size:13px"><strong>FHIR:</strong> <code><?= e($proto.'://'.$_SERVER['HTTP_HOST'].BASE_URL.'/api/fhir/Patient.php?id='.$p['id']) ?></code></p>
    <p style="font-size:12px;color:#6b7280">Send header <code>Authorization: Bearer &lt;JWT&gt;</code>. Demo token (15 min, role=<?= e(current_role()) ?>):</p>
    <textarea style="width:100%;height:70px;font-family:monospace;font-size:11px" readonly><?= e($apiToken) ?></textarea>
  </details>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
