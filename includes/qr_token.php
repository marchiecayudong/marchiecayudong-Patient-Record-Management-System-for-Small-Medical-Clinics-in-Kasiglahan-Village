<?php
// includes/qr_token.php - SINGLE-USE QR tokens (no time limit)
require_once __DIR__ . '/../config/database.php';

function qr_token_issue($patient_id, $issued_by = null) {
    global $pdo;
    $token = bin2hex(random_bytes(32)); // 64 hex chars
    // expires_at kept in schema for compatibility; set far-future sentinel
    $exp = '2099-12-31 23:59:59';
    $pdo->prepare(
        "INSERT INTO qr_tokens (token, patient_id, issued_by, expires_at) VALUES (?, ?, ?, ?)"
    )->execute([$token, $patient_id, $issued_by, $exp]);
    return ['token' => $token, 'expires_at' => null];
}

/**
 * Single-use consume. No expiration check.
 * Returns ['ok'=>bool,'reason'=>?,'patient_id'=>?]
 */
function qr_token_consume($token, $ip = null) {
    global $pdo;
    if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return ['ok' => false, 'reason' => 'invalid_format'];
    }
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT * FROM qr_tokens WHERE token = ? FOR UPDATE");
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        if (!$row) { $pdo->commit(); return ['ok' => false, 'reason' => 'not_found']; }
        if ($row['used_at'] !== null) {
            $pdo->commit();
            return ['ok' => false, 'reason' => 'already_used', 'patient_id' => (int)$row['patient_id']];
        }
        $pdo->prepare("UPDATE qr_tokens SET used_at = NOW(), used_by_ip = ? WHERE id = ?")
            ->execute([substr($ip ?? '', 0, 64), $row['id']]);
        $pdo->commit();
        return ['ok' => true, 'patient_id' => (int)$row['patient_id']];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'reason' => 'error'];
    }
}
