# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Run all tests
php artisan test

# Run a single test file
php artisan test --filter AuthTest
php artisan test tests/Feature/OTPTest.php

# Run migrations
php artisan migrate

# Force-run migrations against production DB
php artisan migrate --force

# Start local dev server
php artisan serve

# Lint / code style
./vendor/bin/pint
```

## Architecture

This is a **Laravel 13 stateless JSON API** for authentication. It uses **Laravel Sanctum** for token-based auth (Bearer tokens, no cookies/sessions for API consumers) and is designed to deploy to **Vercel** (serverless PHP via `vercel-php` runtime) with **Supabase PostgreSQL** as the database.

### Request flow

All requests enter via `api/index.php` (the Vercel serverless entry point), which boots the Laravel application and routes every incoming request to `routes/api.php`.

### Auth endpoints

| Method | Path | Middleware |
|--------|------|------------|
| POST | `/api/register` | — |
| POST | `/api/login` | `throttle:5,1` |
| POST | `/api/logout` | `auth:sanctum` |
| GET | `/api/me` | `auth:sanctum` |
| POST | `/api/email/verify` | `auth:sanctum` |
| POST | `/api/email/resend-otp` | `auth:sanctum` |

### OTP flow

On registration, `OTPController::sendOtp()` is called directly (not via a queued listener). It generates a 6-digit code, persists it as `otp` + `otp_expires_at` on the `users` row, and sends it via `Mail::raw()`. Verification stamps `otp_verified_at` and `email_verified_at`. Resend is rate-limited to 3 attempts per 60 seconds per user via `RateLimiter`.

### Key files

- `app/Http/Controllers/AuthController.php` — register, login, logout, me
- `app/Http/Controllers/OTPController.php` — verify, resend (+ static `sendOtp` helper)
- `app/Http/Resources/UserResource.php` — consistent user response shape
- `app/Models/User.php` — includes `HasApiTokens`; OTP columns in `$fillable` and `$casts`
- `config/cors.php` — driven by `CORS_ALLOWED_ORIGINS` env var (comma-separated)
- `config/sanctum.php` — token expiry driven by `SANCTUM_TOKEN_EXPIRATION` (minutes, default 10080 = 7 days)
- `vercel.json` — routes all traffic to `api/index.php`; requires `outputDirectory: dist`
- `.github/workflows/deploy.yml` — pushes to `main` trigger a Vercel production deployment

### Database

PostgreSQL (Supabase). The `users` table carries three extra columns beyond the Laravel default: `otp` (varchar 6, nullable), `otp_expires_at` (timestamp, nullable), `otp_verified_at` (timestamp, nullable). The `search_path` is controlled by `DB_SCHEMA` env var (default `public`) to avoid Supabase's `public` schema conflict.

### Deployment checklist (manual steps)

1. Create Supabase project → copy Session Pooler connection string → set `DB_URL` in Vercel env vars.
2. Run `php artisan migrate --force` against Supabase before first traffic.
3. Set `CORS_ALLOWED_ORIGINS` to your frontend domain in Vercel env vars.
4. Add `VERCEL_TOKEN`, `VERCEL_ORG_ID`, `VERCEL_PROJECT_ID` as GitHub Actions secrets.
5. Push to `main` to trigger the GitHub Actions deploy workflow.
