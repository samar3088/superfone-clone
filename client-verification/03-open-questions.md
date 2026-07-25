# Open questions for the client

**Prepared for the client meeting on Sunday 26 July 2026, 10:00 IST.**

Everything the build is currently guessing at, or waiting on. Ordered by how much
rework a late answer causes — **Section A first**, since those shape the data
model and get more expensive the longer they wait.

Each item says what we assumed and what we did about it, so the conversation can
be "is this right?" rather than starting from nothing.

---

## A. Decide first — these shape the data model

### A1. Contacts vs Customers — do we need a separate table?

We have a **Customers** table today. A customer only exists because a lead
created one. Someone who rings the virtual number and never enquires has **no
record at all**.

So the question is what *Contacts* means:

| Option | Consequence |
|---|---|
| Same as Customers, different name | Rename a screen. Trivial. |
| A **phonebook** — every number ever seen, enquiry or not | New table. Every inbound call creates one. Customers become a subset of contacts. |
| A third thing — "clients" as distinct from prospects | Needs defining: what makes someone a client rather than a customer? |

> **VVT is unsure whether a separate clients table is needed.** Worth settling
> this before Contacts is built — it is the difference between a rename and a new
> table that everything else references.

### A2. Sticky agent routing — the detail

**The rule as given:** one virtual number, several agents. When a call comes in,
the agent who last spoke to that caller gets first refusal; only then does it open
to everyone else.

**Understood, but note where it lives.** The app cannot ring phones. The telephony
provider decides who rings and for how long. What we build is an **API the
provider calls** — "here is the caller's number, who should ring first?" — and we
answer with a preferred agent plus a fallback order. That part needs no virtual
number and can be built now.

Six things change what we build:

1. **What counts as "spoke to"?** Any connected call, or one lasting over some
   minimum? A 4-second misdial should probably not claim a customer for months.
2. **How long does the preference last?** Forever, or a window? What if that agent
   has since left?
3. **Lead owner vs last-spoke agent.** These differ. A lead assigned to Aarav, but
   Diya took the last call. Who gets first refusal?
4. **If the preferred agent is unavailable** — offline, on another call — skip
   straight to everyone, or ring and time out?
5. **Unknown caller**, never seen before. Everyone at once, or round-robin?
6. **Do outgoing calls count** toward the relationship? We would assume yes.

> **Note a deliberate difference:** for *leads*, VVT decided repeats go by the
> campaign's own member mapping — explicitly **not** back to the last handler. For
> *calls*, the opposite. Both are fine; recorded so nobody later "harmonises" them
> and breaks one.

### A3. Reports — new, or the Dashboard grown up?

The Dashboard already carries Call Insights, Customer Insights, Staff Insights and
three date-ranged exports.

> **Confirm:** does *Reports* replace that, or go deeper — and if deeper, which
> questions should it answer?

---

## B. Rules already live, running on our assumptions

Full detail in [01-lead-duplicate-rules.md](01-lead-duplicate-rules.md).

| # | We assumed | Confirm |
|---|---|---|
| B1 | A repeat enquiry on the **same campaign within 2 days** is a duplicate | Is 2 days right? For wedding planning a customer may return a week later while deciding — currently that reads as fresh work |
| B2 | A **different campaign** is always a fresh lead | Should a same-person-different-campaign still be flagged for awareness? |
| B3 | Same email, **different mobile** still means the same person, with mobile preferred | Or should both have to match? |
| B4 | Returning after **Sale Closed / Not Interested / Cold** is a fresh opportunity | Should *Cold* count as still-open instead? |
| B5 | Repeats are **labelled, not hidden or merged** | Does the client expect duplicates folded away automatically? |
| B6 | **Field Priority Order** does nothing — screen is deliberately read-only | What is it meant to change? Field order on a lead? Columns in a list? Which fields are mandatory? |

## C. Facebook — client actions, blocking go-live

Full detail in [02-go-live-plan.md](02-go-live-plan.md) §2.

| # | Action | Why it blocks |
|---|---|---|
| C1 | **Rotate the current access token** | It was shared over chat — treat as compromised |
| C2 | Replace it with a **Business Manager System User token** | The current one is personal and expires in ~60 days. When it dies, lead syncing stops silently |
| C3 | Take the Meta app **out of Development Mode** | Otherwise only app-role users' leads come through |
| C4 | Grant **Leads Access** on each page | Without it forms list but no leads arrive |
| C5 | Add a **Privacy Policy URL** | Meta requires it to leave development |
| C6 | Provide the **app secret** | Only needed for one-click reconnect; pasting a token works without it |
| C7 | Confirm **which forms are live, and what their phone field is called** | A form using an unexpected phone field name has its leads **silently discarded** |

## D. Go-live decisions

| # | Question | Notes |
|---|---|---|
| D1 | **Import the ~12,500 historical leads at all?** | Most are months old and already dealt with |
| D2 | If yes, **which stage** should they land in? | Suggested: a closed stage, so they do not read as open work. They arrive unassigned, already read, no emails sent |
| D3 | **Which system is the source of truth** while Superfone runs alongside? | Both read the same Facebook API independently — syncing here does **not** remove leads from Superfone. But a lead closed in one stays open in the other, so staff would work it twice |
| D4 | Does **Superfone history** need migrating? | Not planned or built |
| D5 | **New-lead emails on or off?** | Off by default. One busy campaign produces thousands of leads; switching it on carelessly floods inboxes and risks the sending domain |
| D6 | Confirm the **14 lead stages** match how the business works | Seeded from the client's existing pipeline |
| D7 | Real **staff list** — names, mobiles, emails | Mobile is the login identifier |

## E. Known gaps — for awareness, not decision

1. **Nobody is alerted when a sync fails.** It shows on the integration's Logs tab
   and nowhere else. If the token dies, the only symptom is leads not arriving.
2. **Custom form answers are only visible in the new-lead email** — there is no
   lead detail screen, so they cannot be seen on the Leads list.
3. **Telephony is not built** — no virtual number, call records or recordings. The
   Teams screen has a place for the number and fills in automatically once one exists.
4. **No billing** — Renew Plan and Buy Add-on are visibly disabled.
5. **A campaign with nobody mapped delivers invisible leads** — they arrive
   unassigned and only the Owner sees them.

---

## Also worth raising

- **Production needs a cron entry and a queue worker.** Without them no leads are
  ever pulled and no email is ever sent — and the app looks perfectly healthy
  throughout. Who owns the server?
- **SMTP is not configured.** Mail currently writes to a log file.
- The **To-Dos screen** was designed without a reference — worth showing the client.
