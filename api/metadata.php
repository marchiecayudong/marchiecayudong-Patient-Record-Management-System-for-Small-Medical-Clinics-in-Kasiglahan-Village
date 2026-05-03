<?php
// FHIR CapabilityStatement
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/rate_limit.php';
header('Content-Type: application/fhir+json');
header('Access-Control-Allow-Origin: *');
rate_limit('fhir');

$base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . BASE_URL;

echo json_encode([
    'resourceType' => 'CapabilityStatement',
    'status'       => 'active',
    'date'         => date('c'),
    'publisher'    => 'PatientSys',
    'kind'         => 'instance',
    'software'     => ['name' => 'PatientSys', 'version' => '3.0.0'],
    'implementation' => ['description' => 'PatientSys FHIR R4 API (consent + RBAC + rate limit + audit)', 'url' => $base . '/api/fhir'],
    'fhirVersion'  => '4.0.1',
    'format'       => ['application/fhir+json'],
    'rest' => [[
        'mode' => 'server',
        'security' => [
            'cors' => true,
            'service' => [['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/restful-security-service', 'code' => 'OAuth']]]],
            'description' => 'Bearer JWT (POST /api/auth.php). Roles: admin, doctor (read FHIR). Patient consent_share required.',
        ],
        'resource' => [
            ['type' => 'Patient',     'interaction' => [['code' => 'read'], ['code' => 'search-type']]],
            ['type' => 'Appointment', 'interaction' => [['code' => 'read'], ['code' => 'search-type']], 'searchParam' => [['name' => 'patient', 'type' => 'reference']]],
        ],
    ]],
], JSON_PRETTY_PRINT);
