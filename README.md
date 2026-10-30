# CityRemedy

[![Run PHPUnit Tests](https://github.com/FariaJerin/CityRemedy/actions/workflows/test.yml/badge.svg?branch=develop)](https://github.com/FariaJerin/CityRemedy/actions/workflows/test.yml)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-MariaDB-4479A1?logo=mysql&logoColor=white)
![Tests](https://img.shields.io/badge/tests-36%20passing-brightgreen)
![Coverage](https://img.shields.io/badge/line%20coverage-81.40%25-yellowgreen)
![Deployment](https://cityremedy-uits.infinityfree.io/)

**Your city, heard.** CityRemedy is a civic issue reporting and tracking web application. Citizens report local infrastructure problems (road damage, streetlight failures, water leakage, garbage, drainage blockage) with photo or PDF evidence. The system routes each report to the responsible city department, lets officers verify it with a numeric validity score, manages the repair, publishes proof of the completed work, and collects citizen feedback to improve municipal accountability.

> Course project for **CSE 416 — Software Project Design and Development **, Department of CSE, University of Information Technology and Sciences (UITS), Dhaka.

---

## Table of contents

1. [Features](#features)
2. [Technology stack](#technology-stack)
3. [How it works](#how-it-works)
4. [Requirements](#requirements)
5. [Installation (XAMPP)](#installation-xampp)
6. [Configuration](#configuration)
7. [Using the application](#using-the-application)
8. [Project structure](#project-structure)
9. [Running the tests](#running-the-tests)
10. [Test results and coverage](#test-results-and-coverage)
11. [Continuous integration](#continuous-integration)
12. [Security notes](#security-notes)
13. [Database and file backups](#database-and-file-backups)
14. [Git workflow and branching](#git-workflow-and-branching)
15. [AI tool usage disclosure](#ai-tool-usage-disclosure)
16. [Known limitations and future work](#known-limitations-and-future-work)
17. [Team](#team)

---

## Features

**For citizens**
- Register and sign in (passwords stored hashed).
- Report an issue with category, description, address and optional latitude/longitude, with up to four JPG, PNG, WebP or PDF evidence files (8 MB each).
- Optional anonymous reporting (the public sees "Community member").
- Duplicate check: similar active reports at the same place are shown so citizens can support an existing report instead.
- Community votes on reports; reaching the configured vote threshold fast-tracks a report for officer review.
- Track any report by its Complaint ID. The timeline refreshes status updates every 20 seconds.
- In-portal notifications.
- Give a 1–5 star rating and a comment after a report is resolved.

**For department officers**
- Sign in with email, password **and** a department access code.
- See only their own department's report queue.
- Start a review, give a 1–5 validity score, or reject a report with a reason.
- Create work orders, post crew updates and publish completion proof (photo).

**For system administrators**
- Set the verification score threshold and the community fast-track vote threshold.
- Create officer accounts for each department.
- See metrics across all departments.

**Public**
- Public feed of resolved work with proof photos, plus live statistics (total, under review, in progress, resolved).
- HTTPS and 24/7 availability verified.

## Technology stack

| Layer | Technology |
|---|---|
| Back end | PHP 8.1+ (plain PHP, no framework), PDO |
| Database | MySQL / MariaDB (11 tables) |
| Front end | HTML, CSS, vanilla JavaScript |
| Web server | Apache (XAMPP) |
| Testing | PHPUnit 13, Xdebug (coverage) |
| CI | GitHub Actions |

## How it works

```
Citizen submits report ──► routed to department by category
        │
        ▼
   Submitted ──► Under Verification ──► officer gives validity score (1–5)
                                          │
                       score ≥ threshold ─┴─ score < threshold
                              │                    │
                       Work Assigned           Rejected (reason recorded)
                              │
                         In Progress ──► officer publishes proof ──► Resolved
                                                                        │
                                              citizen feedback (1–5) ──► Closed
```

Category-to-department routing:

| Category | Department |
|---|---|
| Road damage | Roads |
| Streetlight failure | Lighting |
| Water leakage | Water |
| Garbage accumulation | Waste |
| Drainage blockage | Drainage |
| Other | General services |

Business rules from the SRS: only the authorised department may update its reports (BR-5.5.1), and a report below the validity threshold or marked invalid cannot receive a work order (BR-5.5.2). Community votes prioritise a report but never bypass officer verification.

## Requirements

- **XAMPP** with PHP 8.1 or later and MySQL/MariaDB
- PHP extensions: `pdo_mysql`, `fileinfo`, `mbstring`
- Apache and MySQL started in the XAMPP Control Panel
- **Composer** (only needed to run the tests)
- **Xdebug** (only needed to measure coverage)

## Installation (XAMPP)

1. Clone or copy the project into XAMPP's `htdocs` folder so the path is `C:\xampp\htdocs\CityRemedy`:
   ```powershell
   cd C:\xampp\htdocs
   git clone https://github.com/FariaJerin/CityRemedy.git
   ```
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open phpMyAdmin (`http://localhost/phpmyadmin`), choose **Import**, and import `CityRemedy/database/schema.sql`. It creates the `cityremedy` database, its tables, the six departments and the default settings. It does not drop existing tables.
4. Copy `config.example.php` to `config.local.php`, then edit it (see [Configuration](#configuration)). Set `setup_key` to a random secret of at least 32 characters. Generate one with:
   ```powershell
   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
   ```
5. Open `http://localhost/CityRemedy/public/setup.php`, enter the setup key and create the first **system administrator**. Afterwards remove the key from `config.local.php`. There are no default officer credentials.
6. Sign in at `http://localhost/CityRemedy/public/admin.php?view=login`. As system administrator, create one officer account per department under **Officer accounts**.
7. Citizens register at `http://localhost/CityRemedy/public/index.php?page=register`.

The root `.htaccess` serves the public home page from the project root and blocks direct access to the application, configuration, database and upload folders. The `/public` URL always works. If Apache shows a 403 or ignores the redirect, enable `mod_rewrite` and `AllowOverride All`, or use the `/public` URL directly.

### Upload limits (php.ini)

Set `upload_max_filesize = 8M` and `post_max_size = 36M` (four files per report), then restart Apache. Uploaded files are stored outside the public web directory. Citizen evidence is visible only to the report owner and authorised officers; published completion photos are public.

## Configuration

`config.local.php` is git-ignored. Available keys (see `config.example.php`):

| Key | Default | Meaning |
|---|---|---|
| `app_name` | `CityRemedy` | Application name |
| `base_url` | `/CityRemedy/public` | URL path of the `public` folder |
| `db_host` / `db_port` | `127.0.0.1` / `3306` | Database server |
| `db_name` | `cityremedy` | Database name |
| `db_user` / `db_pass` | `root` / *(empty)* | Database credentials (change for any real deployment) |
| `upload_max_bytes` | 8 MB | Per-file upload limit |
| `setup_key` | *(empty)* | Secret required by `setup.php`; setup is disabled until it is at least 32 characters |

For internet hosting, serve the site over **HTTPS** (the session cookie's `Secure` flag is set automatically on HTTPS). Local XAMPP uses plain `http://localhost` for development only.

## Using the application

| Who | Where | Address |
|---|---|---|
| Anyone | Home, public feed, track a report | `/public/index.php`, `?page=feed`, `?page=track` |
| Citizen | Register, sign in, report, account | `?page=register`, `?page=login`, `?page=report`, `?page=account` |
| Officer / admin | Officer sign-in and dashboard | `/public/admin.php?view=login` |
| First-time setup | Create first system admin | `/public/setup.php` |

Typical demo walk-through: register a citizen → submit a report with a photo → sign in as the department officer → start review and give a score at or above the threshold → update work → publish proof → return as the citizen to track the report and leave feedback.

## Project structure

```text
CityRemedy/
├── app/                    Shared logic
│   ├── auth.php            Session, roles, department access checks
│   ├── helpers.php         Escaping, URLs, input, flash messages, uploads, logging
│   ├── security.php        CSRF token creation and verification
│   ├── services.php        Routing, queries, feedback validation, statistics
│   ├── bootstrap.php       Configuration, session start, database connection
│   └── views/partials/     Shared header and footer
├── public/                 Web root: pages, form actions, CSS, JS, protected file delivery
├── database/schema.sql     MySQL schema, departments and default settings
├── scripts/backup.php      Command-line database and upload backup
├── storage/                Private uploads and backups (git-ignored contents)
├── tests/
│   ├── Unit/               Fast, isolated tests (14)
│   ├── Integration/        Service and session tests against the test database (22)
│   └── bootstrap.php       Test environment setup
├── docs/testing/           Test report and evidence (coverage report, screenshots)
├── .github/workflows/      GitHub Actions workflow (test.yml)
├── config.example.php      Configuration template
├── phpunit.xml             PHPUnit configuration
├── composer.json           Dependency manifest
└── USAGE_LOG.md            AI tool usage log
```

## Running the tests

The suite uses **PHPUnit 13**. Some tests query a separate database named `cityremedy_test`, so the live data is never touched.

**One-time setup (PowerShell, from the project folder)**

```powershell
composer install

# create the test database and load the tables (skips the first 4 lines of schema.sql,
# which would otherwise create/select the live database)
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS cityremedy_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
Get-Content database\schema.sql | Select-Object -Skip 4 | C:\xampp\mysql\bin\mysql.exe -u root cityremedy_test
```

**Run everything with one command**

```powershell
vendor/bin/phpunit --testdox
```

**Run with code coverage** (requires Xdebug)

```powershell
$env:XDEBUG_MODE="coverage"; vendor/bin/phpunit --coverage-html docs/testing/evidence/coverage
```

Open `docs/testing/evidence/coverage/index.html` in a browser to view the report. Run a single suite with `vendor/bin/phpunit --testsuite Unit` or `--testsuite Integration`.

**Linux / macOS**

```bash
composer install
mysql -u root -e "CREATE DATABASE IF NOT EXISTS cityremedy_test"
tail -n +5 database/schema.sql | mysql -u root cityremedy_test
vendor/bin/phpunit --testdox
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html docs/testing/evidence/coverage
```

## Test results and coverage

Results below are from the Experiment 9 run (PHP 8.5.12, PHPUnit 13.4.3, Xdebug 3.5.3) on the `develop` branch at commit `538718b`.

| Measure | Result |
|---|---|
| Tests | **36 passed**, 0 failed, 0 skipped (14 unit, 22 integration) |
| Assertions | 122 |
| Line coverage (core layer) | **81.40%** (197 / 242) |
| Function coverage (core layer) | **84.09%** (37 / 44) |

| File | Lines | Functions |
|---|---|---|
| `app/services.php` | 90.56% (96/106) | 84.61% (11/13) |
| `app/auth.php` | 84.61% (22/26) | 77.77% (7/9) |
| `app/security.php` | 77.77% (7/9) | 66.66% (2/3) |
| `app/helpers.php` | 71.28% (72/101) | 89.47% (17/19) |

**Scope and honesty note.** Coverage is measured for the four files in `app/` only (the "core layer"). The page and form-action scripts in `public/` (including `actions.php`) are not yet covered by automated tests, and some tests are smoke-level. These limits and the plan to close them are documented in the test report.

- Full test report: [`docs/testing/Test_Report.pdf`](docs/testing/) (Team10_Exp9)
- Coverage report and screenshots: [`docs/testing/evidence/`](docs/testing/evidence/)

## Continuous integration

`.github/workflows/test.yml` runs on every push and pull request to `develop` and `main`. It starts a MySQL 8.0 service, installs PHP 8.4 with Xdebug, installs Composer dependencies, loads `database/schema.sql` and runs `vendor/bin/phpunit --testdox`. A failing test fails the workflow. To block merges on failure, enable **Settings → Branches → Branch protection → Require status checks to pass** on `main` and `develop`.

## Security notes

- Passwords and officer access codes are stored with `password_hash`.
- All database access uses PDO prepared statements.
- Every form carries a CSRF token, verified on submission.
- Output is HTML-escaped; return-to redirects are limited to the application's own URLs.
- Session cookies are `HttpOnly`, `SameSite=Lax` and `Secure` on HTTPS.
- Evidence files live outside the web root and are served only to authorised users.
- First-time setup requires an operator-held `setup_key` and disables itself once an administrator exists.
- Never commit `config.local.php`, uploads or backups (they are git-ignored). Backups contain emails, password hashes and evidence.

## Database and file backups

Run from the project folder:

```powershell
C:\xampp\php\php.exe scripts\backup.php
```

Each run creates a timestamped folder under `storage/backups/` with a SQL snapshot (`database.sql`) and a copy of uploaded evidence (`attachments`). Importing the SQL creates a new `cityremedy_restore_...` database and never overwrites the live one. To restore, import `database.sql` through phpMyAdmin and copy the `attachments` contents back into `storage/uploads/`. Keep backups on a separate drive. For daily backups on Windows, schedule the command with Task Scheduler and check that a new folder appears.

## Git workflow and branching

| Branch | Purpose |
|---|---|
| `main` | Stable, release-ready code |
| `develop` | Integration branch; CI runs here |
| `feature/<name>` | One branch per feature or fix, merged into `develop` by pull request |

Commit messages use a short prefix such as `test:`, `ci:`, `security:`, `docs:` or `fix:`. Defects are tracked as GitHub Issues labelled `bug` plus one severity label (S1–S4) and one priority label (P1–P3); a fix references its issue with "Fixes #n" and adds a regression test.

## AI tool usage disclosure

AI assistance is logged in [`USAGE_LOG.md`](USAGE_LOG.md): which tool was used, for what, and how its output was verified. Test cases were designed by the team first; **Qodo** was used to suggest edge cases and tests. Generated tests are accepted only after their expected values are checked against the SRS and the code. Qodo's tests for verification logic were reviewed and **rejected** because they did not execute application code (kept unmerged on the `qodo-test-branch` branch).

## Known limitations and future work

- GIS provider, SMS gateway and hosting/domain provider are undecided in the SRS. The app captures an address and optional coordinates without a map key; email/SMS delivery is not connected (in-portal notifications only).
- Controller-level and browser end-to-end tests, the validity-threshold rule test, and regression tests for fixed defects are planned.
- Performance (p95), accessibility, security scans (OWASP ZAP, dependency audit, secret scan), static analysis and usability testing are planned for Experiment 10.

## Team

| ID | Name | Role | Email |
|---|---|---|---|
| 0432320005101002 | Faria Khanam Jerin | Test lead | 0432320005101002@uits.edu.bd |
| 0432320005101015 | Fatema Akter Lamia | Developer / reviewer | fatema_akter1015@uits.edu.bd |

Group No. 10 · Instructor: AL Imtiaz, Associate Professor, Department of CSE, UITS.

## License

Developed for academic purposes as part of the CSE 416 lab course. All rights reserved by the authors unless a license file is added.


