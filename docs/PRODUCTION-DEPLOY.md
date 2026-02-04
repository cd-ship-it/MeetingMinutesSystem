# Production deployment checklist

After `git pull` on the production server, do the following.

---

## 1. Create or update `.env`

`.env` is not in the repo. Create it in the **project root** (same level as `config/`).

**Required:**

| Variable | Example (production) |
|----------|------------------------|
| `APP_ENV` | `production` |
| `APP_URL` | `https://your-domain.com/MeetingMinutesSystem/public` (no trailing slash) |
| `DB_HOST` | `35.215.126.244` |
| `DB_PORT` | `3306` |
| `DB_NAME` | `crossp11_db1` |
| `DB_USER` | `u3imfaackr8jh` |
| `DB_PASSWORD` | (your production DB password) |

**Optional:**

- **Google OAuth** (for admin sign-in in production): set `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`, or put the JSON file on the server and set `GOOGLE_CREDENTIALS_PATH=/path/to/client_secret_*.json`.
- **OpenAI** (if AI summary is on): set `OPENAI_API_KEY=sk-...`.
- **AI summary on/off:** `USE_AI_FOR_MINUTES_SUMMARY=1` or `0` (default `1`).

Copy from `.env.example` and fill in production values.

---

## 2. Ensure `.env` is not web-accessible

- Document root must be the **`public/`** directory (e.g. Apache `DocumentRoot` or nginx `root` pointing to `.../MeetingMinutesSystem/public`).
- So the project root (where `.env` and `config/` live) is **above** the web root; `.env` is never served.

---

## 3. Make `uploads/` writable

The app saves uploaded files to `uploads/` (in project root).

```bash
chmod 755 uploads
# or, if the web server runs as e.g. www-data:
chown -R www-data:www-data uploads
chmod 755 uploads
```

---

## 4. Make `logs/` writable

The app writes all logging to **`logs/app.log`** (in project root). Create the folder and allow the web server to write:

```bash
mkdir -p logs
chmod 755 logs
# or, if the web server runs as e.g. www-data:
chown -R www-data:www-data logs
chmod 755 logs
```

---

## 5. Web server points to `public/`

- **Apache:** `DocumentRoot` = `.../MeetingMinutesSystem/public`; allow `.htaccess` if you use it.
- **Nginx:** `root` = `.../MeetingMinutesSystem/public`; PHP requests go to `public/index.php` (or your front controller).

All URLs should go through `public/` (e.g. `https://your-domain.com/.../public/` or a vhost that maps to `public/`).

---

## 6. PHP

- PHP 7.4+ (or 8.x recommended).
- Extensions: `pdo_mysql`, `json`, `mbstring`, `fileinfo` (and usuals like `curl` if using OpenAI).

---

## 7. (Optional) Remove or restrict test script

`public/test-generate-summary.php` is for debugging. In production either:

- Delete it, or  
- Restrict access (e.g. by IP or auth) so it is not publicly callable.

---

## 8. Quick verification

1. Open `APP_URL` in a browser → submission form loads.
2. Submit a test meeting (with file or URL) → success message and “You can close this window now.”
3. Visit `APP_URL/admin/` → admin list (and Google sign-in if configured).
4. Confirm uploads appear under `uploads/` and in the admin list.

---

## Summary checklist

- [ ] `.env` created/updated with production values (APP_ENV, APP_URL, DB_*, etc.)
- [ ] Document root is `public/` so `.env` and `config/` are not web-accessible
- [ ] `uploads/` exists and is writable by the web server
- [ ] `logs/` exists and is writable (app writes to `logs/app.log`)
- [ ] Web server and PHP configured (PHP 7.4+, PDO MySQL, etc.)
- [ ] Google OAuth set in `.env` or via credentials file if using admin sign-in
- [ ] Optional: restrict or remove `public/test-generate-summary.php`
- [ ] Smoke test: submit form, check admin list and uploads

---

## Troubleshooting: "Could not save. Please try again."

This message appears when the **database insert** fails. The app now logs the real error so you can fix it.

**1. Check the app log**

All app errors are written to **`logs/app.log`** in the project folder (not the PHP/server error log).

```bash
tail -50 /path/to/MeetingMinutesSystem/logs/app.log
```

Search for lines like `[submit]` followed by the actual error message and stack trace.

**2. Common causes**

| Cause | What to do |
|-------|------------|
| **DB connection** (wrong host/port/user/password/DB name) | Fix `.env`: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`. Ensure production DB allows connections from the app server (firewall, Cloud SQL auth, etc.). |
| **Table `meetings` does not exist** | Run the `CREATE TABLE` from `docs/schema.sql` (Option A) in your production database. |
| **Column mismatch** (table has different columns than the app expects) | Align table with `docs/schema.sql`, or run the `ALTER TABLE` (Option B) only if you already have the base table and just need the extra columns. |
| **`uploads/` not writable** | The file is saved *before* the insert; if the insert fails, the file may have been written. So this is usually a DB/table issue. Still ensure `uploads/` is writable. |

**3. Quick DB check**

From the production server (or a host that can reach the DB):

```bash
mysql -h 35.215.126.244 -P 3306 -u u3imfaackr8jh -p crossp11_db1 -e "SHOW TABLES; DESCRIBE meetings;"
```

Confirm `meetings` exists and has the columns used in the app (e.g. `chair_first_name`, `chair_last_name`, …, `document_type`, `file_path`, `document_url`, `created_at`).
