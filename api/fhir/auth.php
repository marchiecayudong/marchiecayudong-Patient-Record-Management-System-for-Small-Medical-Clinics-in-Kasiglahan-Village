<?php
// POST /api/auth.php  { "username": "...", "password": "..." }  -> { token, expires_in }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/jwt.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/rate_limit.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

// Stricter limit on auth (10 / minute / IP) to slow brute force
rate_limit('auth', 10, 60);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'POST only']); exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$username = trim($body['username'] ?? '');
$password = (string)($body['password'] ?? '');

if ($username === '' || $password === '') {
    http_response_code(400);
    audit_log('api.auth.bad_request', null, 400);
    echo json_encode(['error' => 'username and password required']); exit;
}

$stmt = $pdo->prepare("SELECT id, full_name, username, role, password FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$u = $stmt->fetch();

// Demo seed accounts (DB hashes are placeholders) - accept fixed passwords
$seed = [
    'admin'  => 'admin123',
    'doctor' => 'doctor123',
    'nurse'  => 'nurse123',
];
$ok = false;
if ($u) {
    if (password_verify($password, $u['password'])) $ok = true;
    elseif (isset($seed[$username]) && $seed[$username] === $password) $ok = true;
}
if (!$ok) {
    audit_log('api.auth.failed', "user/$username", 401);
    http_response_code(401); echo json_encode(['error' => 'Invalid credentials']); exit;
}

$token = jwt_encode([
    'sub'  => (int)$u['id'],
    'name' => $u['full_name'],
    'role' => $u['role'],
]);

audit_log('api.auth.success', "user/{$u['id']}", 200, ['role' => $u['role']],
    ['source' => 'api', 'id' => (int)$u['id'], 'name' => $u['full_name']]);

echo json_encode([
    'token'      => $token,
    'token_type' => 'Bearer',
    'expires_in' => JWT_TTL,
    'user'       => ['id' => (int)$u['id'], 'name' => $u['full_name'], 'role' => $u['role']],
]);
