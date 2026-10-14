# JobProof — Build Progress

Source of truth: `SPEC.md`. Laravel app at repo root (fits the staging subfolder deploy pattern); Android app will live in `android/`.

## Phase 0 — Foundation

- [x] JobProof welcome page (React, Login/Register links) + SVG favicon + checkmark logo (APP_NAME=JobProof)
- [x] Laravel React starter kit merged (Inertia v3, Fortify, Wayfinder, shadcn, passkeys/2FA); Chisel installer dropped
- [x] Web register creates company + owner (`company_name` field)
- [x] Quick login on the login page (`DEMO_LOGINS`, `DatabaseSeeder::LOGINS`: test@ and bina@example.com / Zx123456), as in lms-mentor
- [x] Laravel Boost (guidelines, skills, MCP in `.mcp.json`)
- [x] MAMP MySQL (`bayar_siap`), served at https://bayar-siap.local:8890
- [x] `composer ci:check` green: vp check, tsc, pint, larastan L7, phpunit

## Phase 1 — Laravel API (spec weeks 1–3)

- [x] Laravel 13 skeleton + Sanctum
- [x] Schema: all 17 tables (3 phase-2 tables created empty), money as `*_cents`
- [x] Multi-tenant global scope on `company_id`
- [x] Auth: register (company + owner), login, logout, /me, PUT /company
- [x] Clients, jobs (with milestone templates), send job, milestones CRUD/start
- [x] Proofs: upload-url (pre-signed on S3, local signed route on MAMP), register with sha256, delete
- [x] Payment requests (request / withdraw), VOs (create / send), payments (record)
- [x] Activity log (append-only), comments, devices, activity endpoint
- [x] Invariant tests (proof required, proofs read-only, sum rule, job completion)

## Phase 2 — Client web link (spec weeks 8–9; Inertia React + shadcn instead of Blade + Livewire)

