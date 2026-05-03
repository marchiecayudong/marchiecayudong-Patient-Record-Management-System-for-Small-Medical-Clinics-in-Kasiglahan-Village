<?php
$pageTitle = 'Patients • PatientSys';
$active = 'patients';
require_once __DIR__ . '/../includes/header.php';

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE name LIKE ? OR contact LIKE ? ORDER BY id DESC");
    $stmt->execute(["%$q%", "%$q%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM patients ORDER BY id DESC");
}
$rows = $stmt->fetchAll();
?>
<div class="panel">
  <div class="panel-head">
    <h2>Patients</h2>
    <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/patients/add.php">+ Add Patient</a>
  </div>
  <form method="get" style="margin-bottom:14px">
    <input name="q" value="<?= e($q) ?>" placeholder="Search by name or contact..." style="padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;width:280px">
  </form>
  <table>
    <thead><tr><th>ID</th><th>Name</th><th>Age</th><th>Gender</th><th>Contact</th><th>Date Added</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td>#<?= e($r['id']) ?></td>
        <td><?= e($r['name']) ?></td>
        <td><?= e($r['age']) ?></td>
        <td><?= e($r['gender']) ?></td>
        <td><?= e($r['contact']) ?></td>
        <td><?= e(date('M j, Y', strtotime($r['date_added']))) ?></td>
        <td class="actions">
          <a class="view" href="<?= BASE_URL ?>/patients/view.php?id=<?= $r['id'] ?>">👁</a>
          <a class="edit" href="<?= BASE_URL ?>/patients/edit.php?id=<?= $r['id'] ?>">✏</a>
          <a class="del del-confirm" href="<?= BASE_URL ?>/patients/delete.php?id=<?= $r['id'] ?>">🗑</a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" style="text-align:center;color:#6b7280;padding:24px">No patients found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
