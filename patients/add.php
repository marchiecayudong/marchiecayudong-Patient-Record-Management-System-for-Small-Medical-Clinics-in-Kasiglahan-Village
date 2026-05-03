<?php
$pageTitle = 'Add Patient';
$active = 'patients';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth.php';
if (!role_can('edit_patient')) { echo "<div class='panel'>Forbidden — your role cannot add patients.</div>"; require __DIR__ . '/../includes/footer.php'; exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $age     = (int)($_POST['age'] ?? 0);
    $gender  = $_POST['gender'] ?? '';
    $contact = trim($_POST['contact'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $cs_share = isset($_POST['consent_share']) ? 1 : 0;
    $cs_sms   = isset($_POST['consent_sms'])   ? 1 : 0;
    $cs_email = isset($_POST['consent_email']) ? 1 : 0;

    if (!$name || !$age || !$gender || !$contact) {
        $err = 'Please fill all required fields.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO patients (name,age,gender,contact,address,email,consent_share,consent_sms,consent_email,consent_updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())");
        $stmt->execute([$name,$age,$gender,$contact,$address,$email,$cs_share,$cs_sms,$cs_email]);
        header('Location: ' . BASE_URL . '/patients/index.php'); exit;
    }
}
?>
<div class="panel">
  <div class="panel-head"><h2>Add New Patient</h2></div>
  <?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>
  <form method="post" class="form">
    <div><label>Full Name *</label><input name="name" required></div>
    <div><label>Age *</label><input type="number" name="age" min="1" max="130" required></div>
    <div><label>Gender *</label>
      <select name="gender" required>
        <option value="">Select gender</option>
        <option>Male</option><option>Female</option><option>Other</option>
      </select>
    </div>
    <div><label>Contact Number *</label><input name="contact" required></div>
    <div><label>Email</label><input type="email" name="email"></div>
    <div><label>Address</label><textarea name="address" rows="2"></textarea></div>

    <fieldset style="border:1px solid #e5e7eb;border-radius:8px;padding:14px;margin-top:8px">
      <legend style="padding:0 6px;font-weight:600;color:#374151">Patient Consent (Data Privacy Act of 2012)</legend>
      <label style="display:flex;gap:8px;align-items:flex-start;margin:6px 0"><input type="checkbox" name="consent_share"> Allow sharing of records with external systems via FHIR/REST API (labs, hospitals)</label>
      <label style="display:flex;gap:8px;align-items:flex-start;margin:6px 0"><input type="checkbox" name="consent_sms" checked> Allow SMS reminders</label>
      <label style="display:flex;gap:8px;align-items:flex-start;margin:6px 0"><input type="checkbox" name="consent_email" checked> Allow Email reminders</label>
    </fieldset>

    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Save Patient</button>
      <a class="btn btn-outline" href="<?= BASE_URL ?>/patients/index.php">Cancel</a>
    </div>
  </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
