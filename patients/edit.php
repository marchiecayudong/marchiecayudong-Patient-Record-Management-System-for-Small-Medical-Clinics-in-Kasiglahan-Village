<?php
$pageTitle = 'Edit Patient';
$active = 'patients';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth.php';
if (!role_can('edit_patient')) { echo "<div class='panel'>Forbidden — your role cannot edit patients.</div>"; require __DIR__ . '/../includes/footer.php'; exit; }

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id=?");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) { echo "<div class='panel'>Patient not found.</div>"; require __DIR__ . '/../includes/footer.php'; exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cs_share = isset($_POST['consent_share']) ? 1 : 0;
    $cs_sms   = isset($_POST['consent_sms'])   ? 1 : 0;
    $cs_email = isset($_POST['consent_email']) ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE patients SET name=?,age=?,gender=?,contact=?,address=?,email=?,consent_share=?,consent_sms=?,consent_email=?,consent_updated_at=NOW() WHERE id=?");
    $stmt->execute([
        $_POST['name'], (int)$_POST['age'], $_POST['gender'],
        $_POST['contact'], $_POST['address'] ?? '', $_POST['email'] ?? '',
        $cs_share, $cs_sms, $cs_email, $id
    ]);
    header('Location: ' . BASE_URL . '/patients/index.php'); exit;
}
?>
<div class="panel">
  <div class="panel-head"><h2>Edit Patient #<?= $p['id'] ?></h2></div>
  <form method="post" class="form">
    <div><label>Full Name</label><input name="name" value="<?= e($p['name']) ?>" required></div>
    <div><label>Age</label><input type="number" name="age" value="<?= e($p['age']) ?>" required></div>
    <div><label>Gender</label>
      <select name="gender" required>
        <?php foreach (['Male','Female','Other'] as $g): ?>
          <option <?= $p['gender']===$g?'selected':'' ?>><?= $g ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label>Contact</label><input name="contact" value="<?= e($p['contact']) ?>" required></div>
    <div><label>Email</label><input type="email" name="email" value="<?= e($p['email']) ?>"></div>
    <div><label>Address</label><textarea name="address" rows="2"><?= e($p['address']) ?></textarea></div>

    <fieldset style="border:1px solid #e5e7eb;border-radius:8px;padding:14px;margin-top:8px">
      <legend style="padding:0 6px;font-weight:600;color:#374151">Patient Consent</legend>
      <label style="display:flex;gap:8px;align-items:flex-start;margin:6px 0"><input type="checkbox" name="consent_share" <?= $p['consent_share']?'checked':'' ?>> Allow external data sharing via FHIR/REST API</label>
      <label style="display:flex;gap:8px;align-items:flex-start;margin:6px 0"><input type="checkbox" name="consent_sms"   <?= $p['consent_sms']?'checked':'' ?>> Allow SMS reminders</label>
      <label style="display:flex;gap:8px;align-items:flex-start;margin:6px 0"><input type="checkbox" name="consent_email" <?= $p['consent_email']?'checked':'' ?>> Allow Email reminders</label>
      <p style="font-size:12px;color:#6b7280;margin:6px 0 0">Last updated: <?= e($p['consent_updated_at'] ?? 'never') ?></p>
    </fieldset>

    <div class="form-actions">
      <button class="btn btn-primary">Update Patient</button>
      <a class="btn btn-outline" href="<?= BASE_URL ?>/patients/index.php">Cancel</a>
    </div>
  </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
