# Meeting Minutes System

Web application for submitting and viewing meeting minutes with metadata. Built with PHP, MySQL, HTML, JavaScript, and Tailwind CSS.

## Features

- **Public form:** Submit meeting minutes (file upload or cloud URL) with metadata: chair name/email, campus, ministry, pastor-in-charge, attendees, meeting type (in person/online), description.
- **AI summary:** When a file is uploaded, the file is sent to OpenAI; the Assistants API reads and summarizes it (3–5 bullets in English; documents may be in Chinese). No local text extraction—OpenAI reads PDF, Word, etc. directly. Summary appears in the short description field; user can edit or re-generate. 10s timeout with Retry.
- **Admin list:** View all submissions in card layout with file/link icons and pagination (10 per page).
- **Auth:** In development, admin has no login. In production, admin is protected by Google OAuth.

## Requirements

- PHP 7.4+ (with PDO MySQL)
- MySQL (local: port 8889; production: port 3306)
- Web server (Apache/Nginx) or PHP built-in server

## Setup

### 1. Environment

Copy `.env.example` to `.env` in the project root and adjust:

```bash
cp .env.example .env
```

- **APP_ENV:** `development` or `production`
- **APP_URL:** Base URL of the app (e.g. `http://localhost:8888/MeetingMinutesSystem/public`)
- **DB_***:** Database connection (see PRD for local/production values)
- **OPENAI_API_KEY:** (optional) For AI-generated meeting summary when a file is uploaded. Set in `.env`.
- **USE_AI_FOR_MINUTES_SUMMARY:** Set to `0` or `false` to disable AI summary; omit or set to `1`/`true` to enable (default: on).

### 2. Database

Database `crossp11_db1` and table `meetings` should already exist (see docs/PRD.md for schema). If not, create the database and run the table creation SQL.

### 3. Uploads

The `uploads/` directory must exist and be writable by the web server. It is created automatically on first upload if missing.

### 4. AI summary (optional)

When `USE_AI_FOR_MINUTES_SUMMARY` is enabled (default), the uploaded file is sent to OpenAI and the model summarizes it. Set `OPENAI_API_KEY` in `.env`. To disable AI summary entirely, set `USE_AI_FOR_MINUTES_SUMMARY=0` or `false` in `.env`; the short description field becomes a plain text area with no AI UI.

### 5. Run locally

**Option A — PHP built-in server:**

```bash
cd public
php -S localhost:8888
```

Then open `http://localhost:8888` and set `APP_URL=http://localhost:8888` in `.env` (no path if document root is `public/`).

**Option B — Existing server (e.g. MAMP):**

Point document root to `public/` and set `APP_URL` to the full URL (e.g. `http://localhost:8888/MeetingMinutesSystem/public`).

## URLs

- **Submit form:** `{APP_URL}/index.php`
- **Admin list:** `{APP_URL}/admin/`
- **Google OAuth callback (production):** `{APP_URL}/admin/oauth-callback.php`

Ensure these callback URLs are registered in Google Cloud Console for production (and local if testing OAuth).

## Project structure

```
MeetingMinutesSystem/
├── config/          # config.php, db.php
├── docs/            # PLANNING.md, PRD.md
├── public/          # Web document root
│   ├── index.php    # Submission form
│   ├── submit.php   # Form handler
│   └── admin/       # Admin list, OAuth, view-file, logout
├── uploads/         # Uploaded files (do not commit contents)
├── .env.example
├── .gitignore
└── README.md
```

## Production checklist

- Set `APP_ENV=production` and `APP_URL` to the production base URL (HTTPS).
- Set production DB credentials in `.env`.
- Configure Google OAuth: add production redirect URI in Google Cloud Console (`{APP_URL}/admin/oauth-callback.php`).
- Ensure `uploads/` is writable and outside public URL if desired; keep `.env` out of the document root.
- Use HTTPS and secure cookies in production.

## Security

- Do not commit `.env` or real credentials.
- In production, use HTTPS and set correct `APP_URL`.
- Uploaded files are stored with random prefixes; only admins can view them via `view-file.php?id=...`.
