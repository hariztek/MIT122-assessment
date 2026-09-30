# Student SkillBridge — Assessment 2 Kanban Plan

Student: Harikrushna Patel (985703) · Solo project, lecturer-approved individual completion.
Scope: Assessment 2 implementation of the SkillBridge proposal (Assessment 1 is complete — proposal report and presentation delivered Tuesday 11 August 2026).
This file is a planning artefact. It does not contain application code and should not be used to scaffold the app.

---

## 1. Purpose

This board plans and tracks the Week 4–12 build of Student SkillBridge: the free peer-to-peer skill-learning platform described in the Assessment 1 proposal, now being implemented for Assessment 2 by the same student.

**Columns:** `To Do` → `In Progress` → `Testing` → `Done`

**Definition of Done** (per `REPORT_REQUIREMENTS.md` §3): a card only moves to Done when the work is **implemented, validated, tested, and documented**. "Validated" means it does what the acceptance criteria say; "tested" means it was exercised manually against the happy path and at least one edge case; "documented" means the relevant include/page has enough comment/context that the student (or another agent) can explain it in the final demo.

**Method:** personal Kanban board plus an iterative Agile approach — **not** full Scrum. This is a one-person project, so there are no team roles (Product Owner, Scrum Master) and no multi-person ceremonies. Work is pulled from a prioritised backlog in short weekly iterations, one end-to-end feature (vertical slice) at a time, demonstrated to the tutor each week, with feedback folded back into the backlog. This mirrors the methodology already committed to in the Assessment 1 report — this board is that methodology put into practice, not a new plan.

---

## 2. Prioritised product backlog

Grouped by feature area. Priority tags are Must / Should / Could (MoSCoW). Card IDs refer to the full seed list in §4 — read this section as the shape of the backlog, §4 as its executable detail.

### Environment setup — `SB-001`–`SB-008`
- **[Must]** Public GitHub repository (`SB-001`) — **already done**, matches the link in the proposal.
- **[Must]** Project file/folder scaffold: `index.php`, `pages/`, `includes/`, `assets/`, `database/`, `docs/` (`SB-002`) — **already done and committed**.
- **[Must]** Local MAMP (Apache + PHP + MySQL) environment (`SB-003`) — **already done** (document root points at this repo).
- **[Must]** MySQL database created, `database/schema.sql` written and imported — 6 core tables (`SB-004`, `SB-005`) — **already done**.
- **[Should]** Wireframes for login/register, profile, search, matches, dashboard (`SB-006`).
- **[Should]** ER diagram and site map committed to `docs/` (`SB-007`).
- **[Should]** Shared conventions note: one DB-connection file, one validation approach, shared header/footer (`SB-008`).

### Auth & profiles — `SB-009`–`SB-018`
- **[Must]** `includes/db.php`, `includes/functions.php`, `includes/header.php`/`footer.php` (`SB-009`–`SB-011`).
- **[Must]** `register.php` — FR-01, server-side validation + password hashing (`SB-012`).
- **[Must]** `login.php` — FR-02, credential check + session start (`SB-013`).
- **[Must]** `includes/auth.php` — session/authorisation guard reused by every protected page (`SB-014`).
- **[Must]** `profile.php` — edit profile (`SB-015`), add/edit/remove offered skills and wanted skills — FR-03/FR-04 (`SB-017`, `SB-018`).
- **[Must]** `index.php` landing page content (`SB-016`).

### Skills offered/wanted, search & listings — `SB-019`–`SB-022`
- **[Must]** Skill category taxonomy seeded in `skills` table (`SB-019`).
- **[Must]** `search.php` — keyword search across skills, FR-05 (`SB-020`), category filter (`SB-021`).
- **[Should]** Result pagination/listing UI polish (`SB-022`).

### Deterministic match scoring — `SB-023`–`SB-025`
- **[Must]** `matches.php` server-side score: **+50 skill, +25 mode, +15 availability, +10 experience** — FR-06, never AI/ML (`SB-023`).
- **[Must]** Plain-language explanation shown per match (`SB-024`).
- **[Should]** Ranked-results UI polish (`SB-025`).

