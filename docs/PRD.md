# Meeting Minutes System — Product Requirements Document (PRD)

## 1. Product Summary

**Product name:** Meeting Minutes System  
**Version:** 1.0 (MVP)  
**Stack:** PHP, MySQL, JavaScript, HTML, Tailwind CSS  
**Purpose:** Allow users to upload meeting minutes (files or cloud URLs) with metadata; provide an admin view to browse all submissions with file/link access and pagination. Admin protected by Google OAuth in production; authentication disabled in development.

---

## 2. User Roles

| Role | Description | Access |
|------|-------------|--------|
| **Submitter** | Anyone submitting meeting minutes | Public submission form only |
| **Admin** | Person managing/viewing all minutes | Admin list (after Google OAuth in production) |

---

## 3. Functional Requirements

### 3.1 Public Submission (Submitter)

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-1 | Display a form with metadata fields and either file upload or URL input | P0 |
| FR-2 | **Metadata fields:** Meeting chair (First name, Last name, Email), Campus Name, Ministry, Pastor-in-charge, Attendees (optional, if not in the minutes), Meeting type (In person / Online), Short description of the meeting | P0 |
| FR-3 | **Document input:** (a) Drag-and-drop area for file upload, (b) Optional field to enter a URL if the document is shared in the cloud. At least one of file or URL must be provided (or allow one as primary and the other as optional—clarify: for MVP, “file OR URL” is sufficient) | P0 |
| FR-4 | Accept common word processor and document formats: e.g. DOC, DOCX, PDF, XLS, XLSX, and other common formats (list allowed MIME types / extensions in config) | P0 |
| FR-5 | Validate and store metadata and file path or URL in MySQL; store uploaded files on server (or link only when URL is used) | P0 |
| FR-6 | Allow user to **re-upload** meeting minutes (update existing record: same meeting identified by id or by business key; for MVP, “re-upload” can mean a dedicated flow or admin action to replace file/URL for a given meeting) | P1 |
| FR-7 | Show clear success/error messages after submit | P0 |

### 3.2 Admin View (Admin)

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-8 | Admin page shows all meeting minutes in a **box/card** layout | P0 |
| FR-9 | Each box displays **all metadata** for that meeting (chair, campus, ministry, pastor, attendees, meeting type, description) | P0 |
| FR-10 | If the minutes are stored as a **file:** show a “view file” icon that opens or downloads the file | P0 |
| FR-11 | If the minutes are stored as a **URL:** show a link icon that points to the document URL | P0 |
| FR-12 | **Pagination:** 10 boxes (records) per page; provide previous/next or page numbers | P0 |
| FR-13 | **Auth:** In **production,** protect admin page with **Google OAuth**; in **development,** ignore authentication (no login required) | P0 |

### 3.3 Re-upload (Clarification)

- **Option A:** Submitter has a “re-upload” link (e.g. token in email) to replace file/URL for a specific submission.  
- **Option B:** Admin can replace file/URL for a meeting from the admin list.  
- **MVP:** Implement at least one of Option A or B; document choice in implementation. PRD assumes “system allows re-upload” as P1; design can be submitter or admin flow.

---

## 4. Non-Functional Requirements

| ID | Requirement |
|----|-------------|
| NFR-1 | Use **Tailwind CSS** for all front-end styling; responsive layout for form and admin list |
| NFR-2 | Use **PHP** for server-side logic and **MySQL** for persistence |
| NFR-3 | Use **JavaScript** for client-side validation and drag-and-drop UX |
| NFR-4 | Do not store credentials in source code; use environment variables or a config file (e.g. `.env`) that is not committed |
| NFR-5 | Support two environments: **development** (local DB, no auth) and **production** (remote DB, Google OAuth for admin) |

---

## 5. Data Model

