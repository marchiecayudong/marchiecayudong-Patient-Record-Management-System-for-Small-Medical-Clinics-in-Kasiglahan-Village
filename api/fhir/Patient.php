<?php
// FHIR R4 Patient resource endpoint
//   GET /api/fhir/Patient.php          -> Bundle (searchset) - only consenting patients
//   GET /api/fhir/Patient.php?id=123   -> Patient resource    - 403 if no consent
// Auth: Bearer JWT (admin/doctor only)
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

function patient_to_fhir($p) {
    $parts = preg_split('/\s+/', trim($p['name']));
    $family = array_pop($parts);
    $given  = $parts ?: [$family];
    $gender = strtolower($p['gender']);
    if (!in_array($gender, ['male','female','other','unknown'])) $gender = 'unknown';
    $birthYear = (int)date('Y') - (int)$p['age'];

    $res = [
        'resourceType' => 'Patient',
        'id'           => (string)$p['id'],
        'meta'         => ['profile' => ['http://hl7.org/fhir/StructureDefinition/Patient']],
        'identifier'   => [['system' => 'urn:patientsys:id', 'value' => (string)$p['id']]],
        'active' => true,
        'name'   => [['use' => 'official', 'text' => $p['name'], 'family' => $family, 'given' => $given]],
        'gender'    => $gender,
        'birthDate' => sprintf('%04d', $birthYear),
        'telecom'   => [],
        'extension' => [[
            'url' => 'urn:patientsys:consent',
            'valueBoolean' => (bool)$p['consent_share'],
        ]],
    ];
    if (!empty($p['contact'])) $res['telecom'][] = ['system' => 'phone', 'value' => $p['contact'], 'use' => 'mobile'];
    if (!empty($p['email']))   $res['telecom'][] = ['system' => 'email', 'value' => $p['email']];
    if (!empty($p['address'])) $res['address']   = [['text' => $p['address']]];
    return $res;
}

function consent_denied_outcome($pid) {
    return [
        'resourceType' => 'OperationOutcome',
        'issue' => [[
            'severity'    => 'error',
            'code'        => 'forbidden',
            'diagnostics' => "Patient $pid has not consented to external data sharing.",
        ]],
    ];
}

$id = $_GET['id'] ?? null;
if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([(int)$id]);
    $p = $stmt->fetch();
    if (!$p) {
        http_response_code(404);
        audit_log('fhir.patient.read', "Patient/$id", 404, null, $actor);
        echo json_encode([
            'resourceType' => 'OperationOutcome',
            'issue' => [['severity' => 'error', 'code' => 'not-found', 'diagnostics' => 'Patient not found']],
        ]); exit;
    }
    if (empty($p['consent_share'])) {
        http_response_code(403);
        audit_log('fhir.patient.read.denied_no_consent', "Patient/$id", 403, null, $actor);
        echo json_encode(consent_denied_outcome($id)); exit;
    }
    audit_log('fhir.patient.read', "Patient/$id", 200, null, $actor);
    echo json_encode(patient_to_fhir($p), JSON_PRETTY_PRINT);
    exit;
}

// Bundle - only patients who have consented
$rows = $pdo->query("SELECT * FROM patients WHERE consent_share = 1 ORDER BY id DESC")->fetchAll();
$entries = [];
$base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/api/fhir';
foreach ($rows as $p) {
    $entries[] = [
        'fullUrl'  => $base . '/Patient.php?id=' . $p['id'],
        'resource' => patient_to_fhir($p),
    ];
}
audit_log('fhir.patient.search', 'Patient', 200, ['returned' => count($entries)], $actor);
echo json_encode([
    'resourceType' => 'Bundle',
    'type'         => 'searchset',
    'total'        => count($entries),
    'entry'        => $entries,
], JSON_PRETTY_PRINT);
