<?php
$pageTitle = 'Settings';
$active = 'settings';
require_once __DIR__ . '/includes/header.php';
?>
<div class="panel" style="max-width:640px">
  <h2 style="margin-bottom:14px">System Settings</h2>
  <ul class="sys-list">
    <li><span>App Name</span><span>PatientSys</span></li>
    <li><span>Version</span><span>1.0.0</span></li>
    <li><span>Database</span><span><?= e(DB_NAME) ?> @ <?= e(DB_HOST) ?></span></li>
    <li><span>PHP Version</span><span><?= phpversion() ?></span></li>
    <li><span>Timezone</span><span><?= date_default_timezone_get() ?></span></li>
  </ul>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