### Session request lifecycle — `SB-026`–`SB-029`
- **[Must]** Send a request with goal + proposed time — FR-07 (`SB-026`).
- **[Must]** `dashboard.php` incoming/outgoing lists (`SB-027`).
- **[Must]** Accept/decline a pending request — FR-08 (`SB-028`).
- **[Must]** Cancel/complete an authorised request, creating a `sessions` row — FR-08 (`SB-029`).

### Reviews — `SB-030`–`SB-031`
- **[Must]** Review allowed only after a shared completed session — FR-09 (`SB-030`).
- **[Should]** Display reviews left/received on the dashboard (`SB-031`).

### Admin moderation — `SB-032`–`SB-035`
- **[Must]** `admin.php` role-restricted access guard (`SB-032`).
- **[Must]** Moderate user accounts — FR-10 (`SB-033`).
- **[Could]** Moderate individual skill listings (`SB-034`).
- **[Should]** Hide inappropriate reviews — FR-10 (`SB-035`).

### Integration testing, usability, documentation, demo — `SB-036`–`SB-044`
- **[Must]** End-to-end manual test pass across all 8 pages (`SB-036`).
- **[Must]** Responsive/cross-browser check — NFR (`SB-037`).
- **[Must]** Security pass: parameterised queries, session checks, hashing verified on every write path (`SB-038`).
- **[Must]** Usability fixes from testing + tutor feedback (`SB-039`).
- **[Must]** Installation/setup documentation — SLO-C (`SB-040`).
- **[Should]** In-code documentation pass (`SB-041`).
- **[Could]** Sync `REPORT_REQUIREMENTS.md`/`PROJECT_PLAN.md` if scope changed during the build (`SB-042`).
- **[Must]** Final rehearsal + demo script (`SB-043`).
- **[Must]** Final commit/tag + submission packaging (`SB-044`).

### Optional schema extensions — `SB-045`–`SB-046`
Named explicitly in `REPORT_REQUIREMENTS.md` as extensions to add **only if time allows**, not MVP:
- **[Could]** `availability` table — structured day/time slots, replacing the free-text field on `users` (`SB-045`).
- **[Could]** `reports` table — user-submitted moderation reports (`SB-046`).

### Never a To Do card — explicitly out of scope

Payments or time credits · real-time chat · video calling · AI/ML matching · file sharing · automatic email/SMS/push notifications · institutional login/SSO · native mobile apps.

These are excluded in the proposal itself (`REPORT_REQUIREMENTS.md` §2 Scope Boundaries, `PROJECT_PLAN.md` §2). If a future idea sounds like one of these, it does not get a card — flag it to the student instead of adding it to the backlog.

---

## 3. Week-by-week rough plan (Week 4 → Week 12)

Week 4 began **Monday 10 August 2026**. Assessment 1's presentation was Tuesday 11 August, so Week 4's Monday–Tuesday was consumed by that, not implementation — the plan below treats the **remaining Wednesday–Sunday of Week 4** (today is Wednesday 12 August) as the real start of build work, and does not claim Weeks 1–3 implementation happened, because no evidence in these docs says it did. Weekly themes below are unchanged from the Week 3–12 timeline already committed to in `REPORT_REQUIREMENTS.md` §3 — Week 4's theme was already "wireframes and database design," so no renumbering was needed, only calendar dates and an honest note about the lost two days.

