# JobProof (bayar-siap)

Paid milestones with photo proof for small contractors. A job's quote is split into milestones; each milestone is proved with timestamped, geotagged photos; the client approves every payment request from a link on their phone; extra work goes through signed variation orders; and both sides see the same payment record.

Staging: https://ui.staging.oriclabdev.com/bayar-siap

## What's in it

- **Contractor web app**: dashboard, jobs with milestone templates, photo proof (SHA-256 fingerprints, EXIF time and GPS, thumbnails), payment requests, variation orders, payments, comments, cancellation, notifications, company settings and billing.
- **Client link**: no account or app; the client verifies with a 6-digit SMS code, then accepts the job, approves or rejects payment requests with a reason, signs variation orders, and confirms or disputes payments.
- **REST API** (`/api/v1`, Sanctum) for the mobile apps, including pre-signed photo uploads.
- **Job record PDF** with milestones, photos, hashes, variation orders, payments and the full audit trail.
- **English, Bahasa Melayu and 中文** for the interface, texts, emails and the PDF; each person's choice is saved.
- **Notifications**: client SMS or WhatsApp (Twilio) and email; contractor in-app list and push (FCM); daily reminders.
- **Plans**: Free (1 active job, 5 GB) and Solo (10 jobs, 50 GB, RM 49/month through Stripe).

**Coming soon: Android and iOS apps.** Native apps for contractors are on the way, with an in-app camera that stamps time and GPS, an offline upload queue for sites with weak signal, and push notifications. They use the same API as the web app; clients keep using the link and never need to install anything.

The product spec is [SPEC.md](SPEC.md); what is done and what is left is tracked in [PROGRESS.md](PROGRESS.md).

## Stack

Laravel 13 (PHP 8.4), Inertia v3 + React 19 + TypeScript, Tailwind v4 + shadcn/ui, Fortify, Sanctum, Wayfinder, Cashier (Stripe), Horizon, dompdf, MySQL.

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
# set DB_* in .env (MySQL, e.g. MAMP on 127.0.0.1:3306), or keep SQLite
php artisan migrate --seed
composer run dev        # server, queue worker, logs and Vite
```

With `DEMO_LOGINS=true` the login page offers one-click demo accounts:

| Account                            | Password | Plan                         |
| ---------------------------------- | -------- | ---------------------------- |
| test@example.com (Demo Renovation) | Zx123456 | Free, one active kitchen job |
| bina@example.com (Bina Jaya)       | Zx123456 | Solo, jobs in every state    |

Seeding prints a client link. SMS codes and other texts go to `storage/logs/laravel.log` until `SMS_DRIVER=twilio` is configured.

## Configuration

All optional; without them the app logs instead of sending.

| Service               | Keys in `.env`                                                                                            |
| --------------------- | --------------------------------------------------------------------------------------------------------- |
| Twilio SMS / WhatsApp | `SMS_DRIVER=twilio`, `TWILIO_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_FROM`, `TWILIO_CHANNEL`                   |
| Firebase push         | `FCM_CREDENTIALS` (path to the service-account JSON)                                                      |
| Stripe billing        | `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_PRICE_SOLO`; webhook at `/stripe/webhook` |
| S3 storage            | `FILESYSTEM_DISK=s3` plus the `AWS_*` keys                                                                |
| Redis queue           | `QUEUE_CONNECTION=redis`, run `php artisan horizon`; `HORIZON_ADMINS` for the dashboard                   |

## Checks

```bash
composer ci:check       # lint, TypeScript, Pint, Larastan level 7, PHPUnit
```

CI runs the same on every push. Every interface string must exist in `lang/ms.json` and `lang/zh.json`; `php scripts/i18n-keys.php --missing` lists any that don't.

## Deploying to staging

On every push to `main`, GitHub Actions builds the frontend and publishes a `deploy` branch (the server never runs npm). On the server:

```bash
cd ~/bayar-siap && bash scripts/deploy.sh
```

The script header lists the server `.env` settings for the `/bayar-siap` subfolder. A per-minute cron runs `php artisan schedule:run`, which also processes the queue.

## Credits

Demo site photos are from Wikimedia Commons (CC0, public domain, CC BY and CC BY-SA); authors and licences are in [database/seeders/photos/credits.json](database/seeders/photos/credits.json). The Chinese PDF font is Noto Sans SC under the [SIL Open Font License](resources/fonts/OFL.txt).
