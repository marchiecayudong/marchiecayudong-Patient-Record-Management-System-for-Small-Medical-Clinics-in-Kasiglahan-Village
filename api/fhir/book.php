<?php
// PUBLIC online booking endpoint (no JWT - patient self-service)
//   POST /api/book.php  { name, age, gender, contact, email, appt_date, appt_time, purpose, consent_sms, consent_email }
// Rate-limited to slow spam. Audited.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/rate_limit.php';
require_once __DIR__ . '/../includes/notify.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

rate_limit('book', 10, 60); // public — keep tight

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }

$body = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$required = ['name','age','gender','contact','appt_date','appt_time','purpose'];
foreach ($required as $k) {
    if (empty($body[$k])) {
        http_response_code(400);
        audit_log('book.online.bad_request', null, 400, ['missing'=>$k]);
        echo json_encode(['error'=>"Field '$k' is required"]); exit;
    }
}
$name    = trim($body['name']);
$age     = (int)$body['age'];
$gender  = in_array($body['gender'], ['Male','Female','Other'], true) ? $body['gender'] : 'Other';
$contact = trim($body['contact']);
$email   = trim($body['email'] ?? '');
$date    = $body['appt_date'];
$time    = $body['appt_time'];
$purpose = trim($body['purpose']);
$cs_sms  = !empty($body['consent_sms'])   ? 1 : 0;
$cs_em   = !empty($body['consent_email']) ? 1 : 0;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
    http_response_code(400); echo json_encode(['error'=>'Invalid date/time format. Use YYYY-MM-DD and HH:MM.']); exit;
}
if (strtotime("$date $time") < time()) {
    http_response_code(400); echo json_encode(['error'=>'Appointment must be in the future']); exit;
}

try {
    $pdo->beginTransaction();
    // Match existing patient by contact (or email)
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE contact = ? OR (email <> '' AND email = ?) LIMIT 1");
    $stmt->execute([$contact, $email]);
    $p = $stmt->fetch();
    if (!$p) {
        $ins = $pdo->prepare("INSERT INTO patients (name,age,gender,contact,email,consent_sms,consent_email,consent_updated_at) VALUES (?,?,?,?,?,?,?,NOW())");
        $ins->execute([$name,$age,$gender,$contact,$email ?: null,$cs_sms,$cs_em]);
        $pid = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM patients WHERE id=?");
        $stmt->execute([$pid]); $p = $stmt->fetch();
    } else {
        // Update consent if new ones provided
        $pdo->prepare("UPDATE patients SET consent_sms=?, consent_email=?, consent_updated_at=NOW() WHERE id=?")
            ->execute([$cs_sms, $cs_em, $p['id']]);
        $p['consent_sms'] = $cs_sms; $p['consent_email'] = $cs_em;
    }

    $bookingRef = generate_booking_ref();
    $a = $pdo->prepare("INSERT INTO appointments (patient_id, appt_date, appt_time, purpose, status, source, booking_ref) VALUES (?,?,?,?,'Confirmed','online',?)");
    $a->execute([$p['id'], $date, substr($time,0,5).':00', $purpose, $bookingRef]);
    $aid = (int)$pdo->lastInsertId();
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    audit_log('book.online.error', null, 500, ['err'=>$e->getMessage()]);
    echo json_encode(['error'=>'Could not create booking', 'detail'=>$e->getMessage()]); exit;
}

// Send CONFIRMATION (with booking ref) immediately on both channels
$appt = ['appt_date'=>$date,'appt_time'=>$time,'purpose'=>$purpose,'booking_ref'=>$bookingRef];
$msg  = build_confirmation_message($p, $appt);
$smsR = send_sms_reminder($p, $msg, $aid);
$emR  = send_email_reminder($p, "Booking Confirmed [$bookingRef] — " . CLINIC_NAME, $msg, $aid);

audit_log('book.online.created', "appointment/$aid", 201, ['patient_id'=>$p['id'],'booking_ref'=>$bookingRef,'sms'=>$smsR['status'],'email'=>$emR['status']]);

http_response_code(201);
echo json_encode([
    'ok'             => true,
    'appointment_id' => $aid,
    'patient_id'     => (int)$p['id'],
    'booking_ref'    => $bookingRef,
    'status'         => 'Confirmed',
    'message'        => "Your booking is confirmed. Reference: $bookingRef",
    'notifications'  => ['sms' => $smsR['status'], 'email' => $emR['status']],
]);
