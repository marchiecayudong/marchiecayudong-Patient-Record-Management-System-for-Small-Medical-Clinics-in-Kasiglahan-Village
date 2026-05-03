<?php
// includes/rate_limit.php - simple sliding-window rate limit per IP+endpoint group
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/audit.php';

/**
 * Enforce rate limit. On limit exceed -> 429 + JSON + audit log + exit.
 *  $group : endpoint group identifier (e.g. 'fhir', 'auth', 'qr')
 */
function rate_limit($group = 'api', $max = null, $window = null) {
    global $pdo;
    $max    = $max    ?? API_RATE_LIMIT_MAX;
    $window = $window ?? API_RATE_LIMIT_WINDOW;

    $ip  = client_ip();
    $key = substr($ip . ':' . $group, 0, 120);
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $nowStr = $now->format('Y-m-d H:i:s');

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT id, hits, window_start FROM api_rate_limits WHERE api_key = ? FOR UPDATE");
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        if (!$row) {
            $pdo->prepare("INSERT INTO api_rate_limits (api_key, hits, window_start) VALUES (?, 1, ?)")
                ->execute([$key, $nowStr]);
            $pdo->commit();
            header("X-RateLimit-Limit: $max");
            header("X-RateLimit-Remaining: " . ($max - 1));
            return;
        }

        $start = strtotime($row['window_start']);
        if ((time() - $start) > $window) {
            $pdo->prepare("UPDATE api_rate_limits SET hits = 1, window_start = ? WHERE id = ?")
                ->execute([$nowStr, $row['id']]);
            $pdo->commit();
            header("X-RateLimit-Limit: $max");
            header("X-RateLimit-Remaining: " . ($max - 1));
            return;
        }

        if ((int)$row['hits'] >= $max) {
            $pdo->commit();
            $retry = max(1, $window - (time() - $start));
            header("X-RateLimit-Limit: $max");
            header("X-RateLimit-Remaining: 0");
            header("Retry-After: $retry");
            http_response_code(429);
            header('Content-Type: application/json');
            audit_log('api.rate_limited', $group, 429, ['ip' => $ip, 'hits' => $row['hits']]);
            echo json_encode(['error' => 'rate_limited', 'message' => "Too many requests. Try again in {$retry}s."]);
            exit;
        }

        $pdo->prepare("UPDATE api_rate_limits SET hits = hits + 1 WHERE id = ?")->execute([$row['id']]);
        $pdo->commit();
        header("X-RateLimit-Limit: $max");
        header("X-RateLimit-Remaining: " . ($max - 1 - (int)$row['hits']));
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // fail-open (do not block clinic on infra error), but audit it
        audit_log('api.rate_limit_error', $group, 500, ['err' => $e->getMessage()]);
    }
}
