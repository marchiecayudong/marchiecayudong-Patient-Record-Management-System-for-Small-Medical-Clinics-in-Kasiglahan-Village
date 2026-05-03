<?php
$pageTitle = 'Appointments';
$active = 'appointments';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/notify.php';
require_once __DIR__ . '/../includes/audit.php';

$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? 'create';

    if ($action === 'remind') {
        $aid = (int)($_POST['appointment_id'] ?? 0);
        if ($aid && (current_role() === 'admin' || current_role() === 'doctor' || current_role() === 'nurse')) {
            $r = trigger_appointment_reminder($aid);
            audit_log('appointment.reminder.manual', "appointment/$aid", 200, [
                'sms' => $r['sms']['status'] ?? 'n/a',
                'email' => $r['email']['status'] ?? 'n/a',
                'triggered_by' => $_SESSION['user']['username'] ?? null,
            ]);
            $flash = 'Reminder sent for appointment #'.$aid.' — SMS: '.($r['sms']['status'] ?? 'n/a').' · Email: '.($r['email']['status'] ?? 'n/a');
        }
    } else {
        $ref = generate_booking_ref();
        $stmt = $pdo->prepare("INSERT INTO appointments (patient_id,appt_date,appt_time,purpose,status,booking_ref,source) VALUES (?,?,?,?,?,?, 'staff')");
        $stmt->execute([
            (int)$_POST['patient_id'], $_POST['appt_date'], $_POST['appt_time'],
            $_POST['purpose'], $_POST['status'] ?? 'Pending', $ref,
        ]);
        $aid = (int)$pdo->lastInsertId();
        audit_log('appointment.created', "appointment/$aid", 201, ['ref'=>$ref]);

        // Auto-confirmation notifications when staff confirms immediately
        if (($_POST['status'] ?? '') === 'Confirmed') {
            $p = $pdo->prepare("SELECT * FROM patients WHERE id=?");
            $p->execute([(int)$_POST['patient_id']]);
            $patient = $p->fetch();
            if ($patient) {
                $appt = ['appt_date'=>$_POST['appt_date'],'appt_time'=>$_POST['appt_time'],'purpose'=>$_POST['purpose'],'booking_ref'=>$ref];
                $msg = build_confirmation_message($patient, $appt);
                send_sms_reminder($patient, $msg, $aid);
                send_email_reminder($patient, "Booking Confirmed [$ref] — " . CLINIC_NAME, $msg, $aid);
            }
        }

        header('Location: ' . BASE_URL . '/appointments/index.php?ok=1&ref='.$ref); exit;
    }
}

$rows = $pdo->query("SELECT a.*, p.name, p.contact, p.email FROM appointments a JOIN patients p ON p.id=a.patient_id ORDER BY appt_date DESC, appt_time")->fetchAll();
$patients = $pdo->query("SELECT id,name FROM patients ORDER BY name")->fetchAll();
?>
<?php if ($flash): ?><div class="alert alert-success"><?= e($flash) ?></div><?php endif; ?>
<?php if (!empty($_GET['ok'])): ?><div class="alert alert-success">Appointment created. Booking Reference: <span class="booking-ref"><?= e($_GET['ref'] ?? '') ?></span></div><?php endif; ?>

<div class="grid-2">
  <div class="panel">
    <div class="panel-head"><h2>All Appointments</h2></div>
    <table>
      <thead><tr><th>Patient</th><th>Ref</th><th>Date</th><th>Time</th><th>Purpose</th><th>Status</th><th>Reminders</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $a): ?>
        <tr>
          <td><?= e($a['name']) ?></td>
          <td><?= $a['booking_ref'] ? '<span class="booking-ref">'.e($a['booking_ref']).'</span>' : '<small style="color:#9ca3af">—</small>' ?></td>
          <td><?= e(date('M j, Y', strtotime($a['appt_date']))) ?></td>
          <td><?= e(date('h:i A', strtotime($a['appt_time']))) ?></td>
          <td><?= e($a['purpose']) ?></td>
          <td><span class="badge-status badge-<?= strtolower($a['status']) ?>"><?= e($a['status']) ?></span></td>
          <td style="font-size:12px;color:#6b7280">
            <?= (int)($a['reminder_count'] ?? 0) ?>×
            <?= !empty($a['last_reminder_at']) ? '<br><small>'.e(date('M j H:i', strtotime($a['last_reminder_at']))).'</small>' : '' ?>
          </td>
          <td class="actions" style="white-space:nowrap">
            <form method="post" style="display:inline" onsubmit="return confirm('Send SMS + Email reminder to <?= e($a['name']) ?>?');">
              <input type="hidden" name="_action" value="remind">
              <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
              <button class="btn btn-outline btn-sm" title="Send reminder now">🔔 Remind</button>
            </form>
            <a class="del del-confirm" href="<?= BASE_URL ?>/appointments/delete.php?id=<?= $a['id'] ?>" title="Delete">🗑</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="panel">
    <div class="panel-head"><h2>New Appointment</h2></div>
    <form method="post" class="form">
      <div><label>Patient</label>
        <select name="patient_id" required>
          <option value="">Select patient</option>
          <?php foreach ($patients as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div><label>Date</label><input type="date" name="appt_date" required value="<?= date('Y-m-d') ?>"></div>
      <div><label>Time</label><input type="time" name="appt_time" required></div>
      <div><label>Purpose</label><input name="purpose" required placeholder="Regular Checkup"></div>
      <div><label>Status</label>
        <select name="status">
          <option>Pending</option><option>Confirmed</option><option>Completed</option><option>Cancelled</option>
        </select>
      </div>
      <small style="color:#6b7280">A unique booking reference is generated automatically. If status is "Confirmed", SMS + Email are sent immediately.</small>
      <button class="btn btn-primary">Schedule Appointment</button>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
