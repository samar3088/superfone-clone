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
| B7 | A **member can do their own work but delete nothing at all** | See B7 below — is anything on the "cannot" list something a member should be able to do? |
| B8 | A note with **several leads to choose from must say which one** | See B8 below — should we instead default to the newest lead and not ask? |
| B9 | **Reminders first, then Fresh Leads, then Follow Ups** | See B9 below — three points, including whether a completed first call should move a lead out of Fresh |
| B10 | **An imported number already on file is matched, not skipped** | See B10 below — Superfone skips it; we attach the new details to the contact already there |

### B10. What the contact import does with a number already on file

The wizard follows the client's own three steps — upload and check, choose the
settings, done — and reads the client's template columns exactly: FIRST NAME,
LAST NAME, PRIMARY PHONE, SECONDARY PHONE, BUSINESS NAME, CITY, ADDRESS, EMAIL,
WEBSITE URL, ADDITIONAL INFO. Files built against our earlier template still
import; those headings are kept as unadvertised aliases.

One deliberate difference from Superfone:

| | Superfone | Here |
|---|---|---|
| Number already on file | *"Existing contacts will be skipped"* | **Matched.** The row's extra numbers and emails are attached to the contact already on file |
| "Update existing contact if found" | Overwrites | Overwrites the contact's **details**; the numbers and emails are attached either way |

The reasoning: attaching a second number to someone already in the book is the
duplicate rule the client has already approved (see
[01-lead-duplicate-rules.md](01-lead-duplicate-rules.md)), not an edit of their
record. Skipping the row outright would silently throw that number away, and
the next enquiry from it would look like a new person.

> **Confirm:** should a row whose number is already on file be skipped
> completely instead, matching Superfone exactly?

Also worth noting: **nothing is written until Submit on step two.** The file is
checked and set aside first, so a bad file costs nothing and the settings are
chosen once the rows are known to be good.

**Downloading** is the mirror image. The dialog offers the same ten template
columns first, spelled identically and in the same order, then ours — tags,
lead stage, lead owner, team, counts, dates. Tick the first ten and the file
that comes out **imports straight back**, which is how a bulk update is
actually done. There is a test that downloads and re-imports to keep that true.

One difference worth flagging: Superfone caps a download at **one month of
data**. We do not. Exports here stream row by row rather than being built in
memory, so the size of the range costs nothing — a cap would be a limitation
copied for no reason.

> **Confirm:** is the one-month cap something the client actually wants kept?

### B9. What the three To-Dos tabs hold

Read in this order. The order is what makes the three exhaustive and
non-overlapping — every to-do lands on exactly one, so nothing is hidden and
nothing is counted twice.

| # | Tab | Holds |
|---|---|---|
| 1 | **Reminders** | Anything of type **REMINDER**, whatever its lead is doing |
| 2 | **Fresh Leads** | Anything else that is new work — see below |
| 3 | **Follow Ups** | Everything left |

**Fresh Leads** takes a to-do if *any one* of these is true:

- the to-do is a **FIRST CALL**. Nothing is more clearly new work, so it stays
  Fresh even after the lead has moved on.
- the lead is still at **New Inquiry**. Keyed on the stage's `INITIAL` type, not
  the name — the client can rename stages in Settings, and a rule keyed on a
  name breaks silently when they do.
- **nobody has touched the lead**: its version is still 1, so no stage or owner
  change, and nothing on it has been ticked off.

**Second-line filters differ by tab**, because a single row would be wrong on
two of the three:

| Tab | Second line |
|---|---|
| Fresh Leads | **None.** The tab is partly defined by task type, so a type filter would contradict the list beneath it |
| Follow Ups | Task-type chips, minus FIRST CALL and REMINDER — neither can appear here |
| Reminders | How soon it falls due: **Overdue · 1 day · 2 days · 3 days · Later** |

The due buckets **tile rather than nest** — something due tomorrow afternoon is
in *2 days* only, not in *1 day* as well — so the row reads as a countdown
rather than as running totals. Counted from now, not from midnight: *due in 1
day* means twenty-four hours.

> **Confirm three things.**
>
> 1. A lead still at **New Inquiry stays in Fresh Leads even after its first
>    call is ticked off**, because *"or status is new inquiry"* was given as its
>    own condition. If a completed call should move it out, that is a one-line
>    change.
> 2. Is **FIRST CALL** the only type that should hold a to-do in Fresh Leads?
> 3. Are **1 / 2 / 3 days and Later** the right due buckets, or does the client
>    want a different horizon — this week, this month?

### B7. What a team member can and cannot do

Members sign in and work their own leads. The line drawn — and proven by tests,
so it cannot drift — is **members delete nothing**.

**A member can:**

- open the Dashboard, Leads, To-Dos, Customers and their own profile
- move their own lead through the stages
- complete and reopen the to-dos assigned to them
- add a contact, and import contacts
- write notes, and edit their own
- export the leads they can see

**A member cannot:**

- delete anything — a team member, tag, lead stage, integration, note, or the
  stored Facebook token
- **merge two contacts.** A merge archives the losing record and moves its leads
  and calls away, so it is a deletion wearing a friendlier name — owner only
- open Settings, the Activity log, or the Teams screen
- touch a to-do assigned to somebody else
- add or change team members, or change any setting

> **Confirm:** is anything on the second list something a member should be able
> to do day to day? Each is a one-line change, but each also removes a guardrail.

### B8. Which lead a note belongs to

A note is **always** filed under the contact. Whether it is *also* filed under
one of their enquiries depends on how many they have:

| Contact has | What happens |
|---|---|
| **No leads** | Saved against the contact, no question asked |
| **One lead** | That lead is pre-selected; *"This contact (not a specific lead)"* stays available |
| **Several leads** | A choice is required — nothing is saved until one is picked |

The reasoning for the third row: a note filed against the wrong enquiry is read
by the wrong person at the wrong moment, and nobody ever notices it moved. The
prompt costs one click; the mistake costs a customer.

Because every note also carries the contact, archiving a lead never loses a
note — it simply reads as a note about the contact from then on.

> **Confirm:** would the client rather we never asked, and always attached the
> note to the most recent lead? Faster to write, and occasionally wrong.

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
- The **To-Dos screen** now follows the client's reference: Fresh Leads / Follow
  Ups / Reminders tabs, task-type chips, a usage-by-team summary and a card per
  to-do with call and WhatsApp actions. What each tab holds, and which
  second-line filter it carries, is B9 above.
- **Two things are called notes.** The contact record has a single free-text
  field, filled when the contact is created or imported; separately there is a
  dated note trail (B8). The contact page labels the first *"On the contact
  record"* to keep them apart. If the client only wants one, the field can be
  folded into the trail.
