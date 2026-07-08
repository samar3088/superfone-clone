# Superfone Admin

A replica of the Superfone owner dashboard (admin.superfone.co.in) built as a
**Laravel 12 API** + **React (Vite) SPA**. The logged-in user is a business
owner managing their organizations, staff, settings, and lead integrations.

## Current scope — 5 pages

| Page             | Route           | What it does                                                            |
| ---------------- | --------------- | ----------------------------------------------------------------------- |
| **Home**         | `/`             | Greeting, Explore AI cards, Call/Customer/Staff insights (date range), subscription overview |
| **Teams**        | `/teams`        | Orgs table (status, number, staff x/y, leads x/y), Renew from wallet, Addon Purchase modal |
| **Team Members** | `/team-members` | Staff across teams (name/role/state), Add staff modal (+91 phone, team, role) |
| **Settings**     | `/settings`     | 5 tabs: Tags · Call Settings (sticky agent, IVR, ringing order) · CRM (lead stages + templates, groups) · Custom Fields · Priority Order |
| **Integrations** | `/integrations` | Lead sources (Facebook wizard: name → page → form), 15-provider catalog, ACTIVE toggle |

Every other sidebar item lands on a **Coming Soon** placeholder (`/soon/:title`)
so the full Superfone navigation look is kept while the codebase stays focused.

> The full earlier build (sticky-agent call engine, Live Call Dashboard,
> WhatsApp inbox/broadcast, CRM pages, billing/analytics) is preserved in git —
> first commit `Snapshot: full build before focusing codebase on 5 pages`.

## Stack

| Layer    | Tech                                                          |
| -------- | ------------------------------------------------------------- |
| Backend  | Laravel 12, Sanctum token auth, MySQL (`superfone` database)   |
| Frontend | React 19, Vite, React Router, Tailwind CSS v4, Inter font      |

## Running

### Production (single URL — recommended)

```bash
bash build.sh              # builds the SPA and publishes it into Laravel
cd backend
php artisan serve          # → http://localhost:8000
```

### Development (hot reload)

```bash
cd backend  && php artisan serve    # API  → :8000
cd frontend && npm run dev          # SPA  → :5173
```

## Login

```
Email:    admin@superfone.test
Password: admin123
```

## API surface (all under `/api`, Bearer token except login)

- `POST /login` · `GET /me` · `POST /logout`
- `GET /home` — insights for a `from`/`to` date range
- `GET /orgs` · `POST /orgs/{org}/renew`
- `GET /addons` · `POST /addons/purchase` · `GET /addon-purchases`
- `apiResource /team-members`
- `GET|POST|PATCH|DELETE /settings/*` — tags, lead-stages (+templates), lead-groups, custom-fields, call, ring-order, priority
- `GET|POST|PATCH|DELETE /integrations` + `GET /integrations/facebook/pages[/{id}/forms]`

## Notes

- Re-seed anytime: `cd backend && php artisan migrate:fresh --seed`
- Facebook pages/forms are served by a simulated driver shaped like the Meta
  Graph API ([FacebookLeadSource](backend/app/LeadSources/FacebookLeadSource.php)) —
  swap in real Graph calls once the client provides a Meta App + OAuth.
- "Buy a new Number" opens a WhatsApp deep link, matching the real product.