| Week | Dates (Mon–Sun) | Theme | Cards into In Progress | Exit criteria / tutor-demo checkpoint | Risk notes |
|---|---|---|---|---|---|
| **4** | **10–16 Aug 2026** | Environment + wireframes + database design (compressed — only Wed–Sun available) | `SB-003`, `SB-005` (`SB-001`, `SB-002` already Done) | XAMPP running locally; `schema.sql` executed, 6 tables exist with FKs; at least the login/register and profile wireframes committed | Only ~5 working days instead of 7 because Mon–Tue went to the Assessment 1 presentation. If wireframes/ERD diagrams (`SB-006`, `SB-007`) slip, that's acceptable — they're Should, not Must, and the design is already fully specified in prose in `REPORT_REQUIREMENTS.md`. Do **not** let `schema.sql` slip; everything else depends on it. |
| **5** | **17–23 Aug 2026** | Authentication and profiles | `SB-009`–`SB-018` | A student can register, log in, land on a session-backed page, edit their profile, and add/remove at least one offered and one wanted skill, all validated server-side | Auth + profile + skill CRUD is a lot for one week. If behind, cut `SB-022` (pagination polish, already Should) and push it to Week 6 buffer — do not cut `SB-014` (`auth.php`), every later page depends on it. |
| **6** | **24–30 Aug 2026** | Skill listings and search | `SB-019`–`SB-022` | `search.php` returns correct results for a keyword query and a category filter against seeded data | Lower risk than Week 5 — mostly builds on `SB-014`/`SB-017`/`SB-018` already done. Good week to absorb any Week 4/5 slippage. |
| **7** | **31 Aug–6 Sep 2026** | Matching and session requests | `SB-023`–`SB-029` | A logged-in student sees ranked, explained matches, can send a request, and the receiving student can accept/decline it from `dashboard.php` | Heaviest week on the plan — matching logic plus the full request send/accept/decline/cancel/complete cycle in one week. If overloaded, finish `SB-023`/`SB-024`/`SB-026`/`SB-027`/`SB-028` (the visible, demoable half) and let `SB-029` (cancel/complete) spill one week — but flag it, since reviews (`SB-030`) depend on `SB-029`. |
| **8** | **7–13 Sep 2026** | Reviews and moderation | `SB-030`–`SB-035` | A completed session can be reviewed; an unreviewable pending session correctly refuses a review; admin can view and act on a reported user | Also busy. `SB-034` (moderate individual skill listings) is Could — the first card to drop if the week is tight, per `REPORT_REQUIREMENTS.md`'s own fallback ("merge admin into dashboard if workload is too high" is the more extreme version of this same pressure valve). |
| **9** | **14–20 Sep 2026** | Integration testing | `SB-036`–`SB-038` | Every one of the 8 pages walked end-to-end at least once; a written list of found bugs, not zero bugs | This is a testing week, not a feature week — resist adding new feature cards here. |
| **10** | **21–27 Sep 2026** | Usability fixes | `SB-039` | Bugs and usability issues found in Week 9 (plus any tutor feedback collected across the term) are triaged and the Must-priority ones are fixed | Scope this against what Week 9 actually found — don't plan fixes for problems that haven't been found yet. |
| **11** | **28 Sep–4 Oct 2026** | Documentation | `SB-040`–`SB-042` | Installation/setup instructions exist and were followed once from a clean XAMPP install; code has enough comments to explain in the demo | Low technical risk, easy to under-budget time for — Document Structure-equivalent effort (SLO-C) is graded, don't treat it as a 30-minute afterthought. |
| **12** | **5–11 Oct 2026** | Final demonstration and submission | `SB-043`, `SB-044` | Full rehearsed run-through of the live system; final commit pushed; GitHub link verified public and resolving | No new feature work this week under any circumstance — if something is unfinished, cut it from the demo script rather than rushing it in. |

---

## 4. Initial board seed (concrete cards)

**Estimate scheme (fixed for all cards):** `S` = up to 2 hours · `M` = 3–6 hours · `L` = 7–12 hours.

**Notion properties mapping (applies identically to every card below — stated once here rather than repeated 46 times):**

| This file's field | Notion property |
|---|---|
| Title | `Name` (title property) |
| Status | `Status` |
| Priority | `Priority` |
| Week | `Week` |
| Estimate | `Estimate` |
| Acceptance criteria | `Acceptance Criteria` |
| Depends on | `Dependencies` |
| Card ID (the `SB-0xx` prefix) | `Card ID` |
| — (implicit, same for every card in this file) | `Assessment / Project` = `SkillBridge · MIT122 A2` |

Almost all cards start in **To Do**. `SB-001`, `SB-002`, and `SB-003` are already **Done** — the GitHub repo, file scaffold, and local MAMP environment exist. `SB-005` is seeded as **In Progress** because writing `schema.sql` is the current active work.