### 5.1 Core Table: `meetings` (or equivalent)

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT, PK, AUTO_INCREMENT | Primary key |
| `chair_first_name` | VARCHAR(255) | Meeting chair first name |
| `chair_last_name` | VARCHAR(255) | Meeting chair last name |
| `chair_email` | VARCHAR(255) | Meeting chair email |
| `campus_name` | VARCHAR(255) | Campus name |
| `ministry` | VARCHAR(255) | Ministry |
| `pastor_in_charge` | VARCHAR(255) | Pastor-in-charge |
| `attendees` | TEXT, nullable | Attendees (if not in minutes) |
| `meeting_type` | ENUM('in_person','online') or VARCHAR | In person vs Online |
| `description` | TEXT | Short description of meeting |
| `document_type` | ENUM('file','url') or VARCHAR | Whether minutes are file or URL |
| `file_path` | VARCHAR(512), nullable | Server path to uploaded file (null if URL) |
| `document_url` | VARCHAR(1024), nullable | Cloud URL (null if file) |
| `created_at` | DATETIME | When record was created |
| `updated_at` | DATETIME | Last update (e.g. on re-upload) |

- **Re-upload:** When replacing document for same meeting, update `file_path` or `document_url` (and clear the other if switching from file to URL or vice versa) and `updated_at`.

### 5.2 Optional

- **`allowed_extensions` / `allowed_mime_types`:** Maintain in config, not necessarily as DB table.  
- If multiple files per meeting are required later, add a `meeting_files` table with `meeting_id`, `file_path` or `url`, and ordering.

---

## 6. Configuration & Credentials

**Important:** Do not commit real credentials. Use `.env` (or similar) and `.gitignore` for all secrets. This section describes **what** to configure, not the actual values.

### 6.1 Environment Variables (recommended)

- `APP_ENV` = `development` | `production`
- `APP_URL` = base URL of the app (for redirects)
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`
- Google OAuth (for production admin):
  - `GOOGLE_CLIENT_ID`
  - `GOOGLE_CLIENT_SECRET` (or path to client secret JSON)
  - Redirect URI(s) registered in Google Cloud Console for local and production

### 6.2 Database Connections (reference only — store in env)

**Development (local):**

- Host: `localhost`
- Port: `8889`
- Database: `crossp11_db1`
- User / Password: (e.g. root/root — set via env)

**Production:**

- Host: (e.g. `35.215.126.244`)
- Port: `3306`
- Database: (e.g. `crossp11_db1`)
- User / Password: (set via env only)

*Note: The PRD does not paste production credentials; they are supplied at deploy time into environment or config.*

### 6.3 Google OAuth

- **Client ID** and **Client Secret** (or path to existing `client_secret_*.json` in project) should be loaded from environment or config.
- In production, set redirect URI in Google Cloud Console to the admin callback URL (e.g. `https://yourdomain.com/admin/callback.php`).
- In development, either skip OAuth (per NFR) or use a local redirect (e.g. `http://localhost/.../admin/callback.php`) if you test login locally.

---

## 7. UI/UX Summary

### 7.1 Public Form

- One clear section for **metadata** (all fields listed in FR-2).
- One section for **document:**
  - Drag-and-drop zone for files + traditional file input as fallback.
  - Optional “Or paste a link” URL input.
- Buttons: Submit; optional “Submit another” after success.
- Validation messages inline or at top; server-side validation required.

### 7.2 Admin List

- **Layout:** Grid or list of boxes/cards; each card = one meeting.
- **Content per card:** All metadata (chair name, chair email, campus, ministry, pastor, attendees, meeting type, description).
- **Actions:**  
  - If document is file: “View file” icon → open/download.  
  - If document is URL: link icon → open URL in new tab.
- **Pagination:** “Previous” / “Next” and/or page numbers; 10 items per page.

---

## 8. Acceptance Criteria (Summary)

- [ ] Submitter can fill metadata and upload a file **or** provide a URL; submission is saved.
- [ ] Submitter can re-upload (replace document) for a meeting (by defined flow).
- [ ] Admin sees all meetings in box layout with full metadata and correct file/link icon and pagination (10 per page).
- [ ] In production, admin page requires Google OAuth; in development, it does not.
- [ ] Credentials and environment-specific settings come from config/env only; no secrets in repo.

---

## 9. References

- **Planning (phases, milestones, risks):** [PLANNING.md](./PLANNING.md)
- **Google OAuth:** Use existing `client_secret_*.json` in project; load via env/path in production.
- **Tailwind:** Use for all styling; ensure build step and correct paths for CSS in PHP templates.
