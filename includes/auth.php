<?php
// includes/auth.php - session + auth + RBAC helpers
if (session_status() === PHP_SESSION_NONE) session_start();

function require_login() {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}
function current_user() { return $_SESSION['user'] ?? null; }
function current_role() { return $_SESSION['user']['role'] ?? null; }

/**
 * Role-based access. Pass any number of allowed roles.
 *   require_role('admin')                -> only admin
 *   require_role('admin','doctor')       -> admin OR doctor
 */
function require_role(...$roles) {
    require_login();
    $r = current_role();
    if (!in_array($r, $roles, true)) {
        http_response_code(403);
        echo "<div style='font-family:Arial;padding:40px;max-width:500px;margin:60px auto;background:#fff;border:1px solid #fecaca;border-radius:12px;color:#991b1b'>";
        echo "<h2 style='margin-top:0'>403 — Forbidden</h2>";
        echo "<p>Your role (<strong>" . htmlspecialchars((string)$r) . "</strong>) does not have access to this page.</p>";
        echo "<p>Required: <strong>" . htmlspecialchars(implode(', ', $roles)) . "</strong></p>";
        echo "<p><a href='" . BASE_URL . "/index.php'>← Back to dashboard</a></p>";
        echo "</div>";
        exit;
    }
}

function role_can($action) {
    // Capability matrix
    $r = current_role();
    $caps = [
        'admin'  => ['view_patient','edit_patient','delete_patient','view_appointment','edit_appointment','delete_appointment','manage_users','view_audit','view_reports','generate_qr','view_consent','edit_consent'],
        'doctor' => ['view_patient','edit_patient','view_appointment','edit_appointment','view_reports','generate_qr','view_consent'],
        'nurse'  => ['view_patient','view_appointment','edit_appointment','generate_qr','view_consent'],
    ];
    return in_array($action, $caps[$r] ?? [], true);
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
