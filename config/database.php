<?php
// config/database.php - MySQL connection (PDO)
date_default_timezone_set('Asia/Manila');

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'patientsys');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('BASE_URL', '/patientsys');

define('JWT_SECRET', 'change-this-super-secret-key-please-2026');
define('JWT_ISSUER', 'patientsys');
define('JWT_TTL', 3600);

define('QR_TOKEN_TTL', 600);

define('API_RATE_LIMIT_MAX',     60);
define('API_RATE_LIMIT_WINDOW',  60);

// --- Notification providers ---
define('SMS_PROVIDER',  'log');                 // 'semaphore' | 'log'
define('SEMAPHORE_API_KEY', '');
define('SEMAPHORE_SENDER',  'PatientSys');

define('MAIL_FROM',      'no-reply@patientsys.local');
define('MAIL_FROM_NAME', 'PatientSys Clinic');

// --- Clinic / admin contact (used as fallback recipient + BCC for reminders) ---
define('ADMIN_CONTACT_PHONE', '09777721173');
define('ADMIN_CONTACT_EMAIL', 'marchiecayudong@gmail.com');
define('ADMIN_BCC_REMINDERS', true); // BCC admin email on every reminder
define('CLINIC_NAME', 'Kasiglahan Village Medical Clinic');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

