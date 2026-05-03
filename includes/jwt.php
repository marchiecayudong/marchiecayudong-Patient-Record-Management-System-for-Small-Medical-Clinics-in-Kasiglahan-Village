<?php
// includes/jwt.php - Lightweight HS256 JWT (no external deps) + role helper
require_once __DIR__ . '/../config/database.php';

function b64url_encode($data) { return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); }
function b64url_decode($data) {
    $pad = strlen($data) % 4;
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    return base64_decode(strtr($data, '-_', '+/'));
}

function jwt_encode(array $payload, $ttl = null) {
    $ttl = $ttl ?? JWT_TTL;
    $header = ['typ' => 'JWT', 'alg' => 'HS256'];
    $now = time();
    $payload = array_merge([
        'iss' => JWT_ISSUER, 'iat' => $now, 'exp' => $now + $ttl,
    ], $payload);
    $h = b64url_encode(json_encode($header));
    $p = b64url_encode(json_encode($payload));
    $sig = hash_hmac('sha256', "$h.$p", JWT_SECRET, true);
    return "$h.$p." . b64url_encode($sig);
}

function jwt_decode($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$h, $p, $s] = $parts;
    $expected = b64url_encode(hash_hmac('sha256', "$h.$p", JWT_SECRET, true));
    if (!hash_equals($expected, $s)) return null;
    $payload = json_decode(b64url_decode($p), true);
    if (!$payload) return null;
    if (isset($payload['exp']) && time() > $payload['exp']) return null;
    return $payload;
}

function jwt_get_bearer() {
    $hdr = '';
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) $hdr = $_SERVER['HTTP_AUTHORIZATION'];
    elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $hdr = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    elseif (function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0) { $hdr = $v; break; }
        }
    }
    if (stripos($hdr, 'Bearer ') === 0) return trim(substr($hdr, 7));
    if (!empty($_GET['token'])) return $_GET['token'];
    return null;
}

function require_jwt() {
    $token = jwt_get_bearer();
    $payload = $token ? jwt_decode($token) : null;
    if (!$payload) {
        http_response_code(401);
        header('Content-Type: application/json');
        if (function_exists('audit_log')) audit_log('api.auth_failed', null, 401);
        echo json_encode(['error' => 'Unauthorized', 'message' => 'Valid Bearer JWT required']);
        exit;
    }
    return $payload;
}

/**
 * Require JWT AND that the token's role is in the allowed list.
 *   require_jwt_role('admin','doctor')
 */
function require_jwt_role(...$roles) {
    $p = require_jwt();
    if (!in_array($p['role'] ?? '', $roles, true)) {
        http_response_code(403);
        header('Content-Type: application/json');
        if (function_exists('audit_log')) audit_log('api.forbidden', null, 403, [
            'role' => $p['role'] ?? null, 'required' => $roles,
        ], ['source' => 'api', 'id' => $p['sub'] ?? null, 'name' => $p['name'] ?? null]);
        echo json_encode(['error' => 'Forbidden', 'message' => 'Role not permitted', 'required' => $roles]);
        exit;
    }
    return $p;
}

function jwt_actor($p) {
    return ['source' => 'api', 'id' => $p['sub'] ?? null, 'name' => $p['name'] ?? null];
}