| ID | Title | Status | Priority | Week | Est. | Depends on |
|---|---|---|---|---|---|---|
| SB-001 | Create public GitHub repository | Done | Must | 4 | S | — |
| SB-002 | Scaffold project structure (`index.php`, `pages/`, `includes/`, `assets/`, `database/`, `docs/`) | Done | Must | 4 | S | SB-001 |
| SB-003 | Install/verify local MAMP (Apache + PHP + MySQL) | Done | Must | 4 | S | — |
| SB-004 | Create MySQL database, import `schema.sql` | Done | Must | 4 | S | SB-005, SB-003 |
| SB-005 | Write `database/schema.sql` — 6 core tables + FKs (`users`, `skills`, `user_skills`, `session_requests`, `sessions`, `reviews`) | Done | Must | 4 | M | — |
| SB-006 | Wireframes: login/register, profile, search, matches, dashboard | To Do | Should | 4 | M | — |
| SB-007 | ER diagram + site map committed to `docs/` | To Do | Should | 4 | S | SB-005 |
| SB-008 | Write shared-conventions note (DB connection file, validation approach, includes) | To Do | Should | 4 | S | — |
| SB-009 | `includes/db.php` — PDO connection helper | Done | Must | 5 | S | SB-004 |
| SB-010 | `includes/functions.php` — shared validation helpers | Done | Must | 5 | S | — |
| SB-011 | `includes/header.php` / `footer.php` — shared layout | Done | Must | 5 | S | — |
| SB-012 | `register.php` — account creation (FR-01) | Done | Must | 5 | M | SB-009, SB-010, SB-011 |
| SB-013 | `login.php` — authentication + session start (FR-02) | Done | Must | 5 | M | SB-012 |
| SB-014 | `includes/auth.php` — session/authorisation guard | Done | Must | 5 | M | SB-013 |
| SB-015 | `profile.php` — edit profile fields | Done | Must | 5 | M | SB-014 |
| SB-016 | `index.php` — landing page content | Done | Must | 5 | S | SB-011 |
| SB-017 | `profile.php` — add/edit/remove offered skills (FR-03/FR-04) | Done | Must | 5 | M | SB-015 |
| SB-018 | `profile.php` — add/edit/remove wanted skills (FR-03/FR-04) | Done | Must | 5 | M | SB-017 |
| SB-019 | Seed skill category taxonomy in `skills` table | Done | Must | 6 | S | SB-004 |
| SB-020 | `search.php` — keyword search (FR-05) | Testing | Must | 6 | M | SB-019 |
| SB-021 | `search.php` — category filter | Testing | Must | 6 | S | SB-020 |
| SB-022 | `search.php` — pagination/listing UI polish | Testing | Should | 6 | S | SB-020 |
| SB-023 | `matches.php` — deterministic score calc (+50/+25/+15/+10, FR-06) | Testing | Must | 7 | L | SB-017, SB-018 |
| SB-024 | `matches.php` — plain-language explanation per match | Testing | Must | 7 | M | SB-023 |
| SB-025 | `matches.php` — ranked-results UI polish | Testing | Should | 7 | S | SB-023 |
| SB-026 | Send session request with goal + proposed time (FR-07) | Testing | Must | 7 | M | SB-023 |
| SB-027 | `dashboard.php` — incoming/outgoing request lists | Testing | Must | 7 | M | SB-026 |
| SB-028 | Accept/decline a pending request (FR-08) | Testing | Must | 7 | M | SB-026, SB-027 |
| SB-029 | Cancel/complete an authorised request → creates `sessions` row (FR-08) | To Do | Must | 7 | M | SB-028 |
| SB-030 | Review allowed only after shared completed session (FR-09) | To Do | Must | 8 | M | SB-029 |
| SB-031 | Dashboard: show reviews left/received | To Do | Should | 8 | S | SB-030 |
| SB-032 | `admin.php` — role-restricted access guard | Testing | Must | 8 | S | SB-014 |
| SB-033 | Admin: moderate user accounts (FR-10) | Testing | Must | 8 | M | SB-032 |
| SB-034 | Admin: moderate individual skill listings | To Do | Could | 8 | S | SB-032, SB-019 |
| SB-035 | Admin: hide inappropriate reviews (FR-10) | To Do | Should | 8 | S | SB-032, SB-030 |
| SB-036 | End-to-end manual test pass, all 8 pages | To Do | Must | 9 | L | SB-035 |
| SB-037 | Responsive / cross-browser check | To Do | Must | 9 | M | SB-036 |
| SB-038 | Security pass — parameterised queries, session checks, hashing on every write path | To Do | Must | 9 | M | SB-036 |
| SB-039 | Usability fixes from testing + tutor feedback | To Do | Must | 10 | L | SB-036, SB-037, SB-038 |
| SB-040 | Installation/setup documentation (SLO-C) | To Do | Must | 11 | M | SB-039 |
| SB-041 | In-code documentation pass | To Do | Should | 11 | M | SB-039 |
| SB-042 | Sync planning docs if scope changed during build | To Do | Could | 11 | S | SB-039 |
| SB-043 | Final rehearsal + demo script | To Do | Must | 12 | M | SB-040, SB-041 |
| SB-044 | Final commit/tag + submission packaging | To Do | Must | 12 | S | SB-043 |
| SB-045 | *(Optional, only if ahead of schedule)* `availability` table — structured day/time slots | To Do | Could | 6 | M | SB-005 |
| SB-046 | *(Optional, only if ahead of schedule)* `reports` table — user-submitted moderation reports | To Do | Could | 8 | M | SB-005, SB-032 |

