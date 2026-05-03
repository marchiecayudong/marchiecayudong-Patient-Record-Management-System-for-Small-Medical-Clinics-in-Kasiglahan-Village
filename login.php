<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/audit.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    // Seed-account fallback (DB hashes are placeholders for the demo)
    $seed = ['admin' => 'admin123', 'doctor' => 'doctor123', 'nurse' => 'nurse123'];
    $ok = false;
    if ($user) {
        if (password_verify($password, $user['password'])) $ok = true;
        elseif (isset($seed[$user['username']]) && $seed[$user['username']] === $password) $ok = true;
    }
    if ($ok) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user']    = $user;
        audit_log('ui.login.success', "user/{$user['id']}", 200, ['role'=>$user['role']]);
        header('Location: ' . BASE_URL . '/index.php'); exit;
    } else {
        audit_log('ui.login.failed', "user/$username", 401);
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login • PatientSys</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="login-body">
<div class="login-wrap"><div class="login-card">
  <h1>PatientSys</h1>
  <p class="sub">Patient Record Manager System</p>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="form">
    <div><label>Username or Email</label><input name="username" required autofocus></div>
    <div><label>Password</label><input type="password" name="password" required></div>
    <button class="btn btn-primary" type="submit">Sign in</button>
    <p style="font-size:12px;color:#6b7280;text-align:center;line-height:1.6">

      Patients can <a href="<?= BASE_URL ?>/book.php">book online here →</a>
    </p>
  </form>
</div></div>
</body></html>
