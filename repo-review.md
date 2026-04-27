# Library Facilities Booking System — Full Code Review Report

Generated: 2026-04-21
Repository: `Library-Facilities-Booking-System`

> Note: This report is based on a static review of the repository files (PHP/MySQL/HTML/CSS/JS). Items below include actionable edits and verification steps. Prioritize **Security** and **Correctness** items first.

---

## 1) Architecture Summary

### Main Components & Structure

```
Library-Facilities-Booking-System/
├── admin/              # Admin dashboard: manage users, facilities, reports, bookings
├── auth/               # Login/register/logout/forgot password
├── assets/             # CSS/JS/images
├── config/             # config.php (constants + headers), database.php (PDO + schema/migrations/seeds)
├── faculty/            # Faculty dashboard + booking workflow
├── includes/           # header/footer/navbar + shared helper functions
├── logs/               # Application logs (protected via .htaccess)
├── student/            # Student dashboard + booking workflow
├── uploads/            # Uploaded request letters
├── index.php           # Landing/login
└── setup.php           # One-time setup + seeding + migration-style updates
```

### Key Files
- `config/config.php` — constants (`BASE_URL`), session config, security headers, logs directory protection
- `config/database.php` — PDO connection, table creation, light migrations (ALTER TABLE list), seed data
- `includes/functions.php` — auth helpers, CSRF helpers, output escaping `e()`, audit logging helpers
- `admin/manage_facilities.php`, `admin/edit_facility.php`, `admin/add_facility.php` — facility management

---

## 2) Critical / High Security Issues

### 2.1 SQL injection / unsafe SQL patterns
Most database access correctly uses prepared statements. However, any direct interpolation should be removed.

**Example risk:**
- `notifications.php` uses a raw SQL string with `$uid` interpolated.

**Fix (exact edit):**
**File:** `notifications.php`

Replace:
```php
$count = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id=$uid AND is_read=0")->fetchColumn();
```
With:
```php
$stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$stmt->execute([$uid]);
$count = (int)$stmt->fetchColumn();
```

### 2.2 CSRF consistency
You already have CSRF helpers:
- `csrfToken()`, `csrfField()`, `verifyCsrf()` and `verifyCsrfGet()` in `includes/functions.php`.

**Rule:** every POST form must include `<?= csrfField() ?>` and POST handlers should call `verifyCsrf()`.

### 2.3 Session cookie security
In `config/config.php`, cookie security flags must be correct for production.

**Fix:**
- In production with HTTPS, set `secure => true`.

**File:** `config/config.php`
Replace:
```php
'secure' => false,
```
With:
```php
'secure' => true,
```

### 2.4 File upload hardening
Booking flows allow uploads (request letters). Ensure server-side validation:
- extension + mime type
- max size
- random file naming
- store outside webroot if possible

**Suggested validation snippet (add where file is handled):**
```php
$allowedExt = ['pdf','doc','docx','jpg','jpeg','png'];
$maxSize = 2 * 1024 * 1024; // 2MB
$ext = strtolower(pathinfo($_FILES['request_letter']['name'] ?? '', PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt, true)) {
    $errors[] = 'Invalid file type.';
}
if (($_FILES['request_letter']['size'] ?? 0) > $maxSize) {
    $errors[] = 'File too large (max 2MB).';
}
```

### 2.5 XSS output escaping
You have `e()` for escaping. Ensure **all** user-controlled output uses it (including logs/notifications/details).

Example fix:
```php
<?= e($row['details'] ?? '') ?>
```

---

## 3) Correctness / Functional Bugs

### 3.1 Facilities table missing columns used by admin UI
If you see:
> `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'description' in 'field list'`

Root cause: `admin/edit_facility.php` and `admin/add_facility.php` write `description` / `equipment`, but the DB schema lacked those columns.

**Fix:** add `description` and `equipment` columns to `facilities` table (both CREATE TABLE and ALTER TABLE migration list).

**Files to ensure include this:**
- `config/database.php` (CREATE TABLE facilities + ALTER TABLE list)

### 3.2 Facility edit save failing (CSRF / action / time range)
Fixes recommended:
- Add `verifyCsrf()` in POST handler
- Ensure the form includes `csrfField()`
- Use explicit `action="?id=..."` to preserve querystring on POST
- Expand time dropdown range to cover both CL (08:00–18:00) and Morelos (07:00–17:00)

---

## 4) UX / Frontend & Accessibility

### 4.1 Native time input “gap” issue
Browsers often show a visual separator between minutes (e.g., 00 and 01) in native `<input type="time">`. Replacing with `<select>` time slots is the correct approach.

### 4.2 Form accessibility
- Ensure labels are linked (`for` + matching `id`) where possible.
- Provide clear error messages close to fields.

---

## 5) Maintainability Recommendations

1. **Reduce duplication** between student and faculty booking flows:
   - Extract common booking validation into a shared include (e.g., `includes/booking.php`).
2. **Centralize facility metadata** (labels/types/locations/hours) into constants or DB-driven configuration.
3. Keep DB migrations in one place:
   - `config/database.php` currently acts as both bootstrap + migration runner; ensure it remains idempotent.

---

## 6) Manual Verification Steps (XAMPP)

### Verify admin facility edit/save
1. Start Apache + MySQL in XAMPP.
2. Visit: `http://localhost/Library-Facilities-Booking-System/`
3. Log in as admin (seeded account): `admin@library.com` / `Admin@123`
4. Admin → Manage Facilities → Edit
5. Change type/location/time/description → Save
6. Confirm redirect to Manage Facilities and flash message appears.

### Verify notifications count query is safe
1. Open notifications UI.
2. Confirm notifications count still loads.
3. Confirm all queries are prepared statements.

---

## 7) Prioritized TODO List (SQL-friendly)

- `sql-prepared-notifications` — Convert remaining raw SQL to prepared statements (notifications count, any dynamic filters)
- `csrf-audit-admin-forms` — Ensure every POST has `csrfField()` and every handler uses `verifyCsrf()`
- `upload-validation` — Add server-side validation for uploads (ext/mime/size/random name)
- `xss-audit-escape` — Audit output escaping; wrap all user-controlled output with `e()`
- `db-facilities-columns` — Ensure `facilities.description` and `facilities.equipment` exist in schema + migrations
- `cascade-fk` — Add/verify FK constraints with ON DELETE CASCADE where appropriate
- `dedupe-booking` — Refactor shared booking logic into includes
- `a11y-forms` — Improve label/ID linkage and error message UX

---

## Top 10 Actionable Fixes (Quick List)

1. `notifications.php` — parameterize notification count query
2. `config/config.php` — set session cookie `secure=true` for production/HTTPS
3. Booking upload handlers — validate extension/mime/size; randomize filenames
4. Admin/Reports/Logs — escape all user-controlled output with `e()`
5. `config/database.php` — ensure facilities columns include `description` + `equipment`
6. Ensure all admin POST endpoints use CSRF checks
7. Add/verify DB constraints (FKs + ON DELETE CASCADE)
8. Refactor duplicated booking code
9. Improve a11y for forms (labels, focus, error messaging)
10. Add a minimal manual test checklist (login/book/approve/export)
