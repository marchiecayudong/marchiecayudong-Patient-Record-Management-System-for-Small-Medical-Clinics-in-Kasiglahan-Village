<?php
// includes/notify.php - SMS + Email reminders/confirmations with consent enforcement
require_once __DIR__ . '/../config/database.php';

function generate_booking_ref() {
    return 'PSYS-' . strtoupper(bin2hex(random_bytes(3))); // PSYS-XXXXXX
}

function send_sms_reminder($patient, $message, $appointment_id = null) {
    global $pdo;
    if (empty($patient['consent_sms'])) {
        log_notification($patient['id'], $appointment_id, 'sms', $patient['contact'] ?? '', $message, 'skipped_no_consent', 'Patient has not consented to SMS reminders');
        return ['status' => 'skipped_no_consent', 'detail' => 'No SMS consent on file'];
    }
    $to = preg_replace('/\D+/', '', $patient['contact'] ?? '');
    if (!$to) {
        log_notification($patient['id'], $appointment_id, 'sms', '', $message, 'failed', 'No phone number');
        return ['status' => 'failed', 'detail' => 'No phone number'];
    }

    $resp = '';
    if (defined('SMS_PROVIDER') && SMS_PROVIDER === 'semaphore' && SEMAPHORE_API_KEY !== '') {
        $resp = http_post('https://api.semaphore.co/api/v4/messages', [
            'apikey'     => SEMAPHORE_API_KEY,
            'number'     => $to,
            'message'    => $message,
            'sendername' => SEMAPHORE_SENDER,
        ]);
        $ok = $resp && stripos($resp, 'error') === false;
    } else {
        $ok = true;
        $resp = '[LOG-ONLY] would send SMS to +' . $to . ' (admin copy: ' . ADMIN_CONTACT_PHONE . ')';
    }
    log_notification($patient['id'], $appointment_id, 'sms', $to, $message, $ok ? 'sent' : 'failed', $resp);
    return ['status' => $ok ? 'sent' : 'failed', 'detail' => $resp];
}

function send_email_reminder($patient, $subject, $message, $appointment_id = null) {
    global $pdo;
    if (empty($patient['consent_email'])) {
        log_notification($patient['id'], $appointment_id, 'email', $patient['email'] ?? '', $message, 'skipped_no_consent', 'Patient has not consented to Email reminders');
        return ['status' => 'skipped_no_consent', 'detail' => 'No email consent on file'];
    }
    $to = trim($patient['email'] ?? '');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        log_notification($patient['id'], $appointment_id, 'email', $to, $message, 'failed', 'No valid email');
        return ['status' => 'failed', 'detail' => 'No valid email address'];
    }
    $headers  = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    if (defined('ADMIN_BCC_REMINDERS') && ADMIN_BCC_REMINDERS && ADMIN_CONTACT_EMAIL) {
        $headers .= "Bcc: " . ADMIN_CONTACT_EMAIL . "\r\n";
    }
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $ok = @mail($to, $subject, $message, $headers);
    $resp = $ok ? 'mail() returned true (BCC admin: '.ADMIN_CONTACT_EMAIL.')' : 'mail() returned false (configure SMTP)';
    log_notification($patient['id'], $appointment_id, 'email', $to, $message, $ok ? 'sent' : 'failed', $resp);
    return ['status' => $ok ? 'sent' : 'failed', 'detail' => $resp];
}

function log_notification($pid, $apid, $channel, $recipient, $message, $status, $resp = '') {
    global $pdo;
    try {
        $pdo->prepare(
            "INSERT INTO notifications_log (patient_id, appointment_id, channel, recipient, message, status, provider_response)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        )->execute([$pid, $apid, $channel, $recipient, $message, $status, $resp]);
    } catch (Throwable $e) {}
}

function http_post($url, $fields) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_TIMEOUT        => 10,
    ]);
    $r = curl_exec($ch);
    if ($r === false) $r = 'curl_error: ' . curl_error($ch);
    curl_close($ch);
    return $r;
}

function build_reminder_message($patient, $appointment) {
    $when = date('M d, Y g:i A', strtotime($appointment['appt_date'] . ' ' . $appointment['appt_time']));
    $ref  = !empty($appointment['booking_ref']) ? " Ref: {$appointment['booking_ref']}." : '';
    return "Hi {$patient['name']}, reminder: your appointment is on {$when} ({$appointment['purpose']}).{$ref} " .
           "Please arrive 10 minutes early. — " . CLINIC_NAME;
}

function build_confirmation_message($patient, $appointment) {
    $when = date('M d, Y g:i A', strtotime($appointment['appt_date'] . ' ' . $appointment['appt_time']));
    $ref  = $appointment['booking_ref'] ?? '';
    return "Hi {$patient['name']}, your booking is CONFIRMED for {$when} ({$appointment['purpose']}). " .
           "Booking Reference: {$ref}. Please save this code. — " . CLINIC_NAME;
}

/**
 * Manually trigger reminder for one appointment (used by staff UI).
 * Returns ['sms'=>..., 'email'=>...]
 */
function trigger_appointment_reminder($appointment_id) {
    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT a.*, p.id AS pid, p.name AS p_name, p.contact, p.email, p.consent_sms, p.consent_email
         FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE a.id=?"
    );
    $stmt->execute([$appointment_id]);
    $a = $stmt->fetch();
    if (!$a) return ['error' => 'not_found'];

    $patient = [
        'id'=>$a['pid'],'name'=>$a['p_name'],'contact'=>$a['contact'],'email'=>$a['email'],
        'consent_sms'=>$a['consent_sms'],'consent_email'=>$a['consent_email'],
    ];
    $msg = build_reminder_message($patient, $a);
    $sms = send_sms_reminder($patient, $msg, $a['id']);
    $em  = send_email_reminder($patient, 'Appointment Reminder — ' . CLINIC_NAME, $msg, $a['id']);
    $pdo->prepare("UPDATE appointments SET reminder_count = reminder_count + 1, last_reminder_at = NOW() WHERE id=?")
        ->execute([$a['id']]);
    return ['sms'=>$sms, 'email'=>$em, 'booking_ref'=>$a['booking_ref']];
}
