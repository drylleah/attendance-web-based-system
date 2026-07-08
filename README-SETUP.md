# Lorma Colleges — Attendance System (Laravel)

This is the Laravel rebuild of the original Node.js / Express attendance system.  
All features, routes, UI, and database structure are preserved exactly.  
The existing frontend JavaScript (`dashboard.js`, `timerecord.js`, `settings.js`, `attendance.js`) is reused without modification — it still calls the same `/api/*` endpoints.

---

## Requirements

| Dependency | Minimum Version |
|---|---|
| PHP | 8.1 |
| Composer | 2.x |
| MySQL | 5.7 or 8.x |
| Web server | Apache (with `mod_rewrite`) or `php artisan serve` |

---

## Step-by-Step Installation

### 1. Install PHP Dependencies

Open a terminal inside the `attendance_laravel/` folder and run:

```bash
composer install
```

> If you don't have Composer, download it from https://getcomposer.org

---

### 2. Create Your `.env` File

Copy the example file and open it for editing:

```bash
cp .env.example .env
```

On Windows:
```cmd
copy .env.example .env
```

Then edit `.env` and set your database credentials:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=attendance_db
DB_USERNAME=root
DB_PASSWORD=
```

> These match the original `src/db.js` settings. Change `DB_PASSWORD` if your MySQL root has a password.

---

### 3. Generate the Application Key

```bash
php artisan key:generate
```

This writes a `APP_KEY=base64:...` value into your `.env`. **Required before the app will start.**

---

### 4. Create the Database

Open your MySQL client (phpMyAdmin, MySQL Workbench, or command line) and create the database:

```sql
CREATE DATABASE attendance_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

> If you already have the database from the Node.js version, skip this step — the existing tables and data will be reused.

---

### 5. Run Migrations

This creates all 7 tables (same schema as the original `seed.js`):

```bash
php artisan migrate
```

> Safe to run on an existing database — Laravel only creates tables that don't already exist.

---

### 6. Seed the Admin Account

```bash
php artisan db:seed
```

This creates:

| Field | Value |
|---|---|
| Username | `admin` |
| Password | `Att@2024#Xz9!` |
| Email | `admin@lorma.edu` |
| Role | `admin` |

> Safe to run multiple times — it skips creation if the admin already exists.

---

### 7. Start the Development Server

```bash
php artisan serve
```

The app will be available at: **http://localhost:8000**

---

## Page URLs

| Page | URL | Original |
|---|---|---|
| Login | `http://localhost:8000/` | `index.html` |
| Dashboard (Live Attendance) | `http://localhost:8000/dashboard` | `dashboard.html` |
| Time Records | `http://localhost:8000/timerecord` | `timerecord.html` |
| Settings | `http://localhost:8000/settings` | `settings.html` |
| RFID Scanner Kiosk | `http://localhost:8000/attendance` | `attendance.html` |

---

## API Endpoints (unchanged from Node.js version)

All endpoints remain identical so existing RFID hardware / integrations need no changes.

### Auth
| Method | URL | Description |
|---|---|---|
| POST | `/api/auth/login` | Login with username or email |
| POST | `/api/auth/logout` | Logout |
| GET | `/api/auth/me` | Check session |

### Attendance (Live)
| Method | URL | Description |
|---|---|---|
| GET | `/api/attendance` | List all (supports `?search=`) |
| POST | `/api/attendance` | Add record |
| PUT | `/api/attendance/{id}` | Update record |
| DELETE | `/api/attendance` | Bulk delete by IDs |
| DELETE | `/api/attendance/clear` | Clear all |

### Time Records
| Method | URL | Description |
|---|---|---|
| GET | `/api/timerecord` | List (search, date range, month, pagination) |
| POST | `/api/timerecord` | Manually add record |
| PUT | `/api/timerecord/{id}` | Update record |
| DELETE | `/api/timerecord/{id}` | Delete record |
| POST | `/api/timerecord/save` | Save attendance → time_records + clear |

### Settings
| Method | URL | Description |
|---|---|---|
| GET/PUT | `/api/settings/profile` | Get / update profile |
| PUT | `/api/settings/avatar` | Update profile picture (base64) |
| PUT | `/api/settings/password` | Change password |
| GET/PUT | `/api/settings/datetime` | Get / set date-time mode |
| PUT | `/api/settings/datetime/triggered` | Mark manual schedule as triggered |
| GET | `/api/settings/activity-logs` | List logs (search, filter, paginate) |
| DELETE | `/api/settings/activity-logs` | Clear all logs |
| POST | `/api/settings/activity-logs/bulk-delete` | Delete specific log IDs |
| POST | `/api/settings/activity-logs/archive` | Archive old logs |
| GET | `/api/settings/activity-logs/export` | Export as JSON or CSV |

### RFID
| Method | URL | Auth | Description |
|---|---|---|---|
| POST | `/api/rfid/scan` | **Public** | Process a scan (time-in / time-out) |
| GET | `/api/rfid/cards` | Login required | List registered students |
| POST | `/api/rfid/cards` | Login required | Register a student |
| PUT | `/api/rfid/cards/{idNumber}` | Login required | Update student |
| DELETE | `/api/rfid/cards/{idNumber}` | Login required | Remove student |

