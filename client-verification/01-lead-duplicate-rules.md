# Lead duplicate / repeat detection — for client confirmation

**Status:** Built and working, on assumptions that still need the client's sign-off.
**Date:** 25 July 2026
**Decided by:** VVT internal (Shank), pending client confirmation.

---

## Why this document exists

We needed to answer one question — *"is this a new enquiry, or one we already have?"* —
before we could build it. The client was not available, so VVT gave us working
answers and asked that we proceed on those and record what needs confirming.

**Everything in Section 2 is live in the system now.** If the client disagrees with
any of it, each item is a small change, not a rebuild. Section 3 lists the things
nobody has ruled on yet.

---

## 1. The three situations, kept separate

These get confused with each other, so they are named plainly:

| # | Situation | What happens | Configurable? |
|---|---|---|---|
| 1 | **The identical Facebook submission reaches us twice** — a re-sync, an overlapping window | Silently ignored. Never becomes a second record. | No — this is data integrity, not policy |
| 2 | **The same person fills in the same campaign form again** | Marked **Repeat** | Yes — see 2.1 |
| 3 | **The same person enquires through a different campaign** | Treated as a **Fresh** lead | No — see 2.2 |

Situation 1 was already handled and is not in question. Situations 2 and 3 are what
this document is about.

---

## 2. What we built, and the assumption behind each

### 2.1 A repeat only counts for **2 days**

If the same person fills in the same campaign form again **within 2 days**, the second
one is marked *Repeat*. After that, it is a *Fresh* lead.

**Why a time limit at all:** without one, somebody who enquired in January and again in
July would be marked a repeat forever. Six months apart is genuinely new business.
Ten minutes apart is genuinely a duplicate.

**Changeable by the client themselves:** Settings → Notifications → *Repeat enquiry window*.
Setting it to `0` switches repeat detection off entirely.

> **Confirm:** is 2 days right for this business? For a wedding-planning enquiry,
> a customer may well come back a week or two later while still deciding — those
> would currently be marked *Fresh* and appear as new work.

### 2.2 A different campaign is always a Fresh lead

The same person coming through a *different* form is new business — a different
intent, and usually a different team member's work.

> **Confirm:** the client may want same-person-different-campaign flagged for
> awareness even while still counting as fresh work. That is not built.

### 2.3 "Same campaign" means the same **integration**, not the same name

We key this on the integration record, not the campaign's text label.

**Why:** the label is Facebook's form title. Two integrations can carry the same
title, and renaming the form on Facebook would silently change it underneath us —
past leads would then stop matching future ones for no visible reason.

*No client decision needed. Recorded because it is a change from how it first worked.*

### 2.4 Identity is **mobile first, then email**

A person is matched by mobile number. If the mobile does not match anyone, we then try
the email. Mobile wins wherever the two disagree.

**Consequence worth knowing:** if the same person submits with the same email but a
*different* mobile, they are matched as the same customer. If they use the same mobile
with a different email, likewise.

**Updated 26 July 2026 — a contact now holds *several* numbers and addresses.** Matching
searches every one of them, not just the primary pair. Someone who first enquired on one
number and later rings from another is one customer, and the new number is added to their
record so it matches next time too. Merging carries the numbers across; without that a
merged-away number would stop matching and the next enquiry from it would open a third
record. The existing customers were backfilled, so this covers history too.

> **Confirm:** should a same-email-different-mobile match still count as the same
> person? We assumed yes, with mobile preferred. The stricter alternative — require
> *both* to match — would create more separate customer records.
>
> **Also confirm:** a contact accumulating many numbers over time is intended, and one
> number can never belong to two contacts — the database refuses it outright.

### 2.5 Coming back after a won or lost sale is a **Fresh** lead

If the earlier enquiry has already reached *Sale Closed* or *Not Interested*, the new
one is Fresh, not a repeat. Finished business; someone returning after that is a new
opportunity worth working.

> **Confirm:** this currently applies to *Sale Closed*, *Not Interested* and *Cold* —
> every stage marked as a final outcome. If the client wants *Cold* treated as still-open,
> that is a one-line change in Settings → CRM Settings.

### 2.6 Repeats are **not** hidden, merged or auto-closed

A repeat is still a full lead, still assigned, still worked. It carries a **Repeat**
badge on the Leads list and can be filtered with *Fresh & repeat*, and the CSV export
carries a **Kind** column.

> **Confirm:** the client may expect repeats to be merged into the original or skipped
> entirely. We deliberately did not do that — deleting or hiding an enquiry the
> customer actually made is not something to guess at.

### 2.7 Assignment is unchanged for repeats

A repeat is assigned by the **campaign's own member mapping**, exactly like any other
lead. It does *not* go back to whoever handled the customer last.

*Decided by VVT on 25 July 2026. Recorded because it is a plausible thing to want
differently — a returning customer reaching the same person they spoke to before.*

---

## 3. Open — nobody has ruled on these yet

1. **Repeats across campaigns for reporting.** A customer who enquired on three
   campaigns counts as three fresh leads. If conversion rates are measured per
   campaign that is right; if measured per customer it overstates the total.

2. **What a repeat should do beyond being labelled.** *Now built:* the campaign's
   *Create to do on existing lead* rule raises a to-do against the assignee, with its
   own task type, title and due time, separate from the New Lead rule. Both appear on
   the **To-Dos** screen.

   > **Confirm:** whether the notification and to-do wording is right for the business,
   > and what the default due times should be per campaign.

3. **Leads with no usable mobile number are dropped entirely.** A Facebook form using an
   unexpected field name for the phone number would have its leads silently discarded.
   Worth confirming which forms are in use and what their phone field is called.

4. **Whether the client wants a Duplicates review screen** — a list of repeats to
   confirm or reject by hand, rather than the system deciding alone.

---

## 4. How to change any of this

| Change | Where |
|---|---|
| The 2-day window | Settings → Notifications → Repeat enquiry window |
| Turn repeat detection off | Same, set to `0` |
| Which stages count as "finished" | Settings → CRM Settings → Lead Stage → Type |
| Re-check existing leads after a change | `php artisan leads:repair-duplicates` |

**Note on changing the window:** leads already in the system keep the verdict they were
given. Changing the rule does not silently rewrite history — re-judging is a deliberate
step via the command above.

---

## 5. What we verified

Fifteen automated tests cover every branch: first enquiry, same campaign inside and
outside the window, different campaign, won and lost outcomes, two campaigns sharing a
name, a renamed form, window changes including zero, and the repair pass.

One case is worth calling out because it is not obvious. Facebook returns leads
**newest first**, so during the go-live import of historical leads, a customer's third
enquiry arrives before their first. Judged as written, the flags come out inverted. The
import therefore re-checks every affected customer once the full history is present, and
this is covered by a test.
