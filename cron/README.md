# Appointment Reminder Cron — `cron/send_reminders.php`

This script scans the `appointments` table and sends **SMS + Email reminders**
for every Pending/Confirmed appointment scheduled in the **next 24 hours**
(Asia/Manila time). Each appointment is reminded only once
(`appointments.reminder_sent = 1` once notified).

---

## 1. What it does

For each appointment with `appt_date + appt_time` between **NOW** and
**NOW + 24h** (PHT):

1. Builds a friendly message with patient name, date, time, and booking ref.
2. Sends **SMS** if `patients.consent_sms = 1` (provider configured in
   `config/database.php` — `SMS_PROVIDER`).
3. Sends **Email** if `patients.consent_email = 1`. When
   `ADMIN_BCC_REMINDERS = true`, the admin email
   (`marchiecayudong@gmail.com`) is BCC'd.
4. Marks the appointment as reminded and writes an `audit_logs` entry
   (`cron.reminders.run`).

---

## 2. Manual test

From the project root:

```bash
php cron/send_reminders.php
```

Expected output:

```
[2026-05-03T22:15:01+08:00] Appt #12 → SMS:ok EMAIL:ok
Done. Processed 1 appointments.
```

---

## 3. Linux / Mac cron (recommended)

Open the crontab editor:

```bash
crontab -e
```

Add **one** of these. The first runs hourly (recommended — catches new bookings
quickly, each appointment is still reminded only once):

```cron
# Asia/Manila — run every hour, on the hour
CRON_TZ=Asia/Manila
0 * * * * /usr/bin/php /var/www/html/patientsys/cron/send_reminders.php >> /var/log/patientsys-reminders.log 2>&1
```

Or, if you want a single daily sweep at 9:00 AM PHT (sends reminders for
appointments happening within the next 24h):

```cron
CRON_TZ=Asia/Manila
0 9 * * * /usr/bin/php /var/www/html/patientsys/cron/send_reminders.php >> /var/log/patientsys-reminders.log 2>&1
```

> **Tip:** Replace `/usr/bin/php` with the output of `which php`, and update
> the project path. Make sure the log file is writable by the cron user.

If your system doesn't support `CRON_TZ`, prefix the command instead:

```cron
0 * * * * TZ=Asia/Manila /usr/bin/php /var/www/html/patientsys/cron/send_reminders.php >> /var/log/patientsys-reminders.log 2>&1
```

---

## 4. Windows — Task Scheduler (XAMPP)

1. Open **Task Scheduler → Create Task…**
2. **General:** name `PatientSys Reminders`, "Run whether user is logged on or not".
3. **Triggers → New:** Daily, repeat every **1 hour** for **1 day**.
4. **Actions → New:**
   - Program: `C:\xampp\php\php.exe`
   - Arguments: `C:\xampp\htdocs\patientsys\cron\send_reminders.php`
5. **Settings:** allow task to be run on demand.
6. Set Windows time zone to **(UTC+08:00) Taipei / Manila**, or override in the
   script (already pinned to `Asia/Manila` via `date_default_timezone_set`).

---

## 5. cPanel / shared hosting

Cron Jobs → Add new:

- Common Settings: **Once an hour** (`0 * * * *`)
- Command:
  ```
  /usr/local/bin/php /home/USER/public_html/patientsys/cron/send_reminders.php
  ```

cPanel servers usually default to UTC — the script forces Asia/Manila
internally, so the 24-hour window is always computed in PHT regardless.

---

## 6. Verifying it works

- **Audit log** (`/audit.php`) shows `cron.reminders.run` entries with the
  number of appointments processed.
- **`appointments.reminder_sent`** flips to `1`.
- **`appointments.last_reminder_at`** updates (when triggered manually from the
  admin UI).
- For SMS provider `log` (default), check `notify.log` in the project root.
- For Semaphore SMS, set `SMS_PROVIDER='semaphore'` and `SEMAPHORE_API_KEY` in
  `config/database.php`.

---

## 7. Admin contact

The admin number `09777721173` and email `marchiecayudong@gmail.com` are set
in `config/database.php`. They are BCC'd on every reminder email and used as a
fallback when a patient has no contact info on file.
