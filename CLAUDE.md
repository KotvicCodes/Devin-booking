# CLAUDE.md

Guidance for Claude Code (and other AI agents) working in this repository.

## Project

Děvín Booking — a Laravel 12 + FilamentPHP 3 booking and administration system for a cabin
rental. It has a public booking form with live availability, an admin panel, automatic
deposit/reminder handling, and bank-payment reconciliation. UI and customer-facing copy are in
**Czech**; code and comments are mixed Czech/English.

See `README.md` for a feature overview, `DEPLOY.md` for the deployment checklist,
`MAINTENANCE.md` for operations, and **`IMPROVEMENTS.md`** for the standing
security/deployment/maintainability assessment and the planned Fio→Comgate payment migration —
read `IMPROVEMENTS.md` before making infra, payment, or security changes.

## Tech stack

- Laravel 12, PHP 8.2+ (Docker image runs 8.4)
- FilamentPHP 3 admin panel (Livewire, `saade/filament-fullcalendar`)
- Frontend: Livewire, FullCalendar, Flatpickr, Vite, Tailwind CSS 4
- MySQL 8; queue/cache/session all use the `database` driver
- `spatie/laravel-settings` for global settings; Meilisearch (Scout) for admin search
- Payments: Fio Bank API today (`dfridrich/qr-platba` for QR); **migrating to Comgate** — see
  `IMPROVEMENTS.md`

## Running it

Docker Compose is the supported path. Do **not** run destructive commands against a real
environment (`docker compose down -v`, `migrate:fresh`, `db:wipe`).

```bash
docker compose -f compose.dev.yaml up -d     # dev stack (uses .dev.env); Mailpit at :8025
docker compose -f compose.yaml up -d         # prod stack (uses .env)
```

## Testing

Run tests through the isolated test stack, **not** inside the `app` container (that image is
built with `composer install --no-dev`, so it has no PHPUnit or Pint):

```bash
docker compose -f compose.test.yaml run --rm test                     # full suite
docker compose -f compose.test.yaml run --rm test --filter=BookingForm  # subset (args go to `artisan test`)
docker compose -f compose.test.yaml run --rm test vendor/bin/pint --test tests  # style check
docker compose -f compose.test.yaml down                              # stop the throwaway MySQL
```

- Needs only Docker: no host PHP, Composer, `.env` or `vendor/`. First run builds
  `deploy/test.Dockerfile` (PHP 8.4 CLI + dev dependencies); add `--build` after `run` when
  `composer.lock` changes (the entrypoint refuses to run with a stale `vendor/`).
- MySQL 8.0.36 (same as prod) in tmpfs, because `BookingForm` uses MySQL `GET_LOCK`; don't
  switch tests to SQLite.
- The source is mounted **read-only** path by path (see `compose.test.yaml`), with `storage/`
  and `bootstrap/cache/` on tmpfs, so tests can't touch the checkout or the dev/prod volumes.
  Add any new top-level source directory to the mounts there.
- Tests live in `tests/Feature` (HTTP, Livewire, artisan, Filament) and `tests/Unit`. Use
  `RefreshDatabase` and `Mail::fake()`. The default prices and capacity come from
  `database/settings/` migrations.
- Bug fixes come with a regression test that fails before the fix.

Prod stack services: `app`, `queue`, `scheduler`, `db`, `backup`, `meilisearch`, `caddy`
(Caddy terminates TLS and reverse-proxies to `app:80`).

## Architecture — where things live

- **Public booking:** `routes/web.php` → `booking` view; form is `app/Livewire/BookingForm.php`
  (server-side validation, IP+email rate limiting, a MySQL `GET_LOCK` around the overlap/capacity
  re-check). Availability API: `app/Http/Controllers/Api/AvailabilityController.php`
  (`/api/availability`, throttled).
- **Booking domain logic:** `app/Models/Booking.php` — the `saving()` hook computes
  `total_price`, `deposit_amount`, and `variable_symbol`, and drives status transitions:
  `status = deposit_paid` once `paid_amount >= deposit_amount`. **This is the heart of the app.**
- **Emails/side effects:** `app/Observers/BookingObserver.php` (`afterCommit`) queues
  confirmation/update/deposit emails. Templates in `resources/views/emails/`.
- **Payments (current):** `app/Services/FioBankApiClient.php` +
  `app/Console/Commands/ProcessFioBankPayments.php` poll Fio and match transactions by variable
  symbol; `app/Models/ProcessedTransaction.php` dedupes. **Being replaced by Comgate** (webhook
  instead of polling) — see the migration plan in `IMPROVEMENTS.md`.
- **Scheduled work:** `routes/console.php` — `bookings:process-pending` (reminders + auto-cancel
  overdue bookings) and `bookings:process-fio-payments` (Fio import).
- **Settings:** `app/Settings/GeneralSettings.php` (pricing, `bed_capacity`, `pending_window`,
  `deposit_percentage`, bank details) edited via `app/Filament/Pages/GeneralSettings.php`.
- **Admin:** panel in `app/Providers/Filament/AdminPanelProvider.php` (path `/admin`); resources
  under `app/Filament/Resources/` (`BookingResource`, `UserResource`, `ProcessedTransactionResource`).
- **Authorization:** boolean flags on `users` (`is_super_admin`, `can_manage_users`,
  `can_view_bookings`, `can_edit_bookings`, `can_manage_financials`) enforced via
  `app/Policies/`; super admins bypass checks.
- **Middleware/exceptions:** `bootstrap/app.php` (trusted proxies via `TRUSTED_PROXIES`, crash
  emails to `ADMIN_CRASH_EMAILS`).

## Conventions & gotchas

- **Money is integer Kč** throughout (`total_price`, `deposit_amount`, `paid_amount` are cast to
  `integer`). Comgate amounts are in haléře (×100).
- **Payment status flows through `paid_amount`.** To mark a booking paid, set `paid_amount` and
  `save()` — the model + observer handle status and emails. Don't set `status` directly.
- **`variable_symbol`** is the booking's payment reference and (in the Comgate plan) its `refId`.
- Bookings that have already ended, have no `customer_email`, or have `total_price <= 0` skip
  email/deadline enforcement — mirror that logic in any new payment/notification code.
- Guest categories: graduate/student/child/external/dog. **Dogs affect price but not bed
  capacity.** Capacity is checked per night; `reserve_whole` blocks the entire cabin.
- **Never log secrets or full API URLs containing tokens** (an existing bug with the Fio token —
  `IMPROVEMENTS.md` finding #1). Use the `bookings` log channel for payment events.
- Customer-facing strings are Czech. Keep new copy Czech (translations in `lang/`).

## Git / workflow

- Branch per task; do not commit to `main` directly.
- Do not create pull requests unless asked.
- Keep `IMPROVEMENTS.md` in sync when you change payments, deployment, or security.
