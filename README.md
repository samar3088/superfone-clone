# Superfone Admin

A business-calling admin panel built as a **Laravel 12 API** + **React (Vite) SPA**, inspired by the Superfone admin dashboard. It manages customers (businesses), virtual numbers, call logs, and subscription plans.

## Stack

| Layer    | Tech                                                         |
| -------- | ----------------------------------------------------------- |
| Backend  | Laravel 12, Sanctum token auth, MySQL (`superfone` database) |
| Frontend | React 19, Vite, React Router, Tailwind CSS v4, Chart.js      |

## Project layout

```
superfone/
├── backend/    Laravel API
└── frontend/   React SPA
```

## Prerequisites

- XAMPP running **MySQL** (the `superfone` database is used)
- PHP 8.2+ and Composer
- Node 18+ and npm

## Setup (already done once)

```bash
# Backend
cd backend
composer install
# .env already points DB_DATABASE=superfone (root / no password)
php artisan migrate:fresh --seed   # creates tables + sample data

# Frontend
cd ../frontend
npm install
```

## Running

### Production (single URL — recommended)

Build the SPA into Laravel, then serve everything from one origin:

```bash
# From the project root: builds the React app and publishes it into Laravel
bash build.sh

# Then start the app
cd backend
php artisan serve
```

Open **http://localhost:8000** — the React SPA, its API, and client-side
routing (deep links like `/customers`) are all served by Laravel. No CORS,
no second server. Re-run `bash build.sh` after any frontend change.

### Development (hot reload — two terminals)

```bash
# Terminal 1 — API on http://localhost:8000
cd backend
php artisan serve

# Terminal 2 — SPA with hot reload on http://localhost:5173
cd frontend
npm run dev
```

Then open **http://localhost:5173** (the dev build points at `http://localhost:8000/api`).

## Login

```
Email:    admin@superfone.test
Password: admin123
```

## Modules

| Section            | Frontend route   | API base            |
| ------------------ | ---------------- | ------------------- |
| Dashboard          | `/`              | `/dashboard`        |
| Customers          | `/customers`     | `/customers`        |
| Contacts (CRM)     | `/contacts`      | `/contacts`         |
| Leads (pipeline)   | `/leads`         | `/leads`            |
| Reminders          | `/reminders`     | `/reminders`        |
| Team & roles       | `/team`          | `/team-members`     |
| Virtual Numbers    | `/numbers`       | `/virtual-numbers`  |
| Call Logs          | `/call-logs`     | `/call-logs`        |
| WhatsApp Inbox     | `/inbox`         | `/conversations`    |
| Templates          | `/templates`     | `/templates`        |
| Campaigns          | `/campaigns`     | `/campaigns`        |
| AI Agents          | `/ai-agents`     | `/ai-agents`        |
| Plans              | `/plans`         | `/plans`            |
| Payments           | `/payments`      | `/payments`         |
| Analytics          | `/analytics`     | `/analytics`        |

Auth: `POST /api/login`, `GET /api/me`, `POST /api/logout`. All other
`/api` routes require a Bearer token.

## Notes

- Auth uses Sanctum personal access tokens stored in `localStorage`.
- Dev API base URL is set in `frontend/.env`; the production build uses `/api`
  (relative) from `frontend/.env.production`.
- Re-seed anytime with `php artisan migrate:fresh --seed` in `backend/`.
- `build.sh` compiles the SPA and publishes it into `backend/public/assets`
  plus `backend/resources/spa.html` (served by the catch-all route in
  `routes/web.php`).
