<?php
// POST /api/qr_issue.php  { "patient_id": 1 } -> { token, url, expires_at }
// Issues a one-time, short-lived QR token. Staff JWT required.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/jwt.php';
require_once __DIR__ . '/../includes/qr_token.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/rate_limit.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
rate_limit('qr');
$jwt = require_jwt_role('admin','doctor','nurse');
$actor = jwt_actor($jwt);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }

$body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$pid = (int)($body['patient_id'] ?? 0);
if ($pid <= 0) { http_response_code(400); echo json_encode(['error'=>'patient_id required']); exit; }

$exists = $pdo->prepare("SELECT id FROM patients WHERE id=?");
$exists->execute([$pid]);
if (!$exists->fetch()) { http_response_code(404); echo json_encode(['error'=>'patient not found']); exit; }

$t = qr_token_issue($pid, $jwt['sub'] ?? null);
$proto = isset($_SERVER['HTTPS']) ? 'https' : 'http';
$url = $proto . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/qr/scan.php?t=' . $t['token'];
audit_log('qr.token.issued', "patient/$pid", 200, ['one_time' => true], $actor);

echo json_encode([
    'token'    => $t['token'],
    'url'      => $url,
    'one_time' => true,
    'expires'  => 'never (single-use only)',
]);
