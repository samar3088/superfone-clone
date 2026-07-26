# Restore points

**How to undo any of this if the client says no.**
Last updated: 26 July 2026

---

## What exists

| | Where | Covers |
|---|---|---|
| **Code** | git tag `client-review-2026-07-26` at commit `60c909d` | Every line of the app |
| **Database** | `backups/superfone_2026-07-26_0832.sql` | All data: customers, leads, users, settings, the encrypted Facebook token |

The database dump is **deliberately not in git** — it holds real customer
records and the encrypted Facebook token. `backups/` is gitignored. Keep it
somewhere private and backed up separately.

**The restore was tested, not assumed.** The dump was loaded into a scratch
database and every table's row count compared against the live one — users 11,
customers 30, channels 35, leads 32, integrations 2 — and the encrypted token
was confirmed byte-identical after the round trip.

---

## Undoing one feature

Each feature is one or two commits with a self-describing message, so reverting
one does not disturb the others. `git revert` keeps the history rather than
rewriting it, which matters once anything is shared.

```bash
git revert --no-commit <sha> && git commit
```

| Feature | Commit(s) | Notes on reverting |
|---|---|---|
| Contacts hold several numbers and emails | `26af14c` `60c909d` | **Leaves a migration behind** — see below |
| Export button scrolling out of reach | `14a0f7d` | Safe, presentation only |
| Open-questions document | `5cb600b` | Safe, documentation only |
| New Lead / Existing Lead to-dos | `3804f14` | Leaves the `tasks` table behind |
| Settings tabs remembered in the URL | `ee87fb9` | Safe, presentation only |
| Repeat-enquiry detection | `e8ace60` | Leaves lead columns behind |
| Team column on Team Members | `acdeff1` | Leaves `users.team_id` behind |
| Integration settings panel rework | `154f466` | Safe, presentation only |
| Teams screen | `8a978a3` | Leaves the `teams` table behind |
| Multi-select filter panel clipping | `07396f4` | Safe, presentation only |
| Multi-select filters and button colours | `4c888f7` | Safe, presentation only |
| Working Reconnect | `a120faa` | Reverts to a Reconnect that does nothing |
| Integration list, preview, settings screens | `404685a` | Leaves integration columns behind |
| Field Priority Order made read-only | `0a62535` | Reverting makes the controls live again |
| 14-stage pipeline seed | `901ea28` | Stages already seeded stay; edit them in Settings |
| Filters apply on a button press | `4e0b36e` | Reverts to filtering on every change |
| List filters and exports | `94349b4` | Safe, presentation and query only |
| Smoke test | `48c6e88` | Safe, tests only |
| Removal of starter-kit auth controllers | `86c093f` | Restores dead code; no reason to |
| Account and lead emails, token out of `.env` | `9119485` | **Reverting puts the token back in `.env`** |
| Facebook sync made production-safe | `0ecf915` | **Do not revert** — reintroduces silent lead loss past page 1 |

### Migrations do not reverse themselves

Reverting a commit removes the migration *file* but not the table or column it
already created. Roll the database back **first**, while the migration file is
still present:

```bash
php artisan migrate:rollback --step=1   # check `migrations` table for how many
git revert --no-commit <sha> && git commit
```

Getting this the wrong way round leaves an orphaned table that no migration
knows about — harmless, but confusing later.

---

## Going all the way back

```bash
# Code
git reset --hard client-review-2026-07-26

# Database — replaces everything currently in it
/c/xampp/mysql/bin/mysql.exe -h 127.0.0.1 -u root -e "DROP DATABASE superfone; CREATE DATABASE superfone CHARACTER SET utf8mb4;"
/c/xampp/mysql/bin/mysql.exe -h 127.0.0.1 -u root superfone < backups/superfone_2026-07-26_0832.sql

# Then
composer install && npm ci && npm run build
php artisan optimize:clear
php artisan test
```

`git reset --hard` throws away uncommitted work. Commit or stash first.

---

## Taking a fresh snapshot

Before any risky change, or at the end of a working day:

```bash
mkdir -p backups
/c/xampp/mysql/bin/mysqldump.exe -h 127.0.0.1 -u root \
  --single-transaction --routines --triggers --events \
  --default-character-set=utf8mb4 \
  superfone > "backups/superfone_$(date +%Y-%m-%d_%H%M).sql"

git tag -a checkpoint-$(date +%Y-%m-%d) -m "Known good: <what works>"
```

---

## The gap worth closing

**Everything here lives on one machine.** The 22 commits made in this session
have not been pushed to GitHub, and the database dump sits on the same disk as
the database it came from. A failed drive loses both.

Pushing to `github.com/samar3088/superfone-clone` — and copying the dump
somewhere else — is what turns this from a restore point into a backup.
