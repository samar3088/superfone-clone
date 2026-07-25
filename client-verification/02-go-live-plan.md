# Go-live plan

**Status:** Not started. This is the list of everything standing between the
current build and a working production system.
**Last updated:** 25 July 2026

---

## How to read this

Ordered by when it has to happen, not by size. Anything marked **BLOCKER**
stops launch outright — the system will appear to work and quietly do nothing.

| Owner | Meaning |
|---|---|
| **VVT** | We do it |
| **Client** | Only the client can do it — account access, credentials, business decisions |
| **Host** | Whoever runs the production server |

---

## 1. Server — before anything else

| # | Task | Owner | Why it matters |
|---|---|---|---|
| 1.1 | **BLOCKER** Add the cron entry: `* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1` | Host | Without it **no leads are ever pulled automatically**. The two-hourly sync is defined and tested, but nothing runs it. The app looks healthy and silently receives nothing. |
| 1.2 | **BLOCKER** Run a queue worker (`php artisan queue:work`, under supervisor or equivalent) | Host | Welcome emails and lead alerts are queued. With no worker they sit in the `jobs` table forever — no error, no email. |
| 1.3 | **BLOCKER** Set `APP_DEBUG=false` | Host | With it on, any error page prints the full stack trace **including the Facebook token**. |
| 1.4 | Set `APP_ENV=production`, `APP_URL` to the real domain | Host | Signed links and email URLs use it. |
| 1.5 | Configure real SMTP (`MAIL_MAILER`, host, credentials) | Client + Host | Currently `log` — mail is written to a file and never sent. |
| 1.6 | Confirm `SESSION_DRIVER=database` and run migrations | Host | |
| 1.7 | HTTPS with a valid certificate | Host | Required for the Meta OAuth redirect, and for session cookies to be safe. |
| 1.8 | Set up database backups before the first import | Host | The go-live import is the largest single write this system will ever do. |

## 2. Facebook / Meta — client actions

| # | Task | Owner | Why it matters |
|---|---|---|---|
| 2.1 | **BLOCKER** Rotate the current access token | Client | The token in use was shared over chat and must be treated as compromised. |
| 2.2 | **BLOCKER** Replace it with a **Business Manager System User token** | Client | The current one is a *personal* token and expires in about 60 days. When it dies, lead syncing stops silently. A System User token does not expire. |
| 2.3 | Take the Meta app out of Development Mode | Client | In Development Mode only app-role users' leads come through. |
| 2.4 | Grant the app **Leads Access** on each page | Client | Without it the form list loads but no leads do. |
| 2.5 | Add a Privacy Policy URL to the Meta app | Client | Meta requires it before an app can leave development. |
| 2.6 | Register the production HTTPS URL as a valid OAuth redirect | Client | Needed only if one-click reconnect is wanted later; token paste works without it. |
| 2.7 | Provide `FACEBOOK_APP_SECRET` | Client | Required for real OAuth. Not needed for the current paste-a-token flow. |
| 2.8 | Confirm **which forms are live** and what their phone field is called | Client | A form using an unexpected phone field name has its leads **silently discarded**. See [01](01-lead-duplicate-rules.md) §3.3. |

## 3. Historical lead import — the one-way door

| # | Task | Owner | Notes |
|---|---|---|---|
| 3.1 | **Decide whether to import history at all** | Client | ~12,500 leads exist across the account. Most are months old and already dealt with. |
| 3.2 | Decide which stage they land in | Client | The import parks them in a chosen stage. Suggested: a closed stage, so they do not read as open work. |
| 3.3 | Take a database backup | Host | Before, not after. |
| 3.4 | Run `php artisan leads:backfill --stage=<closed> --force` | VVT | Defaults are deliberately cautious: **unassigned**, **already read**, **no emails sent**. |
| 3.5 | Let it finish the repeat-detection repair pass | VVT | Runs automatically at the end. Facebook returns leads newest-first, so flags are corrected once the full history is present. |
| 3.6 | Spot-check 20 imported leads against Facebook | VVT + Client | Dates, names, phone numbers, custom answers. |

> **Note:** the routine sync only looks back 30 days on its first run. The
> historical import is a separate, deliberate one-off.

## 4. Running alongside Superfone

| # | Task | Owner | Notes |
|---|---|---|---|
| 4.1 | **Decide the source of truth for lead status** | Client | Both systems read the same Facebook API independently — syncing here does **not** remove leads from Superfone. But a lead closed in one stays open in the other. Without a decision, staff work the same lead twice. |
| 4.2 | Agree a cutover date, or a parallel-run period with one system authoritative | Client | |
| 4.3 | Decide whether Superfone history needs migrating | Client | Not currently planned or built. |

## 5. Users and access

| # | Task | Owner | Notes |
|---|---|---|---|
| 5.1 | Provide the real staff list — names, mobiles, emails | Client | Mobile is the login identifier. |
| 5.2 | Create the Owner account with the client's own mobile | VVT | |
| 5.3 | Create members; each receives a welcome email with a temporary password | VVT | Requires §1.5 (SMTP) and §1.2 (queue worker) first. |
| 5.4 | Map members to each campaign | VVT + Client | **A campaign with nobody mapped delivers leads that no member can see** — they arrive unassigned and only the Owner sees them. |
| 5.5 | Confirm the lead stage list matches how the business works | Client | 14 stages seeded from the client's existing pipeline. |
| 5.6 | Decide whether new-lead emails are on | Client | Off by default. One busy campaign can produce thousands of leads; switching it on without thinking floods inboxes and risks the sending domain. |

## 6. Before handover

| # | Task | Owner |
|---|---|---|
| 6.1 | Full run-through on production: login, OTP, lead arrives, assignment, filters, exports | VVT |
| 6.2 | Confirm the scheduled sync has actually run (check an integration's Logs tab) | VVT |
| 6.3 | Confirm a queued email actually arrived | VVT |
| 6.4 | Walk the client through Settings | VVT |
| 6.5 | Hand over this folder | VVT |

---

## 7. Known gaps at go-live

Not blockers, but the client should not be surprised:

1. **Nobody is alerted when a sync fails.** Failures are recorded on the
   integration's Logs tab and nowhere else. If the token dies, lead flow stops
   and the only symptom is leads not arriving.
2. **The Existing Lead to-do settings do nothing.** The form saves task type,
   title and due time, but there is no task list in the system for them to
   create work in.
3. **Field Priority Order is read-only**, pending an answer on what it should do.
4. **Custom form answers are only visible in the new-lead email** — there is no
   lead detail screen, so on the Leads list those answers cannot be seen.
5. **Telephony is not built.** No virtual number, call records or recordings.
   The Teams screen has a place for the number and fills in automatically once
   one is provisioned.
6. **No billing.** Renew Plan and Buy Add-on are visibly disabled.

---

## 8. Rollback

If the import goes wrong: restore the backup from §3.3. The import is additive —
it creates leads and customers and changes nothing existing — but restoring is
faster and more certain than unpicking it by hand.
