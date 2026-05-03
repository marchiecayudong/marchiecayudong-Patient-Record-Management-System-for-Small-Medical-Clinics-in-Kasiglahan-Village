<?php
// FHIR R4 Appointment resource endpoint - consent + RBAC enforced
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/jwt.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/rate_limit.php';

header('Content-Type: application/fhir+json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

rate_limit('fhir');
$jwt = require_jwt_role('admin', 'doctor');
$actor = jwt_actor($jwt);

function map_status($s) {
    return match (strtolower($s)) {
        'confirmed' => 'booked',
        'pending'   => 'pending',
        'cancelled' => 'cancelled',
        'completed' => 'fulfilled',
        default     => 'proposed',
    };
}
function appt_to_fhir($a) {
    $start = $a['appt_date'] . 'T' . $a['appt_time'] . '+08:00';
    return [
        'resourceType' => 'Appointment',
        'id'           => (string)$a['id'],
        'status'       => map_status($a['status']),
        'description'  => $a['purpose'],
        'start'        => $start,
        'participant'  => [['actor' => ['reference' => 'Patient/' . $a['patient_id']], 'status' => 'accepted']],
    ];
}
function patient_consents($pdo, $pid) {
    $s = $pdo->prepare("SELECT consent_share FROM patients WHERE id = ?");
    $s->execute([(int)$pid]);
    $r = $s->fetch();
    return $r && (int)$r['consent_share'] === 1;
}

if (!empty($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id=?");
    $stmt->execute([(int)$_GET['id']]);
    $a = $stmt->fetch();
    if (!$a) {
        http_response_code(404);
        audit_log('fhir.appointment.read', "Appointment/{$_GET['id']}", 404, null, $actor);
        echo json_encode(['resourceType'=>'OperationOutcome','issue'=>[['severity'=>'error','code'=>'not-found']]]); exit;
    }
    if (!patient_consents($pdo, $a['patient_id'])) {
        http_response_code(403);
        audit_log('fhir.appointment.read.denied_no_consent', "Appointment/{$a['id']}", 403, null, $actor);
        echo json_encode(['resourceType'=>'OperationOutcome','issue'=>[['severity'=>'error','code'=>'forbidden','diagnostics'=>'Patient consent not given']]]); exit;
    }
    audit_log('fhir.appointment.read', "Appointment/{$a['id']}", 200, null, $actor);
    echo json_encode(appt_to_fhir($a), JSON_PRETTY_PRINT);
    exit;
}

$where = 'WHERE p.consent_share = 1'; $params = [];
if (!empty($_GET['patient'])) { $where .= ' AND a.patient_id = ?'; $params[] = (int)$_GET['patient']; }
$stmt = $pdo->prepare("SELECT a.* FROM appointments a JOIN patients p ON p.id = a.patient_id $where ORDER BY a.appt_date DESC, a.appt_time DESC");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$entries = [];
$base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/api/fhir';
foreach ($rows as $a) {
    $entries[] = ['fullUrl' => $base . '/Appointment.php?id=' . $a['id'], 'resource' => appt_to_fhir($a)];
}
audit_log('fhir.appointment.search', 'Appointment', 200, ['returned' => count($entries)], $actor);
echo json_encode([
    'resourceType' => 'Bundle',
    'type'         => 'searchset',
    'total'        => count($entries),
    'entry'        => $entries,
], JSON_PRETTY_PRINT);
