<?php
$pageTitle = 'Users';
$active = 'users';
require_once __DIR__ . '/../includes/header.php';

$err = $msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full = trim($_POST['full_name']); $un = trim($_POST['username']);
    $em = trim($_POST['email']); $pw = $_POST['password']; $role = $_POST['role'] ?? 'staff';
    if (!$full || !$un || !$em || !$pw) { $err = 'All fields are required.'; }
    else {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (full_name,username,email,password,role) VALUES (?,?,?,?,?)");
            $stmt->execute([$full,$un,$em, password_hash($pw, PASSWORD_DEFAULT), $role]);
            $msg = 'User created.';
        } catch (Exception $e) { $err = 'Username or email already exists.'; }
    }
}
$rows = $pdo->query("SELECT id,full_name,username,email,role,created_at FROM users ORDER BY id")->fetchAll();
?>
<div class="grid-2">
  <div class="panel">
    <h2 style="margin-bottom:14px">All Users</h2>
    <table>
      <thead><tr><th>ID</th><th>Name</th><th>Username</th><th>Email</th><th>Role</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr><td>#<?= $r['id'] ?></td><td><?= e($r['full_name']) ?></td><td><?= e($r['username']) ?></td><td><?= e($r['email']) ?></td><td><?= e($r['role']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="panel">
    <h2 style="margin-bottom:14px">Add User</h2>
    <?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>
    <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
    <form method="post" class="form">
      <div><label>Full Name</label><input name="full_name" required></div>
      <div><label>Username</label><input name="username" required></div>
      <div><label>Email</label><input type="email" name="email" required></div>
      <div><label>Password</label><input type="password" name="password" required></div>
      <div><label>Role</label><select name="role"><option value="staff">Staff</option><option value="admin">Admin</option></select></div>
      <button class="btn btn-primary">Create User</button>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
