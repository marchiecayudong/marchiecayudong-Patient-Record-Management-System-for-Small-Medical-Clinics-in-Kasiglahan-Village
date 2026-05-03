<?php
// Public online booking page (no login required) - calls /api/book.php
require_once __DIR__ . '/config/database.php';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Book an Appointment — PatientSys</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<style>
  body{background:linear-gradient(135deg,#eef2ff,#f8fafc);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
  .booking{max-width:560px;width:100%;background:#fff;border-radius:16px;padding:32px;box-shadow:0 20px 50px -20px rgba(15,23,42,.25)}
  .booking h1{margin:0 0 6px;font-size:24px;color:#0f172a}
  .booking p.lead{color:#6b7280;margin:0 0 22px}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .field{margin-bottom:14px}
  label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
  input,select,textarea{width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;font-family:inherit}
  input:focus,select:focus,textarea:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.15)}
  .consent{display:flex;align-items:flex-start;gap:8px;background:#f8fafc;padding:10px 12px;border-radius:8px;margin-bottom:8px}
  .consent input{width:auto;margin-top:3px}
  .consent label{margin:0;font-weight:500;color:#374151;font-size:13px}
  button{width:100%;padding:13px;background:#2563eb;color:#fff;border:0;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;margin-top:8px}
  button:hover{background:#1d4ed8}
  .alert{padding:12px;border-radius:8px;margin-bottom:14px;font-size:14px}
  .alert.ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
  .alert.err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
  .top{text-align:center;margin-bottom:18px}
  .top .logo{font-size:30px}
  .footer{text-align:center;color:#6b7280;font-size:12px;margin-top:18px}
  .footer a{color:#2563eb;text-decoration:none}
  @media(max-width:520px){.row{grid-template-columns:1fr}}
</style>
</head>
<body>
<form class="booking" id="bf">
  <div class="top">
    <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="Clinic Logo" style="width:90px;height:90px;object-fit:contain;border-radius:14px;background:#fff;padding:6px;border:1px solid #e5e7eb">
    <h1>Book an Appointment</h1>
    <p class="lead"><?= (CLINIC_NAME) ?> — instant SMS & email confirmation with booking reference.</p>
  </div>
  <div id="msg"></div>

  <div class="field"><label>Full Name *</label><input name="name" required maxlength="120"></div>
  <div class="row">
    <div class="field"><label>Age *</label><input name="age" type="number" min="0" max="130" required></div>
    <div class="field"><label>Gender *</label>
      <select name="gender" required><option value="">Select…</option><option>Male</option><option>Female</option><option>Other</option></select>
    </div>
  </div>
  <div class="row">
    <div class="field"><label>Mobile (PH) *</label><input name="contact" required pattern="[0-9+\- ]{7,20}" placeholder="09171234567"></div>
    <div class="field"><label>Email</label><input name="email" type="email" placeholder="you@example.com"></div>
  </div>
  <div class="row">
    <div class="field"><label>Preferred Date *</label><input name="appt_date" type="date" required></div>
    <div class="field"><label>Preferred Time *</label><input name="appt_time" type="time" required></div>
  </div>
  <div class="field"><label>Purpose *</label>
    <select name="purpose" required>
      <option value="">Select…</option>
      <option>Regular Checkup</option>
      <option>Follow-up Checkup</option>
      <option>Consultation</option>
      <option>Vaccination</option>
      <option>Lab Test</option>
      <option>Other</option>
    </select>
  </div>

  <div class="consent"><input type="checkbox" id="cs" name="consent_sms" checked><label for="cs">I agree to receive SMS reminders for my appointment</label></div>
  <div class="consent"><input type="checkbox" id="ce" name="consent_email" checked><label for="ce">I agree to receive Email reminders for my appointment</label></div>

  <button type="submit" id="sb">Book Appointment</button>
  <div class="footer">
    Staff member? <a href="<?= BASE_URL ?>/login.php">Sign in here</a>
  </div>
</form>

<script>
const form = document.getElementById('bf');
const msg  = document.getElementById('msg');
const sb   = document.getElementById('sb');

// minimum date = today
form.appt_date.min = new Date().toISOString().slice(0,10);

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  msg.innerHTML = ''; sb.disabled = true; sb.textContent = 'Booking…';
  const fd = new FormData(form);
  const body = {
    name: fd.get('name'), age: fd.get('age'), gender: fd.get('gender'),
    contact: fd.get('contact'), email: fd.get('email'),
    appt_date: fd.get('appt_date'), appt_time: fd.get('appt_time'),
    purpose: fd.get('purpose'),
    consent_sms:   form.consent_sms.checked,
    consent_email: form.consent_email.checked,
  };
  try {
    const r = await fetch('<?= BASE_URL ?>/api/book.php', {
      method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(body)
    });
    const j = await r.json();
    if (!r.ok) throw new Error(j.error || 'Booking failed');
    msg.innerHTML = `<div class="alert ok"><strong>Booking confirmed!</strong><br>Booking Reference: <span class="booking-ref">${j.booking_ref}</span><br>Status: ${j.status} · SMS: ${j.notifications.sms} · Email: ${j.notifications.email}<br><small>Please save your reference. We've also sent it to your phone and email.</small></div>`;
    form.reset();
  } catch (err) {
    msg.innerHTML = `<div class="alert err">${err.message}</div>`;
  } finally {
    sb.disabled = false; sb.textContent = 'Book Appointment';
  }
});
</script>
</body>
</html>