- [x] OTP send/verify (`SmsSender` interface + log driver), 30-day device session (hashed token, cookie scoped to the job's link path)
- [x] Overview + accept, approve/reject request (proof gallery, bank details), VO sign (name + checkbox + optional finger signature)/reject, payment confirm/dispute
- [x] Job record PDF (dompdf) for client link and `GET /api/v1/jobs/{id}/record.pdf`: milestones, embedded photos + SHA-256, VOs, payments, audit trail
- [x] Demo job seeder (`DemoJobSeeder`) — prints the client link; OTP code lands in `storage/logs/laravel.log`
- [x] Comments: shared thread on milestones and VOs, both sides (`JobFlow::comment`, also used by the API)

## Phase 2b — Contractor web app (Laravel + React; spec had this as Android + phase-2 web admin)

- [x] Dashboard: active / awaiting acceptance / awaiting client action / outstanding, open jobs, client activity feed
- [x] Jobs list: search, status filter (shadcn Select), shadcn Table, pagination
- [x] Job form (create + edit draft): existing or new client, RM amounts, date pickers, template fill, live schedule-vs-quote total
- [x] Job page: send, copy link / WhatsApp share, PDF, milestones table, VO dialog (extra/deduction + attachments) + send, record payment dialog, activity
- [x] Milestone page: start, photo/file upload (server SHA-256, EXIF capture time + GPS), notes, request payment, withdraw, remove proof
- [x] Settings → Company: name, logo, bank details; account deletion removed
- [ ] Android app (spec) — the web app covers every contractor action meanwhile

## Phase 3 — Notifications

- [x] Proof thumbnails: queued `MakeProofThumbnail` (GD, 640px, EXIF rotation); separate file so the original's SHA-256 holds; HEIC falls back to original
- [x] "Approved verbally": contractor marks an open request with a required note; flagged on both sides + PDF, client is texted
- [x] Notifications: `JobNotifier` hooked on `JobFlow::log()` (audit action = spec event). Client: SMS + mail if on file, deep links. Contractor: database + push (log driver), Notifications page with sidebar badge
- [x] `jobproof:reminders` daily 09:00 MYT: milestone due in 3 days → contractor; client nudged after 48h waiting, max once per 48h per job (logged as `client.reminded`)
- [x] Twilio SMS/WhatsApp (`SMS_DRIVER=twilio`, `TWILIO_CHANNEL=sms|whatsapp`, local numbers → E.164); OTP shows a retry message if the gateway fails
- [x] FCM HTTP v1 push (`FCM_CREDENTIALS` = service-account JSON; free), drops uninstalled-app tokens; both default to the log driver
- [ ] Credentials: Twilio account (trial texts only verified numbers) and a Firebase project — yours to create

## Phase 3b — Staging deploy (prepared, not deployed)

- [x] Subfolder build: `basePath()` Vite plugin (from lms-mentor), `APP_PATH_PREFIX=bayar-siap npm run build`
- [x] `.github/workflows/deploy-assets.yml` (builds + publishes `deploy` branch) and `scripts/deploy.sh` (server: reset to
      origin/deploy, composer, migrate, caches, `queue:restart`; header lists the server `.env` keys)
- [x] Scheduler drains the queue every minute (`queue:work --stop-when-empty`), since cPanel has no supervisor
- [x] Verified locally under a real `/bayar-siap` subfolder: contractor pages, signed photo URLs, PDF, client OTP + cookie
      path, approve, API pre-signed upload; workflow steps run from a clean copy
- [ ] Needs git + a GitHub repo, then on staging: DB via `uapi`, `~/bayar-siap` clone on `deploy`, `.env`, symlink
      `~/ui.staging.oriclabdev.com/public/bayar-siap`, cron `schedule:run`

## Phase 3c — Spec gaps found in the audit (13 Oct)

- [x] Cancel a job (web + `POST /api/v1/jobs/{id}/cancel`): reason required, open requests withdrawn, client told, plan
      slot freed; client link stays readable (cancelled drafts stay closed); work/signing blocked, payment confirm allowed
- [x] Plan storage limits (Free 5 GB, Solo 50 GB, Team unlimited): `bytes` on proofs + VO attachments, measured from
      storage; checked before web uploads are kept; API pre-signed uploads over the limit are deleted; usage bar in Settings
- [x] Production packages: S3 driver (`FILESYSTEM_DISK=s3`, pre-signed PUT/GET verified), Horizon (`/horizon` gated by
      `HORIZON_ADMINS`); scheduler queue-drain runs only on the database queue
- [x] Billing: Stripe via Cashier on the company (tenant); Upgrade → Stripe Checkout, Manage → billing portal; plan synced by webhook (`/stripe/webhook`) and on return; MYR card subscriptions (FPX can't do recurring)
- [ ] Stripe account: Solo price (RM 49/month) id in `STRIPE_PRICE_SOLO`, keys, webhook secret — yours to create
- [x] Removed proof deletes its original + thumbnail (audit log keeps the hash): originals only for the current proof set
- [ ] Move older files to cheaper storage: an S3 lifecycle rule on the bucket (infra, with production hosting)
- [x] API for the app's remaining screens: `POST auth/forgot-password`, `GET notifications` (+ unread count in `/me`), `POST billing/checkout|portal` (returns a Stripe URL)
- [ ] Device-side compression to ~1600 px and offline capture queue — Android app

## Phase 3d — Design, languages, demo content (14 Oct)

- [x] Own visual identity (differs from other ~/Sites projects): hi-vis yellow + ink + concrete tokens, Barlow / Barlow Condensed, measuring-tape progress; flush sidebar, sticky header + footer; branded auth, welcome and error pages (no white backgrounds)
- [x] User guide page (sidebar → User guide)
- [x] English / Bahasa Melayu / 中文: switcher in footer, welcome and auth pages; choice saved on user / client; SMS, email and push sent in the recipient's language; rule + validation messages translated; dates localised
- [x] `scripts/i18n-keys.php` + LocaleTest: every UI key must exist in lang/ms.json and lang/zh.json (CI fails otherwise)
- [x] Demo data: Bina Jaya with 5 jobs in every state; real site photos (Wikimedia Commons, CC0/PD/CC BY(-SA), credits in database/seeders/photos/credits.json) and company logos
- [x] Job record PDF in EN / BM / 中文 (follows the downloader's language); Chinese uses Noto Sans SC (resources/fonts, OFL) with font subsetting (~12 KB added per PDF); activity labels now come from `ActivityLog::LABELS` (one list for feeds + PDF)

## Phase 4 — Android app (Kotlin, Compose) — spec weeks 4–7

- [ ] Not started

## Decisions

- Local dev: MAMP MySQL 8 on 127.0.0.1:3306, DB `bayar_siap` (root/root). Tests: in-memory SQLite by default; also
  pass on MySQL (`bayar_siap_test`). `.env.example` stays SQLite because CI copies it and has no MySQL.
- Laravel's database-queue table renamed to `queue_jobs` (spec owns `jobs`).
- All state transitions + invariants in `app/Services/JobFlow.php`; controllers only validate and call it.
- Tenant isolation: `BelongsToCompany` (company_id scope) and `ScopedThrough` (child rows via parent `whereHas`).
- After a job is sent, money changes only via signed VOs. A VO either adds a new milestone (amount > 0) or adjusts an
  unrequested milestone via `variation_orders.milestone_id` (column added beyond spec; needed for deductions).
- Milestone → `paid` when approved and confirmed payments cover its amount; rejection returns it to `in_progress`.
- Proof upload uses `Storage::temporaryUploadUrl` (S3 pre-signed, or Laravel's signed local PUT route on MAMP);
  server re-hashes the stored file and rejects sha256 mismatches.
- Plan limits enforced on send (free 1 / solo 10 active jobs). Storage quota not enforced yet.
- UI rules: forms `noValidate` (no HTML5 validation) + Laravel messages via `InputError`; dropdowns, tables and
  date pickers are shadcn components (Select/DropdownMenu, Table, Calendar+Popover).
- `SPEC.md` excluded from the `vp` formatter (user-owned document).
- `/settings` redirect is a closure (`to_route`), not `Route::redirect` — subfolder-safe and avoids a Wayfinder TS error.
- Client link uses its own never-logged-in `client-link` auth guard, so a contractor signed in on the same browser
  can't scope away the client's job. Every client lookup goes through the resolved job (no cross-job access).
- OTP: 6 digits, hashed, 10-min expiry, 5 wrong attempts per code, 3 sends per 15 min per job.
- `DatabaseSeeder` no longer uses `WithoutModelEvents` — demo data must go through the same model hooks (share token,
  append-only log) as the app.
- Notifications hang off the audit log instead of 8 event classes: one seam, every logged transition can notify.
  Queued + afterCommit; locally `composer run dev` runs the worker (MAMP alone needs `php artisan queue:work`).
- OTP texts stay synchronous (not queued) — the client is waiting for the code.
