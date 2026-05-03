<?php
// GET /api/patient.php?id=N  - JWT required, consent enforced
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/jwt.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/rate_limit.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
rate_limit('rest');
$jwt = require_jwt_role('admin','doctor','nurse');
$actor = jwt_actor($jwt);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id=?");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) {
    http_response_code(404);
    audit_log('rest.patient.read', "patient/$id", 404, null, $actor);
    echo json_encode(['error' => 'Not found']); exit;
}
if (!$p['consent_share'] && ($jwt['role'] ?? '') !== 'admin') {
    http_response_code(403);
    audit_log('rest.patient.read.denied_no_consent', "patient/$id", 403, null, $actor);
    echo json_encode(['error' => 'forbidden', 'message' => 'Patient has not consented to data sharing.']);
    exit;
}
audit_log('rest.patient.read', "patient/$id", 200, null, $actor);
echo json_encode($p, JSON_PRETTY_PRINT);
