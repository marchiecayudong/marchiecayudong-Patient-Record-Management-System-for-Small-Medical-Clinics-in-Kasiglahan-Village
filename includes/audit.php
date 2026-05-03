<?php
// includes/audit.php - centralized audit logging
require_once __DIR__ . '/../config/database.php';

function client_ip() {
    foreach (['HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            return substr($ip, 0, 64);
        }
    }
    return 'unknown';
}

/**
 * audit_log('fhir.patient.read', 'Patient/123', 200, ['note' => '...'])
 */
function audit_log($action, $resource = null, $status = null, $details = null, $actor = null) {
    global $pdo;
    try {
        $a_type = 'anonymous'; $a_id = null; $a_name = null;
        if ($actor && isset($actor['source'])) {
            $a_type = $actor['source']; // 'user' | 'api'
            $a_id   = $actor['id']   ?? null;
            $a_name = $actor['name'] ?? null;
        } elseif (!empty($_SESSION['user_id'])) {
            $a_type = 'user';
            $a_id   = (int)$_SESSION['user_id'];
            $a_name = $_SESSION['user']['full_name'] ?? null;
        }
        $stmt = $pdo->prepare(
            "INSERT INTO audit_logs (actor_type, actor_id, actor_name, action, resource, ip, user_agent, status_code, details)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $a_type, $a_id, $a_name, $action, $resource,
            client_ip(),
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            $status,
            $details ? (is_string($details) ? $details : json_encode($details)) : null,
        ]);
    } catch (Throwable $e) { /* never break the request */ }
}
