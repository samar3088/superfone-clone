# Client verification

Decisions made **without** the client in the room, and what still needs their
sign-off before go-live.

## Why this folder exists

Building a CRM means answering dozens of small questions the specification does
not cover — *is this a duplicate?*, *what counts as closed?*, *who gets a repeat
enquiry?* Waiting on an answer for each would stall the work; guessing silently
would ship the wrong product.

So when a question comes up, VVT gives a working answer, we build on it, and it
gets written down here with the reasoning. Nothing in these documents is
final until the client confirms it.

Each entry states:

- **what was decided** and by whom
- **why**, so the client can disagree with the reasoning rather than just the outcome
- **what it would take to change**, so a "no" is a small change and not a rebuild

## Documents

| | Document | Covers | Needs client sign-off |
|---|---|---|---|
| 01 | [Lead duplicate rules](01-lead-duplicate-rules.md) | When a repeat enquiry counts as a duplicate rather than fresh business | Yes — 6 points |
| 02 | [Go-live plan](02-go-live-plan.md) | Everything that must happen before and during launch | Yes — client actions in §2 |
| 03 | [Open questions](03-open-questions.md) | Everything the build is currently guessing at, ordered by cost of a late answer | Yes — the whole document |
| 04 | [Restore points](04-restore-points.md) | How to put the project back the way it was, and what each restore point contains | No — reference |

## Keeping these current

**These are living documents.** They are updated as the project moves, not
written once and abandoned:

- a new assumption gets a new section, dated
- an answered question is marked **Confirmed** with the date and who confirmed it
- when something is built differently from what is written here, the document
  changes in the same commit as the code

If a document contradicts the running system, the document is the bug.
