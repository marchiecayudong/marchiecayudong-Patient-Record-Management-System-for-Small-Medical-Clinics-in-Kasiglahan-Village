# PatientSys API v3

All endpoints return JSON. Timezone: **Asia/Manila (PHT, UTC+8)**.

Replace `http://localhost/patientsys` with your install URL. Throughout these
examples we assume the variable `{{baseUrl}} = http://localhost/patientsys`.

## Roles & Permissions

| Endpoint                          | Roles allowed     | Public |
|-----------------------------------|-------------------|:------:|
| `POST /api/auth.php`              | (any seed user)   |   —    |
| `GET  /api/patients.php`          | admin, doctor, nurse | — |
| `GET  /api/patient.php?id=`       | admin, doctor, nurse | — |
| `GET  /api/fhir/Patient.php`      | admin, doctor     |   —    |
| `GET  /api/fhir/Appointment.php`  | admin, doctor     |   —    |
| `GET  /api/metadata.php`          | (no auth)         |   ✓    |
| `POST /api/qr_issue.php`          | admin, doctor, nurse | — |
| `POST /api/book.php`              | (no auth - patient) |  ✓   |

**Patient consent**: FHIR/REST patient endpoints return **403** for any
patient where `consent_share = 0`. Bundles only include consenting patients.

## Rate Limits

Per IP, sliding window:

| Group | Limit       | Used by                         |
|-------|-------------|---------------------------------|
| auth  | 10 / 60s    | `/api/auth.php`                 |
| fhir  | 60 / 60s    | `/api/fhir/*`, `/api/metadata`  |
| rest  | 60 / 60s    | `/api/patient.php`, `/api/patients.php` |
| qr    | 60 / 60s    | `/api/qr_issue.php`             |
| book  | 10 / 60s    | `/api/book.php` (public)        |

Responses include `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After`
on 429.

## Audit Logging

Every API hit (and key UI actions like login, patient view, QR consume)
writes a row to `audit_logs`. Admins can browse via **Sidebar → 🛡 Audit Log**.

---

## 1. Get a JWT

```bash
curl -X POST {{baseUrl}}/api/auth.php \
  -H "Content-Type: application/json" \
  -d '{"username":"doctor","password":"doctor123"}'
```

Response:
```json
{ "token": "eyJ...", "token_type": "Bearer", "expires_in": 3600,
  "user": {"id": 2, "name": "Dr. Jose Cruz", "role": "doctor"} }
```

Save the token:
```bash
TOKEN="eyJ..."
```

## 2. FHIR Patient

```bash
# Search (returns ONLY patients with consent_share=1)
curl {{baseUrl}}/api/fhir/Patient.php -H "Authorization: Bearer $TOKEN"

# Single patient
curl {{baseUrl}}/api/fhir/Patient.php?id=1 -H "Authorization: Bearer $TOKEN"

# A non-consenting patient -> 403
curl -i {{baseUrl}}/api/fhir/Patient.php?id=3 -H "Authorization: Bearer $TOKEN"
```

## 3. FHIR Appointment

```bash
# All bookable appointments (consent enforced)
curl {{baseUrl}}/api/fhir/Appointment.php -H "Authorization: Bearer $TOKEN"

# Filter by patient
curl "{{baseUrl}}/api/fhir/Appointment.php?patient=1" -H "Authorization: Bearer $TOKEN"
```

## 4. Issue a one-time QR token

```bash
curl -X POST {{baseUrl}}/api/qr_issue.php \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"patient_id": 1}'
```

Response:
```json
{ "token":"<64hex>", "url":"http://.../qr/scan.php?t=...",
  "expires_at":"2026-05-02 18:30:00", "ttl_seconds":600, "one_time":true }
```

The `url` is what gets encoded in the QR image. Tokens expire after 10
minutes and can be **consumed exactly once**.

## 5. Online booking (PUBLIC — no JWT)

```bash
curl -X POST {{baseUrl}}/api/book.php \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Liza Mae",
    "age": 29,
    "gender": "Female",
    "contact": "09171234567",
    "email": "liza@example.com",
    "appt_date": "2026-05-10",
    "appt_time": "10:00",
    "purpose": "Consultation",
    "consent_sms": true,
    "consent_email": true
  }'
```

Friendly UI for patients: **`{{baseUrl}}/book.php`**.

## 6. Reminders cron

Run hourly (sends SMS/email for appts due within 24h, respects consent):

```bash
* */1 * * * /usr/bin/php /var/www/html/patientsys/cron/send_reminders.php
```

SMS provider: configure `SMS_PROVIDER='semaphore'` and `SEMAPHORE_API_KEY`
in `config/database.php` (defaults to `log` mode = no real SMS sent).

---

## Postman Collection

Import `postman/PatientSys.postman_collection.json` and the
`postman/PatientSys.postman_environment.json`. The login request stores the
JWT into `{{token}}` automatically — every other request inherits it.
