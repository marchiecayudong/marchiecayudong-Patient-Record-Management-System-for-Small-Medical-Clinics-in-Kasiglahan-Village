<?php
// cron/send_reminders.php
// Run via cron (or manually): php cron/send_reminders.php
// Sends SMS+Email reminders for confirmed appointments happening in the next 24h.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notify.php';
require_once __DIR__ . '/../includes/audit.php';

$now = new DateTime('now', new DateTimeZone('Asia/Manila'));
$tomorrow = (clone $now)->modify('+24 hours');

$stmt = $pdo->prepare(
    "SELECT a.*, p.name AS p_name, p.contact, p.email, p.consent_sms, p.consent_email
     FROM appointments a
     JOIN patients p ON p.id = a.patient_id
     WHERE a.reminder_sent = 0
       AND a.status IN ('Pending','Confirmed')
       AND CONCAT(a.appt_date, ' ', a.appt_time) BETWEEN ? AND ?"
);
$stmt->execute([$now->format('Y-m-d H:i:s'), $tomorrow->format('Y-m-d H:i:s')]);
$rows = $stmt->fetchAll();

$count = 0;
foreach ($rows as $a) {
    $patient = [
        'id' => $a['patient_id'], 'name' => $a['p_name'],
        'contact' => $a['contact'], 'email' => $a['email'],
        'consent_sms' => $a['consent_sms'], 'consent_email' => $a['consent_email'],
    ];
    $msg = build_reminder_message($patient, $a);
    $sms = send_sms_reminder($patient, $msg, $a['id']);
    $em  = send_email_reminder($patient, 'Appointment Reminder — PatientSys', $msg, $a['id']);
    $pdo->prepare("UPDATE appointments SET reminder_sent = 1 WHERE id = ?")->execute([$a['id']]);
    $count++;
    echo "[" . date('c') . "] Appt #{$a['id']} → SMS:{$sms['status']} EMAIL:{$em['status']}\n";
}
audit_log('cron.reminders.run', null, 200, ['processed' => $count]);
echo "Done. Processed $count appointments.\n";
