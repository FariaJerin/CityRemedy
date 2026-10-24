# CityRemedy — PHP and MySQL

CityRemedy is a civic issue reporting and tracking website built with PHP, HTML, CSS, JavaScript, and MySQL. It is organized to run locally with Apache and MySQL in XAMPP.
CityRemedy enables citizens to report local infrastructure problems (e.g., road damage, streetlight failures, garbage accumulation) with multimedia evidence. The system routes complaints directly to relevant city departments, facilitates validation through numeric verification scores, manages physical issue resolution, and captures public feedback to improve municipal accountability.

This folder is the standalone application:  PHP renders the HTML pages; CSS and plain JavaScript provide the interface.

## Requirements

- XAMPP with PHP 8.1 or later and MySQL/MariaDB
- PHP extensions enabled: `pdo_mysql`, `fileinfo`, and `mbstring`
- Apache and MySQL running in the XAMPP Control Panel

## Install in XAMPP

1. Copy the `cityremedy-xampp` folder into XAMPP's `htdocs` folder and rename it `CityRemedy` if needed.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open phpMyAdmin at `http://localhost/phpmyadmin`, choose **Import**, and import `CityRemedy/database/schema.sql`. The schema creates the `cityremedy` database and its tables. It does not drop existing tables.
4. If you use a different folder or database account, update `base_url`, `db_user`, and `db_pass`.
5. Open `http://localhost/CityRemedy/public/setup.php` and create the first system administrator. There are no default officer credentials.
6. Sign in at `http://localhost/CityRemedy/public/admin.php?view=login`. From the system administrator account, create an officer account for each department under **Officer accounts**.
7. Citizens can create accounts at `http://localhost/CityRemedy/public/index.php?page=register`.

The `.htaccess` file lets Apache serve the public home page from the project root and blocks direct access to application, configuration, database, and upload folders. The `/public` URL works without relying on the root redirect. If Apache reports a 403 or ignores the root redirect, enable `mod_rewrite` and `AllowOverride All`, or use the direct `/public` URL.


## What is included

- Citizen accounts with hashed passwords, issue reporting, optional anonymity, evidence uploads, duplicate checks, tracking IDs, community votes, notifications, and post-resolution feedback
- Department routing for roads, waste, lighting, water, drainage, and general services
- Officer sign-in with both a password and a department access code
- Department-scoped report queues, 1–5 validity scoring, rejection reasons, work orders, crew updates, and completion proof
- Public resolved-work feed and report timeline that refreshes status updates every 20 seconds
- System-administrator controls for verification thresholds, community fast-track votes, notifications, and department officer accounts
- MySQL schema for users, departments, reports, evidence, votes, verification, work orders, resolutions, feedback, notifications, settings, and activity logs
- Command-line backup that snapshots MySQL data and copies uploaded evidence into a private timestamped folder

Community support reaching the configured vote threshold prioritizes a report for officer review. It does not bypass verification: only an authorized officer's validity score at or above the configured cutoff can create a work order. This preserves the SRS rule that low-scoring or invalid reports cannot be dispatched.

## Upload limits

The app accepts up to four JPG, PNG, WebP, or PDF evidence files, up to 8 MB each. Completion proof must be a JPG, PNG, or WebP image. In `php.ini`, set `upload_max_filesize` to at least `8M` and `post_max_size` to at least `36M`; then restart Apache. Uploaded files are kept outside the public web directory. Citizen evidence is available only to the report owner and authorized department officers; published completion photos are public.

For internet hosting, configure Apache to serve the site over HTTPS and enable the secure cookie flag. The local XAMPP instructions use `http://localhost` for development.

## Database and file backups

Run `php scripts/backup.php` from the `cityremedy-xampp` folder. Each run creates a private timestamped folder under `storage/backups/` containing a SQL snapshot and a copy of uploaded files. The SQL import creates a new `cityremedy_restore_...` database rather than dropping or overwriting the live database. Keep backups on a separate drive and restrict access: they contain report details, contact emails, password hashes, and uploaded evidence.

For regular backups on Windows, use Task Scheduler to run the script daily with XAMPP's PHP executable, for example `C:\xampp\php\php.exe`, and the full path to `scripts\backup.php`. Check that a new backup folder appears after the scheduled run. To restore, import the backup's `database.sql` through phpMyAdmin and copy its `attachments` contents back into `storage/uploads/`.

## Items left for a later decision

The SRS lists a GIS provider, SMS gateway, and hosting/domain provider as undecided. This build captures an address and optional latitude/longitude without requiring a third-party map key. Email and SMS delivery are not connected; the app provides in-portal notifications and settings placeholders until a provider is chosen.

## Source layout

```text
app/                 Shared PHP helpers, access checks, and data queries
app/views/           Shared page header and footer
database/schema.sql  MySQL database and initial departments/settings
public/              Website pages, CSS, JavaScript, and protected file delivery
storage/uploads/     Private evidence and completion images
storage/backups/     Private SQL snapshots and upload copies
scripts/backup.php   CLI database and attachment backup
config.example.php   Local database and URL defaults
```
