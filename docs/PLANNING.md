# Meeting Minutes System — Planning Document

## 1. Overview

A web application for uploading, storing, and managing meeting minutes with rich metadata. Users submit minutes in common formats (Word, PDF, Excel, etc.) or via cloud URLs; admins view and manage submissions with Google OAuth protection in production.

---

## 2. Goals & Success Criteria

| Goal | Success Criteria |
|------|------------------|
| Simple submission | Users can submit minutes via drag-and-drop or URL in &lt; 2 minutes |
| Reliable storage | All files and metadata stored in MySQL; files on disk or object storage |
| Secure admin | Admin area protected by Google OAuth in production; no auth in dev |
| Usable admin UI | Admins see all metadata in box layout with file/link icons and 10-item pagination |

---

## 3. Architecture Summary

```
┌─────────────────────────────────────────────────────────────────┐
│                        Frontend (HTML/JS/Tailwind)               │
│  • Public: submission form (metadata + file/URL)                 │
│  • Admin: list view, pagination, view file/link                  │
└───────────────────────────────┬─────────────────────────────────┘
                                │
┌───────────────────────────────▼─────────────────────────────────┐
│                        PHP Backend                               │
│  • Public: submit.php, validation, file upload / URL handling    │
│  • Admin: list.php, auth (Google OAuth in prod, skip in dev)     │
│  • Config: env-based DB + Google credentials                     │
└───────────────────────────────┬─────────────────────────────────┘
                                │
┌───────────────────────────────▼─────────────────────────────────┐
│  MySQL (metadata)  │  File storage (uploads) or cloud URL only    │
└─────────────────────────────────────────────────────────────────┘
```

- **Environments:** Development (local DB, no auth) and Production (remote DB, Google OAuth for admin).
- **Credentials:** DB and Google config from environment / config files (see PRD). Google Client Secret JSON already present in project root.

---

## 4. Phases & Milestones

### Phase 1 — Foundation (Week 1)

| # | Task | Deliverable |
|---|------|-------------|
| 1.1 | Project setup: directory structure, Composer if needed, Tailwind build | `/public`, `/src`, `/config`, `/uploads`, build pipeline |
| 1.2 | Environment config: `.env.example`, config loader for DB (dev/prod) | Safe config; no secrets in repo |
| 1.3 | MySQL schema: `meetings` (and `meeting_files` if multiple files) | Migration or SQL script; tables created |
| 1.4 | Basic routing: public index (form), admin index (list), config-based base URL | URLs work locally and in prod |

**Milestone:** App runs locally; DB connects; placeholder pages load.

---

### Phase 2 — Submission Flow (Week 2)

| # | Task | Deliverable |
|---|------|-------------|
| 2.1 | Public submission form: all metadata fields, file input (drag-and-drop), URL field, validation (client + server) | Form in HTML/JS; validation rules in PHP |
| 2.2 | File upload handler: store file safely (rename, restrict type/size), save path in DB; support re-upload (update same meeting record) | Uploaded files in `/uploads` or equivalent; DB stores path |
| 2.3 | URL-only path: store URL in DB when user provides link instead of file | Optional `file_path` vs `document_url` in schema |
| 2.4 | Success/error feedback and optional “submit another” | UX complete for submitter |

**Milestone:** Users can submit minutes (file or URL) and re-upload; data persists in MySQL.

---

### Phase 3 — Admin List & Pagination (Week 3)

| # | Task | Deliverable |
|---|------|-------------|
| 3.1 | Admin list page: fetch meetings from DB, display in box/card layout (all metadata) | Single admin list view |
| 3.2 | File vs link: show “view file” icon (open or download) when `file_path` set; show “link” icon pointing to `document_url` when URL set | Icons and links working |
| 3.3 | Pagination: 10 items per page, prev/next or page numbers | Pagination implemented in PHP/HTML |
| 3.4 | Environment-based auth: in development, skip auth; in production, require Google OAuth before showing admin | Dev = no login; Prod = Google login |

**Milestone:** Admin can see all minutes in boxes with correct icons and pagination; prod admin protected by Google.

---

### Phase 4 — Polish & Deploy (Week 4)

| # | Task | Deliverable |
|---|------|-------------|
| 4.1 | Tailwind styling: form and admin cards responsive and consistent | UI polished |
| 4.2 | Error handling and logging (upload failures, DB errors) | Clear errors; no sensitive data in logs |
| 4.3 | Production checklist: env vars, file permissions, OAuth redirect URIs | Documented; deploy to server |
| 4.4 | Optional: re-upload UI in admin (link to “replace file” for existing meeting) | If time permits |

**Milestone:** Application ready for production use and handoff.

---

## 5. Risk & Mitigation

| Risk | Mitigation |
|------|------------|
| Large or malicious uploads | Limit file size; validate MIME/extension; store outside web root or in private bucket |
| Credentials in repo | All secrets in `.env` or config; `.env` in `.gitignore`; PRD uses placeholders |
| Google OAuth redirect mismatch | Document exact redirect URIs for local and production in PRD and deployment notes |
| DB connection differences (port, host) | Single config layer that reads from env (e.g. `APP_ENV`, `DB_*`) |

---

## 6. Out of Scope (V1)

- User accounts for submitters (anonymous or simple form only).
- Full-text search inside documents.
- Editing metadata after submit (can be added later).
- Email notifications or approval workflows.
- Native mobile app (web only).

---

## 7. Document References

- **Product details, data model, and config:** [PRD.md](./PRD.md)
- **Credentials:** Stored in environment/config only; see PRD “Configuration” section. Do not commit secrets.