### Incidents
| Method | URL | Description |
|---|---|---|
| GET | `/api/incidents` | List reports |
| POST | `/api/incidents` | Submit new report |
| GET | `/api/incidents/{id}` | View single report |
| PUT | `/api/incidents/{id}` | Update status / remarks |
| DELETE | `/api/incidents/{id}` | Delete report |

---

## Project Structure

```
attendance_laravel/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php         ← login, logout, me
│   │   │   ├── AttendanceController.php   ← CRUD + clear
│   │   │   ├── TimeRecordController.php   ← CRUD + save workflow
│   │   │   ├── SettingsController.php     ← profile, avatar, password,
│   │   │   │                                 datetime, activity logs
│   │   │   ├── IncidentController.php     ← incident reports CRUD
│   │   │   └── RfidController.php         ← scan (public) + card mgmt
│   │   └── Middleware/
│   │       └── RequireLogin.php           ← session guard (replaces requireLogin())
│   ├── Models/
│   │   ├── User.php
│   │   ├── Attendance.php
│   │   ├── TimeRecord.php
│   │   ├── DatetimeConfig.php
│   │   ├── ActivityLog.php
│   │   ├── RfidCard.php
│   │   └── IncidentReport.php
│   ├── Providers/
│   │   └── AppServiceProvider.php
│   └── Services/
│       └── ActivityLogger.php             ← replaces src/logger.js
├── bootstrap/
│   ├── app.php                            ← middleware + route registration
│   └── providers.php
├── config/
│   ├── app.php
│   ├── database.php
│   ├── logging.php
│   └── session.php                        ← 480-minute lifetime (8 hours)
├── database/
│   ├── migrations/                        ← 7 migrations (exact original schema)
│   └── seeders/
│       └── DatabaseSeeder.php             ← seeds admin account
├── public/
│   ├── index.php                          ← Laravel front controller
│   ├── .htaccess
│   ├── css/   ← all 5 original CSS files (copied verbatim)
│   ├── js/    ← all 5 original JS files (app.js has 1 line patched)
│   └── images/
│       └── lormaLogo.png
├── resources/
│   └── views/
│       ├── login.blade.php
│       ├── dashboard.blade.php
│       ├── timerecord.blade.php
│       ├── attendance.blade.php           ← RFID kiosk
│       └── settings.blade.php
└── routes/
    ├── api.php                            ← all /api/* routes
    ├── web.php                            ← page routes (/, /dashboard, etc.)
    └── console.php
```

---

## Express → Laravel Component Map

| Express (Node.js) | Laravel |
|---|---|
| `express()` app | `bootstrap/app.php` |
| `express-session` | Laravel session (file driver, 480 min) |
| `express.json({ limit: '5mb' })` | Default JSON middleware (CSRF excluded for `/api/*`) |
| `req.session.userId` | `$request->session()->get('userId')` |
| `bcrypt.compare()` / `bcrypt.hash()` | `Hash::check()` / `Hash::make()` |
| `src/db.js` (mysql2 pool) | Eloquent ORM / `DB` facade |
| `src/logger.js` `logActivity()` | `App\Services\ActivityLogger::log()` |
| Route `requireLogin()` middleware | `App\Http\Middleware\RequireLogin` |
| `src/routes/auth.js` | `AuthController` |
| `src/routes/attendance.js` | `AttendanceController` |
| `src/routes/timerecord.js` | `TimeRecordController` |
| `src/routes/settings.js` | `SettingsController` |
| `src/routes/incidents.js` | `IncidentController` |
| `src/routes/rfid.js` | `RfidController` |
| `public/*.html` | `resources/views/*.blade.php` |
| `public/css/`, `public/js/`, `public/images/` | `public/css/`, `public/js/`, `public/images/` |
| `seed.js` | `DatabaseSeeder.php` |

---

## Deploying on Apache (production)

1. Point your Apache `DocumentRoot` to the `attendance_laravel/public/` directory.
2. Make sure `mod_rewrite` is enabled.
3. Set `APP_ENV=production` and `APP_DEBUG=false` in `.env`.
4. Run `php artisan config:cache` and `php artisan route:cache`.
5. Give the web server write access to `storage/` and `bootstrap/cache/`:

```bash
chmod -R 775 storage bootstrap/cache
```

---

## Troubleshooting

**"No application encryption key has been specified"**  
→ Run `php artisan key:generate`

**500 error on all pages**  
→ Check `storage/logs/laravel.log` for details.  
→ Ensure `storage/` and `bootstrap/cache/` are writable.

**Session not persisting / keeps logging out**  
→ Confirm `SESSION_DRIVER=file` in `.env`.  
→ Confirm `storage/framework/sessions/` directory exists and is writable.

**API returns 419 (CSRF token mismatch)**  
→ The `bootstrap/app.php` already excludes `/api/*` from CSRF checks. If this appears, ensure you are not changing that configuration.

**Database connection error**  
→ Verify `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` in `.env`.  
→ Make sure MySQL is running and the `attendance_db` database exists.
