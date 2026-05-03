# PatientSys  — Patient Record Management System

PHP + MySQL + HTML/CSS/JS. Built for XAMPP/MAMP/LAMP. Timezone: **Asia/Manila**.

## What's new in PRMS

- **Online booking** (`/book.php`) — patient self-service, no login.
- **SMS + Email reminders** with per-patient consent (`consent_sms`,
  `consent_email`). Cron at `cron/send_reminders.php`. Pluggable provider
  (Semaphore for PH SMS; defaults to log-only).
- **JWT-protected REST + FHIR R4** with **role-based access**
  (admin / doctor / nurse) and **patient consent enforcement** (`consent_share`).
- **Rate limiting** on every `/api/*` endpoint (sliding-window per IP).
- **Audit log** for every API call and sensitive UI action — visible to
  admins at `/audit.php`.
- **One-time, short-lived QR tokens** (10 min, single-use) — old QR links
  no longer work.
- **Postman collection + JWT/FHIR examples** in `postman/` and `api/README.md`.

## Install

1. Drop the folder into `htdocs/patientsys` (XAMPP) or `/var/www/html/patientsys`.
2. Create the database: import `sql/patientsys.sql` in phpMyAdmin
   (or `mysql -u root < sql/patientsys.sql`).
3. Edit `config/database.php` if your DB credentials/`BASE_URL` differ.
4. Open `http://localhost/patientsys/login.php`.


### Patient online booking

`http://localhost/patientsys/book.php` — public, no login required.

### Reminders cron

```bash
* */1 * * * /usr/bin/php /var/www/html/patientsys/cron/send_reminders.php
```

For real SMS, sign up at [semaphore.co](https://semaphore.co), set
`SMS_PROVIDER='semaphore'` and `SEMAPHORE_API_KEY` in `config/database.php`.

## API

See `api/README.md` and `postman/PatientSys.postman_collection.json`.