### Acceptance criteria per card

Written once per card here rather than duplicated a third time in the table above.

- **SB-003** — XAMPP installed; Apache serves a test PHP file; MySQL service starts; `phpMyAdmin` reachable at `localhost/phpmyadmin`.
- **SB-004** — a named MySQL database exists; `schema.sql` runs against it without error; all 6 tables visible in `phpMyAdmin`.
- **SB-005** — `schema.sql` defines `users`, `skills`, `user_skills`, `session_requests`, `sessions`, `reviews`; foreign keys match the relationships in `REPORT_REQUIREMENTS.md` §Suggested ERD; script is idempotent (safe to re-run on a fresh DB).
- **SB-006** — one wireframe image/sketch per named page group (login/register, profile, search, matches, dashboard), saved under `docs/`.
- **SB-007** — ER diagram shows all 6 tables and the relationships listed in `REPORT_REQUIREMENTS.md`; site map lists all 8 pages and links between them.
- **SB-008** — short note stating: the one DB-connection file, the one validation approach, and the shared include files every page must use.
- **SB-009** — `db.php` returns a working PDO connection; throws/logs cleanly on bad credentials; included by at least one other file without error.
- **SB-010** — at least one reusable validation function (e.g. required-field check) used by two or more pages.
- **SB-011** — header/footer render on a test page with no duplicated markup.
- **SB-012** — invalid input rejected server-side even if JS is disabled; password stored hashed, never plaintext; duplicate email rejected.
- **SB-013** — wrong password rejected; correct login starts a session; session persists across a page reload.
- **SB-014** — an unauthenticated request to a protected page redirects to login; an authenticated request passes through.
- **SB-015** — profile fields save and reload correctly; invalid input rejected server-side.
- **SB-016** — landing page explains the service; links to register/login.
- **SB-017 / SB-018** — a skill can be added, edited, and removed; only the owning student can edit/remove their own entries; level/mode/availability/description all persist.
- **SB-019** — at least the categories named in the proposal (technology, creative, languages, career/study, practical) exist as rows.
- **SB-020 / SB-021** — a keyword match returns the expected skill; a category filter narrows results correctly; query is parameterised (no string-concatenated SQL).
- **SB-023** — score matches the documented weights exactly for a hand-checked test case; calculation happens in PHP, never client-side, never via an external/AI service.
- **SB-024** — each match shows which components of the score fired (e.g. "skill matched (+50), mode matched (+25)").
- **SB-026** — a request requires a goal and a proposed time; a student cannot request a session with themselves.
- **SB-027 / SB-028** — incoming vs outgoing requests are visually distinct; accept/decline only available to the receiving student; state change persists.
- **SB-029** — cancel/complete only available to an authorised party on that request; completing creates exactly one `sessions` row.
- **SB-030** — review form only appears/succeeds when a completed session exists between the two users; server re-checks this even if the form is bypassed.
- **SB-032** — a non-admin account cannot reach `admin.php` functionality, even via direct URL.
- **SB-033** — admin can view a user and take at least one moderation action (e.g. suspend); action persists.
- **SB-036** — a written test log exists covering all 8 pages with pass/fail per flow.
- **SB-037** — checked at a mobile width and a desktop width in at least one real or emulated browser.
- **SB-038** — every `INSERT`/`UPDATE`/`SELECT` built from user input uses PDO/MySQLi parameter binding; grep for string-concatenated SQL returns nothing.
- **SB-039** — every Must-priority bug found in `SB-036`–`SB-038` is either fixed or explicitly deferred with a reason.
- **SB-040** — a person with a clean machine could follow the doc and get the app running.
- **SB-043** — full run-through timed against the actual demo slot; a fallback plan exists if something breaks live.
- **SB-044** — final state pushed to `main`; GitHub link opens in a private/incognito window and is readable.

