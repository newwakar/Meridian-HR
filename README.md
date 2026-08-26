# Meridian HR — PHP + MySQL

A working PHP/MySQL HR portal: payroll, roster planning, GPS + simulated
face-recognition attendance, and role-based dashboards for admins and
employees.

## Requirements

- PHP 8.0+ with the `pdo_mysql` extension
- MySQL 5.7+ or MariaDB 10.3+
- A web server (Apache/Nginx) with PHP support, or PHP's built-in server for
  local testing

## Setup

1. **Create the database and import the schema:**
   ```bash
   mysql -u root -p -e "CREATE DATABASE meridian_hr;"
   mysql -u root -p meridian_hr < db/schema.sql
   ```

2. **Configure the connection.** Edit `config.php` directly, or set
   environment variables before starting PHP:
   ```
   HR_DB_HOST=127.0.0.1
   HR_DB_PORT=3306
   HR_DB_NAME=meridian_hr
   HR_DB_USER=root
   HR_DB_PASS=your_password
   ```

3. **Set real login passwords** (schema.sql ships with placeholder hashes):
   ```bash
   php db/seed_passwords.php
   ```
   This sets:
   - `admin` / `Admin@123` (Admin / HR Manager)
   - `aisha`, `rahul`, `priya`, `karan`, `sneha`, `vikram`, `ananya`, `rohan` / `Employee@123`

   **Change these before deploying anywhere but a local machine.**

4. **(Optional) Seed two weeks of demo roster + attendance history:**
   ```bash
   php db/seed_demo_data.php
   ```
   This leaves *today* untouched so you can test the live check-in/check-out
   flow yourself instead of seeing pre-filled data.

5. **Run it:**
   ```bash
   php -S localhost:8000
   ```
   Then open `http://localhost:8000` and sign in with one of the accounts
   above. For real deployment, point an Apache/Nginx vhost at this folder
   instead.

## How the ₹50 attendance rule works

- **Roster Planning** assigns a shift (Morning/Evening/Night) to an employee
  on a date, stored in the `roster` table.
- **Attendance** records a real check-in (`attendance` table) with GPS
  coordinates and a simulated face-verification flag.
- Once a shift's end time has passed, **"Run attendance payroll sync"**
  (Attendance page, admin) — or a scheduled cron job calling the same
  logic — posts one ledger row per employee per date to `payroll_ledger`:
  **+₹50** if they checked in, **−₹50** if they didn't. The sync is
  idempotent: re-running it never double-charges a date that's already been
  processed.
- **Payroll** sums `base_salary + bonus − other_deduction ± attendance
  adjustments` for the net pay shown to admins and employees.

To run the sync automatically instead of clicking the button, add a cron
entry that hits the same logic, e.g.:
```bash
* * * * * php /path/to/app/cron_attendance_sync.php
```
(see `cron_attendance_sync.php` for a ready-made script).

## Security notes — read before deploying for real

This is a solid starting point, not a hardened production system. Before
using it beyond a local demo:

- **Serve it over HTTPS** and set the session cookie's `secure` flag
  (`includes/auth.php`).
- **Rotate the demo passwords** — `seed_passwords.php` sets well-known
  values on purpose, for a fresh install.
- **Restrict `db/`** — an `.htaccess` is included to block direct access to
  the SQL/seed scripts, but confirm your web server actually honors
  `.htaccess` (Nginx doesn't; block the path in your server config instead).
- **Face recognition is simulated.** The camera preview and "verified"
  badge are cosmetic — there's no real biometric matching. A production
  system needs a dedicated face-recognition service (e.g. AWS Rekognition,
  Azure Face API) plus explicit user consent and a data-retention policy for
  biometric data, which is regulated in many jurisdictions.
- **GPS is checked server-side** (see `api/checkin.php`), but client GPS can
  still be spoofed by a modified app or emulator. For stricter control, pair
  it with office Wi-Fi/IP checks or a physical badge reader.
- All state-changing requests require a CSRF token and a logged-in session;
  all SQL uses parameterized queries via PDO. Login attempts are rate
  limited with a temporary lockout after 5 failures.

## Structure

```
config.php               DB connection + tunable constants (office GPS, ₹ amounts)
includes/auth.php        Sessions, login, CSRF, lockout
includes/functions.php   Business logic: payroll math, attendance sync, helpers
includes/header.php      Shared sidebar/topbar shell
includes/footer.php      Closing markup + shared JS
login.php / logout.php   Auth pages
dashboard.php            Admin + employee dashboards
roster.php               Weekly shift calendar (admin can edit)
attendance.php           GPS + face check-in/out, admin attendance log
payroll.php              Salary sheet + ledger (admin), breakdown (employee)
employees.php            Team directory + add employee (admin)
api/*.php                JSON endpoints called via fetch() from the pages above
db/schema.sql            Table definitions + starter employee rows
db/seed_passwords.php    Sets real bcrypt password hashes
db/seed_demo_data.php    Optional: two weeks of demo roster/attendance
```
