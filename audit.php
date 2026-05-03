<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/audit.php';
require_login();
require_role('admin');

$action_q   = trim($_GET['action'] ?? '');
$endpoint_q = trim($_GET['endpoint'] ?? '');
$consent_q  = $_GET['consent'] ?? '';        // sms | email | share | ''
$patient_q  = trim($_GET['patient'] ?? '');  // patient name OR id
$from_q     = $_GET['from'] ?? '';
$to_q       = $_GET['to'] ?? '';

$where = []; $params = [];

if ($action_q !== '')   { $where[] = 'a.action LIKE ?';   $params[] = "%$action_q%"; }
if ($endpoint_q !== '') { $where[] = '(a.action LIKE ? OR a.resource LIKE ?)'; $params[]="%$endpoint_q%"; $params[]="%$endpoint_q%"; }
if ($consent_q === 'sms')   { $where[] = '(a.action LIKE "%sms%" OR a.details LIKE "%consent_sms%" OR a.details LIKE "%\"sms\"%")'; }
if ($consent_q === 'email') { $where[] = '(a.action LIKE "%email%" OR a.details LIKE "%consent_email%" OR a.details LIKE "%\"email\"%")'; }
if ($consent_q === 'share') { $where[] = '(a.action LIKE "%fhir%" OR a.action LIKE "%share%" OR a.details LIKE "%consent_share%")'; }
if ($patient_q !== '') {
    if (ctype_digit($patient_q)) {
        $where[] = '(a.resource LIKE ? OR a.details LIKE ?)';
        $params[] = "%/$patient_q"; $params[] = "%\"patient_id\":$patient_q%";
    } else {
        $where[] = 'a.actor_name LIKE ?'; $params[] = "%$patient_q%";
    }
}
if ($from_q !== '') { $where[] = 'a.ts >= ?'; $params[] = $from_q . ' 00:00:00'; }
if ($to_q   !== '') { $where[] = 'a.ts <= ?'; $params[] = $to_q   . ' 23:59:59'; }

$sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// CSV export branch
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit-logs-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Timestamp (PHT)','Actor Type','Actor ID','Actor Name','Action','Resource','IP','User Agent','Status','Details']);
    $stmt = $pdo->prepare("SELECT a.* FROM audit_logs a $sqlWhere ORDER BY a.id DESC LIMIT 50000");
    $stmt->execute($params);
    while ($l = $stmt->fetch()) {
        fputcsv($out, [
            $l['ts'], $l['actor_type'], $l['actor_id'], $l['actor_name'],
            $l['action'], $l['resource'], $l['ip'], $l['user_agent'],
            $l['status_code'], $l['details'],
        ]);
    }
    fclose($out);
    // log the export itself (after stream)
    audit_log('audit.export.csv', null, 200, [
        'filters' => compact('action_q','endpoint_q','consent_q','patient_q','from_q','to_q')
    ]);
    exit;
}

$stmt = $pdo->prepare("SELECT a.* FROM audit_logs a $sqlWhere ORDER BY a.id DESC LIMIT 300");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$qs = http_build_query(array_filter([
    'action'=>$action_q, 'endpoint'=>$endpoint_q, 'consent'=>$consent_q,
    'patient'=>$patient_q, 'from'=>$from_q, 'to'=>$to_q,
]));
$pageTitle = 'Audit Log';
$active = 'audit';
require_once __DIR__ . '/includes/header.php';
?>
<div class="panel">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
    <h2 style="margin:0">🛡 Audit Log <small style="color:#6b7280;font-weight:400">(latest 300, filterable)</small></h2>
    <a class="btn btn-primary" href="?<?= e($qs) ?>&export=csv">⬇ Export CSV</a>
  </div>

  <form method="get" class="filter-row">
    <div class="field"><label>From</label><input type="date" name="from" value="<?= e($from_q) ?>"></div>
    <div class="field"><label>To</label><input type="date" name="to" value="<?= e($to_q) ?>"></div>
    <div class="field"><label>Patient (name or ID)</label><input name="patient" value="<?= e($patient_q) ?>" placeholder="e.g. 42 or Juan"></div>
    <div class="field"><label>Consent type</label>
      <select name="consent">
        <option value="">All</option>
        <option value="sms"   <?= $consent_q==='sms'?'selected':'' ?>>SMS</option>
        <option value="email" <?= $consent_q==='email'?'selected':'' ?>>Email</option>
        <option value="share" <?= $consent_q==='share'?'selected':'' ?>>Share / FHIR</option>
      </select>
    </div>
    <div class="field"><label>API endpoint</label><input name="endpoint" value="<?= e($endpoint_q) ?>" placeholder="fhir / book / qr"></div>
    <div class="field"><label>Action</label><input name="action" value="<?= e($action_q) ?>" placeholder="appointment.reminder"></div>
    <div class="field"><label>&nbsp;</label><button class="btn btn-primary">Filter</button></div>
    <div class="field"><label>&nbsp;</label><a class="btn btn-outline" href="<?= BASE_URL ?>/audit.php">Reset</a></div>
  </form>

  <table class="table">
    <thead><tr><th>When (PHT)</th><th>Actor</th><th>Action</th><th>Resource</th><th>IP</th><th>Status</th><th>Details</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $l): ?>
      <tr>
        <td style="font-family:monospace;font-size:12px"><?= e($l['ts']) ?></td>
        <td><?= e($l['actor_type']) ?><?= $l['actor_name'] ? ' — '.e($l['actor_name']) : '' ?></td>
        <td><code><?= e($l['action']) ?></code></td>
        <td><?= e($l['resource'] ?? '') ?></td>
        <td style="font-size:12px"><?= e($l['ip']) ?></td>
        <td><?= e($l['status_code']) ?></td>
        <td style="font-size:12px;max-width:280px;word-break:break-word"><?= e($l['details'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$logs): ?><tr><td colspan="7" style="color:#6b7280;text-align:center;padding:20px">No audit records match.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
