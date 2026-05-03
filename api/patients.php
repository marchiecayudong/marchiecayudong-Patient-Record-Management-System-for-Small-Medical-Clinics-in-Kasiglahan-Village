<?php
// Legacy REST list endpoint - JWT required, consent enforced, rate limited, audited
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/jwt.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/rate_limit.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
rate_limit('rest');
$jwt = require_jwt_role('admin','doctor','nurse');
$actor = jwt_actor($jwt);

$rows = $pdo->query("SELECT id,name,age,gender,contact,email,date_added,consent_share FROM patients ORDER BY id DESC")->fetchAll();
// Hide PII (contact/email) for non-consenting patients to non-admins
foreach ($rows as &$r) {
    if (!$r['consent_share'] && ($jwt['role'] ?? '') !== 'admin') {
        $r['contact'] = '***';
        $r['email']   = null;
    }
}
audit_log('rest.patients.list', 'patients', 200, ['count' => count($rows)], $actor);
echo json_encode($rows, JSON_PRETTY_PRINT);