---

## 5. Instructions for the Notion agent

Copy everything in this section to give to an agent with Notion access.

> **Goal:** create or update a **SkillBridge Kanban** board inside the existing **Assessment** database/workspace in Notion, seeded from the card list in `kanban.md` §4 (Student SkillBridge, MIT122 Assessment 2, Harikrushna Patel, 985703).
>
> **Step 0 — inspect before you create.** Before adding anything, find the existing **Assessment** database in this Notion workspace and read its current properties. Do not create a duplicate database. If it already has equivalent fields (e.g. an existing `Status` or `Priority` select), **map onto those instead of creating near-duplicates** — only add a property from the list below if nothing equivalent already exists.
>
> **Required database properties** (create only what's missing, per Step 0):
>
> | Property | Type | Notes |
> |---|---|---|
> | `Name` | Title | Card title, e.g. "Write database/schema.sql — 6 core tables + FKs" |
> | `Status` | Status or Select | Options: `To Do`, `In Progress`, `Testing`, `Done` — **exactly these four, no more.** Do not invent extra workflow states even if that feels tidier; if the existing DB already has more states, ask the user before changing them. |
> | `Priority` | Select | Options: `Must`, `Should`, `Could` |
> | `Week` | Number or Select | Target week number, 4–12 |
> | `Estimate` | Select | Options: `S`, `M`, `L` |
> | `Acceptance Criteria` | Rich text | 3–5 bullet points, copied from `kanban.md` §4 |
> | `Dependencies` | Relation (preferred) or Rich text | Other Card IDs this card depends on |
> | `Card ID` | Rich text | Stable ID, e.g. `SB-023`. **Must be unique** — this is the idempotency key (see below). |
> | `Assessment / Project` | Select or Relation | Value: `SkillBridge · MIT122 A2` for every card |
>
> **Board view:** create (or reuse, if one already exists for this project) a **Kanban board view grouped by `Status`**, with columns in this exact order: `To Do`, `In Progress`, `Testing`, `Done`.
>
> **Import order:**
> 1. Create/confirm the properties above.
> 2. Create all 46 cards from `kanban.md` §4 with `Status = To Do`.
> 3. Then set `SB-001`, `SB-002`, and `SB-003` to `Status = Done`, and `SB-005` to `Status = In Progress` — this reflects real, already-completed work (the GitHub repo, file scaffold, and MAMP environment exist; schema writing is the current active work), not aspirational status.
> 4. Populate `Dependencies` using Card IDs, not free text, if the relation type is used.
>
> **Idempotency:** before creating a card, check whether a card with that `Card ID` already exists in the database. If it does, **update its properties in place** — do not create a duplicate. This board is expected to be re-imported/re-synced as the project progresses, so this check matters every time, not just on first run.
>
> **After import:** confirm the Kanban view renders correctly grouped by `Status` with all four columns visible and populated, then paste the Notion page/database URL back to the user.
>
> **If anything fails** — Notion auth missing, insufficient permissions, the Assessment database can't be found — **stop and report exactly what's missing**. Do not fabricate a board, a URL, or a success message. Ask the user rather than guessing which existing database is "the Assessment one" if more than one plausible match exists.

---

## 6. How to use this file

- **Weekly review (do this every Sunday):** pull the next Must-priority cards for the upcoming week from §4 into `To Do`/`In Progress`; move anything finished through `Testing` → `Done` only once it meets the Definition of Done in §1 (implemented, validated, tested, documented) — not just "code exists."
- **Tutor demo:** each week, demo whatever is in `Testing`/`Done` to the tutor; fold feedback back into the backlog as new or revised cards rather than silently changing existing ones.
- **Replanning:** if a week's cards don't finish, decide explicitly whether to extend into the next week's buffer or drop a Should/Could card — don't silently let scope creep into the next week without a decision.
- **Source of truth:** `kanban.md` (this file) is the source of truth for card content. If the Notion board and this file disagree, this file wins until someone deliberately re-syncs.
