# Superfone Console

Business calling &amp; CRM admin, rebuilt from scratch on **Laravel 12 + Inertia 2 + React 19**.

## Stack

| Layer     | Choice                                                        |
| --------- | ------------------------------------------------------------- |
| Backend   | Laravel 12 (PHP 8.2+), MySQL database `superfone`              |
| Frontend  | Inertia 2 + React 19 + TypeScript + Tailwind 4                 |
| Auth      | Session-based; mobile + OTP with a password fallback           |
| Access    | `spatie/laravel-permission` — roles **Owner** and **Member**   |
| Auditing  | `spatie/laravel-activitylog` — every create/update/delete      |

## Engineering standards

These are enforced structurally, not by convention:

- **Controllers stay thin.** All business logic lives in `app/Services/**`; controllers validate through a `FormRequest`, call a service, and return.
- **Every field is validated** server-side via `FormRequest`; Inertia flows the errors straight back to the form.
- **Server-side datatables.** `DataTableService` does search, filter, sort and pagination in SQL. Sort and search columns are whitelisted — an arbitrary column from the client is neither an injection surface nor an unindexed scan.
- **Chunked exports.** `ExportService` streams CSV via `chunkById()` straight to `php://output`, so memory stays flat regardless of row count. Cells beginning `= + - @` are escaped against spreadsheet formula injection.
- **No browser caching of data.** The `NoBrowserCache` middleware sends `no-store` on every application response, so Back never shows stale records. Vite's content-hashed assets are unaffected.
- **Indexes for the access patterns**, e.g. `users(is_active, id)` for the members table, `otp_codes(mobile, purpose, consumed_at, expires_at)` for verification lookups.

## Getting started

```bash
composer install
npm install
php artisan migrate --seed
npm run build      # or: npm run dev
php artisan serve  # http://localhost:8000
```

### Day-to-day: migrations are incremental

Schema changes ship as **new migration files**, never by rewriting old ones:

```bash
php artisan make:migration add_something_to_leads_table
php artisan migrate      # applies only what is new
php artisan db:seed      # safe to re-run, see below
```

`migrate:fresh` is reserved for a deliberate reset — it drops every table,
which wipes the `sessions` table (logging everyone out mid-session) along with
any data entered by hand.

All seeders use `updateOrCreate` keyed on a natural identifier, so
`php artisan db:seed` is idempotent — running it repeatedly tops up missing
rows without duplicating anything.

### Sign in

| Role  | Mobile       | Password    |
| ----- | ------------ | ----------- |
| Owner | `9999900001` | `Owner@123` |

OTP login is the primary path. With `OTP_DRIVER=log` the code is written to
`storage/logs/laravel.log` **and shown on screen in development**, so the flow
works with no SMS gateway. Swap `OTP_DRIVER` when the client's gateway and DLT
registration land — only the driver binding changes.

### OTP security

Codes are stored hashed, expire after 5 minutes, allow 5 wrong guesses before
being burned, are single-use, and are rate limited per mobile (resend cooldown)
and per IP (route throttle). Requesting a code for an unregistered number
returns the same response as a registered one, so the endpoint cannot be used to
enumerate accounts.

## Roadmap

| Phase | Scope                                                             | Status  |
| ----- | ----------------------------------------------------------------- | ------- |
| 0     | Foundation, roles/permissions, datatable + export engines          | ✅ done |
| 0.5   | Design system, login and console shell                             | ✅ done — awaiting client sign-off |
| 1     | Profile, edit profile, forgot/reset password                       | next    |
| 2     | Team members CRUD, activity log viewer                             | next    |
| 3     | Settings — Tags, CRM (lead stage/group/priority), Facebook leads   | planned |
| 4     | VICI dial telephony: inbound/outbound calling and reports          | blocked on API access |
