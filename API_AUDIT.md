# API Audit — backend vs. the Flutter client

Companion to `API_REFERENCE.md` (the contract) and `API_NOTES.md` (build
decisions). This file records an endpoint-by-endpoint audit of the deployed API
against what `new_job_portal` (the Flutter app) **actually calls**, and what was
changed as a result.

Method: every endpoint constant in the app's `AppConstants`, every
`_api.*()` call in `lib/app/data/repositories/`, and every `fromApi()` parser in
`lib/app/data/models/` was read and compared against the matching route,
controller and API Resource. Findings were then confirmed by executing them —
not by reading code alone. Regression coverage lives in
`tests/Feature/ApiAuditRegressionTest.php`.

**Status legend**

| | Meaning |
|---|---|
| ✅ | Verified — implemented, matches the app's expectations, covered by tests |
| 🟡 | Existed, but modified by this audit |
| 🔴 | Was missing — created by this audit |
| ⚠️ | Needs Flutter-side work before it does anything for the user |

---

## 1. Audit result at a glance

The backend was in far better shape than a route-existence check would suggest:
59 of 60 endpoints the app needs were present, correct, and behaving as the
client's parsers expect. Three defects and one missing endpoint were found.

| # | Finding | Severity | Status |
|---|---|---|---|
| 1 | "New application" notification to the recruiter rendered with **no candidate name** | High — recruiter-visible text | 🟡 Fixed |
| 2 | New-message notification fell back to the generic "A candidate" | Medium | 🟡 Fixed |
| 3 | `GET /conversations` (the app's Conversations screen) did not exist | High — screen with no endpoint | 🔴 Created |
| 4 | `applicants_count` computed on `/recruiter/jobs/mine` but never returned | Medium — forced N+1 from the client | 🟡 Fixed |
| 5 | Applicant list lazy-loaded `interview` once per row | Low — N+1 | 🟡 Fixed |

Nothing else required a change. In particular the response envelope, ID
encoding, pagination `meta`, error contract, auth/role guards, ownership checks,
Smart Apply gating and the frozen application snapshot were all verified correct
against the client and left untouched.

---

## 2. Findings in detail

### Finding 1 — the recruiter was never told who applied 🟡

**Symptom.** A real recruiter's notification read:

```
" applied for Staff Nurse."
```

**Cause.** `Notifier::applicationSubmitted()` read `$application->candidate->name`
— the `users.name` column. Authentication is OTP-only (§2.2): signup asks for a
phone and a role and nothing else, so `users.name` is **null for every real
account**. A candidate's actual name lives on `candidate_profiles.name`, which
is where the app writes it.

**Why tests passed anyway.** `UserFactory` sets `'name' => fake()->name()`, so
every existing test had a column populated that no production row ever has. The
regression test registers through the real `/auth/otp/send` → `/auth/otp/verify`
flow instead, and asserts `users.name` is null before going on.

**Fix.** `User::displayName()` resolves a person's name from the profile they
actually filled in (candidate profile name, or recruiter contact name), falling
back to `users.name` then to a caller-supplied default.
`Application::candidateName()` prefers the **frozen snapshot** name over the
live profile, so a notification and the applicant card it links to can never
disagree.

### Finding 2 — chat notifications said "A candidate" 🟡

Same root cause: `$application->candidate->name ?? 'A candidate'` resolved null
→ the fallback, every time. Now routed through `Application::candidateName()`.

### Finding 3 — `GET /conversations` was missing 🔴

**Symptom.** The app has a Conversations screen
(`lib/app/modules/conversations/`) whose `ConversationEntry` needs, per row: a
conversation id, a title, a subtitle, and the last message — sorted by
last-message time. No endpoint returned any of that.

The screen currently builds the list client-side from local services. Wiring it
to the API would have meant one `GET /conversations/{id}/messages` call **per
row** just to render previews.

**Built.** `GET /conversations` — see `API_REFERENCE.md` §11. Every application
is a thread whether or not anyone has spoken; `title`/`subtitle` are resolved
for the caller's role (candidate sees the job, recruiter sees the person);
`unread_count` and `last_message` come back with the row. Threads with traffic
sort above silent ones. Paginated.

Ownership is enforced the same way as everywhere else: a candidate's threads are
their own applications, a recruiter's are applications against their own
postings, and there is no parameter that could widen either.

### Finding 4 — `applicants_count` was computed and discarded 🟡

`Recruiter\JobController::mine()` already called `withCount('applications')`,
but `JobResource` never exposed the resulting `applications_count`. The
recruiter's My Posted Jobs and Home screens both show a per-job applicant count,
so the client's only option was a `/stats` call per card.

Now returned as `applicants_count`, using the same conditional-presence pattern
as `is_saved`/`has_applied` — so it appears **only** where it was counted, and a
candidate never learns how many people they are competing with.

### Finding 5 — N+1 on the applicant list 🟡

`ApplicantResource` renders `interview` inline, but
`ApplicantController::index()` did not eager-load it — one extra query per
applicant row. Added `->with('interview')`.

---

## 3. Full endpoint inventory

Auth column: `none` = public, `optional` = richer with a candidate token,
`candidate`/`recruiter` = role-guarded, `any` = any authenticated user.

### Authentication (§2)

| Feature | API | Method | Auth | Status | Action |
|---|---|---|---|---|---|
| Send OTP | `/auth/otp/send` | POST | none | ✅ | Verified — 3/phone/10min, per-IP backstop |
| Verify OTP | `/auth/otp/verify` | POST | none | ✅ | Verified — mints token, `is_new_user` |
| Refresh token | `/auth/refresh` | POST | any | ✅ | Verified — client auto-calls on 401 |
| Logout | `/auth/logout` | POST | any | ✅ | Verified |

Password login, email verification and forgot/reset password are **not
applicable** — this product authenticates by OTP only. Guest access is handled
by the `guest.token` middleware on the browse endpoints, not by a guest login.

### Reference data (§10)

| Feature | API | Method | Auth | Status | Action |
|---|---|---|---|---|---|
| Option lists + enums + upload limits | `/config/options` | GET | none | 🟡 | **Extended** — added `salary_filters`, `specializations`, `designations`, `institutes`, `skills_by_category`, `city_coordinates`, each of which replaced a hardcoded list in the app |

### Jobs — public browse (§4)

| Feature | API | Method | Auth | Status | Action |
|---|---|---|---|---|---|
| Job listing + search/filter/sort | `/jobs` | GET | optional | ✅ | Verified — category, query, city, experience[], job_type[], shift[], min_salary, pagination |
| Job details | `/jobs/{jobId}` | GET | optional | ✅ | Verified |
| Categories | `/jobs/categories` | GET | none | ✅ | Verified — zero-count categories retained |
| Search suggestions | `/jobs/search/suggestions` | GET | none | ✅ | Verified |
| Trending searches | `/jobs/search/trending` | GET | none | ✅ | Verified |

Recently-viewed and cross-device search history are device-local by design
(§4.5) — deliberately no endpoint.

### Candidate profile (§3)

| Feature | API | Method | Auth | Status | Action |
|---|---|---|---|---|---|
| Get profile | `/candidate/profile` | GET | candidate | ✅ | Verified |
| Update basics + home location + Smart Apply fields | `/candidate/profile` | PATCH | candidate | ✅ | Verified — partial |
| Job preferences | `/candidate/profile/preferences` | PATCH | candidate | ✅ | Verified |
| Skills + proficiency | `/candidate/profile/skills` | PUT | candidate | ✅ | Verified — full replace |
| Certifications + years | `/candidate/profile/certifications` | PUT | candidate | ✅ | Verified — full replace |
| Languages + levels | `/candidate/profile/languages` | PUT | candidate | ✅ | Verified — full replace |
| About me | `/candidate/profile/about` | PATCH | candidate | ✅ | Verified |
| Upload resume | `/candidate/profile/resume` | POST | candidate | ✅ | Verified — multipart, private+signed |
| Generate resume from profile | `/candidate/profile/resume/generate` | POST | candidate | ✅ | Verified |
| Profile photo | `/candidate/profile/photo` | POST | candidate | ✅ | Verified |
| Intro video | `/candidate/profile/intro-video` | POST | candidate | ✅ | Verified — duration re-checked server-side |
| Remove intro video | `/candidate/profile/intro-video` | DELETE | candidate | ✅ | Verified |
| Education CRUD | `/candidate/profile/educations[/{id}]` | POST/PATCH/DELETE | candidate | ✅ | Verified |
| Experience CRUD | `/candidate/profile/experiences[/{id}]` | POST/PATCH/DELETE | candidate | ✅ | Verified |

Profile strength / hiring chance is computed server-side and returned as
`profile_strength` on every profile read; the client trusts it over its local
calculation. Verified — weights match the app's `CandidateProfile.weights`
exactly.

### Saved jobs (§5)

| Feature | API | Method | Auth | Status | Action |
|---|---|---|---|---|---|
| List saved | `/candidate/saved-jobs` | GET | candidate | ✅ | Verified — `is_saved` forced true |
| Save/unsave (toggle) | `/candidate/saved-jobs` | POST | candidate | ✅ | Verified — 201 saved / 200 unsaved |
| Explicit unsave | `/candidate/saved-jobs/{jobId}` | DELETE | candidate | ✅ | Verified — idempotent |

### Applications — Smart Apply (§6)

| Feature | API | Method | Auth | Status | Action |
|---|---|---|---|---|---|
| What's still missing for this job | `/applications/requirements/{jobId}` | GET | candidate | ✅ | Verified — `required_fields`, `missing_fields`, `can_apply`, `already_applied` |
| Apply | `/applications` | POST | candidate | ✅ | Verified — re-check server-side, 422 lists gaps, embeds `job` |
| Application history | `/applications` | GET | candidate | ✅ | Verified — `?status=` filter |
| Application details + timeline | `/applications/{applicationId}` | GET | candidate | ✅ | Verified — adds `profile_snapshot`, `timeline` |

**Progressive profile (task Step 6) — verified working as specified.** The
server never asks twice: `ApplicationService::missingFields()` intersects the
job's `required_fields` with what the live profile already satisfies
(`ProfileField::isSatisfiedBy`), so the second application only asks for what
the first didn't collect. Nothing is duplicated — answers are written to the
candidate's profile, and the application stores one immutable snapshot of it.

Withdrawing an application has no endpoint and no screen in the app; not built,
since nothing requested it.

### Recruiter — profile & organisations (§7)

| Feature | API | Method | Auth | Status | Action |
|---|---|---|---|---|---|
| Contact profile | `/recruiter/profile` | GET/PATCH | recruiter | ✅ | Verified |
| List organisations | `/recruiter/organisations` | GET | recruiter | ✅ | Verified — carries `job_count` |
| Create / edit / delete | `/recruiter/organisations[/{id}]` | POST/PATCH/DELETE | recruiter | ✅ | Verified — `verified` never client-settable |
| Verification document | `/recruiter/organisations/{id}/document` | POST | recruiter | ✅ | Verified — re-upload resets `verified` |
| Logo | `/recruiter/organisations/{id}/logo` | POST | recruiter | ✅ | Verified |

### Recruiter — job posting (§8)

| Feature | API | Method | Auth | Status | Action |
|---|---|---|---|---|---|
| Create job | `/recruiter/jobs` | POST | recruiter | ✅ | Verified — `organisation_id` ownership → 403 |
| My postings (all statuses) | `/recruiter/jobs/mine` | GET | recruiter | 🟡 | **Added `applicants_count`** |
| Edit posting | `/recruiter/jobs/{jobId}` | PATCH | recruiter | ✅ | Verified ⚠️ app has no edit screen yet ("coming soon") |
| Pause / resume / close | `/recruiter/jobs/{jobId}/status` | PATCH | recruiter | ✅ | Verified — `draft`/`expired` rejected 422 |
| Per-job stats | `/recruiter/jobs/{jobId}/stats` | GET | recruiter | ✅ | Verified — all four statuses zero-filled |

### Recruiter — applicant management (§9)

| Feature | API | Method | Auth | Status | Action |
|---|---|---|---|---|---|
| Applicant list | `/recruiter/jobs/{jobId}/applicants` | GET | recruiter | 🟡 | **Fixed N+1**; filters/sorts verified |
| Filter facets | `.../applicants/facets` | GET | recruiter | ✅ | Verified |
| Applicant details | `.../applicants/{applicationId}` | GET | recruiter | ✅ | Verified — frozen snapshot |
| Shortlist / select / reject | `.../applicants/{applicationId}/status` | PATCH | recruiter | ✅ | Verified — writes timeline + notifies candidate |
| Schedule / reschedule interview | `.../applicants/{applicationId}/interview` | POST | recruiter | ✅ | Verified — moves to `shortlisted` unless already `selected` |

Sorts verified against the client's `ApplicantSort`: `newest`, `oldest`,
`most_experience`, `highest_strength`, `best_match`.

### Notifications (§11)

| Feature | API | Method | Auth | Status | Action |
|---|---|---|---|---|---|
| Inbox (per audience) | `/notifications` | GET | any | 🟡 | Content fixed (Findings 1–2); ordering/audience scoping verified |
| Mark inbox read | `/notifications/read` | POST | any | ✅ | Verified — returns `marked_read` |

Events that write a notification, all verified as fired by real events (no
placeholder copy): application submitted (both sides), status changed,
interview scheduled, job posted, new message. Push delivery (FCM/APNs) is not
built — `Notifier::push()` is the single hook.

### Chat (§12)

| Feature | API | Method | Auth | Status | Action |
|---|---|---|---|---|---|
| **Conversation list** | `/conversations` | GET | any | 🔴 | **Created** ⚠️ app must switch from local `ChatService` |
| Thread | `/conversations/{applicationId}/messages` | GET | any | ✅ | Verified — read receipts on fetch |
| Send | `/conversations/{applicationId}/messages` | POST | any | ✅ | Verified — notifies recipient |
| Typing indicator | `/conversations/{applicationId}/typing` | GET/POST | any | ✅ | Verified — 8s TTL |

---

## 4. Security review

Re-verified against task Step 7; no changes were needed.

| Check | Result |
|---|---|
| Protected endpoints require auth | ✅ `auth:sanctum`; `guest.token` only on public browse |
| Role separation | ✅ `role:candidate` / `role:recruiter` middleware, 403 with readable message |
| Users can only touch their own data | ✅ every candidate read/write scopes through `$request->user()` |
| Recruiters only manage their own jobs/applicants | ✅ `findOwnedJob()` scopes through `user()->jobPostings()`; organisations likewise |
| ID manipulation | ✅ IDs are prefix-encoded (`j_`, `org_`) and resolved **inside** an ownership-scoped query, so a guessed id 404s |
| Chat access | ✅ participants only; non-participants get 404, not 403 — the API won't confirm a thread exists |
| Mass assignment | ✅ explicit `#[Fillable]` on every model; `verified`, `posting_status`, `status`, `profile_snapshot` are `forceFill`-only |
| SQL injection | ✅ query builder throughout; `LIKE` terms escape `%`/`_` |
| File uploads | ✅ mime + size validated; resumes/videos/documents on a private disk behind 15-minute signed URLs |
| Sensitive data exposure | ✅ `password`/`remember_token` hidden; `applicants_count` withheld from candidates |
| HTTP status codes | ✅ 200/201/401/403/404/422/429 used correctly; every error carries a toast-safe `message` |

One thing worth a decision rather than a fix: `POST /recruiter/jobs` does not
require the organisation to have a verification document on file. That is
enforced in the app's UI only. If it should be a server-side gate, it belongs in
`Recruiter\JobController::ownedOrganisation()` — see `API_NOTES.md`.

---

## 5. Flutter integration impact

Nothing here breaks an existing call. Both changed payloads are **additive**,
and the client's parsers ignore unknown keys.

| Change | Flutter action needed |
|---|---|
| Notification text now carries the real name | None — text field, renders as-is |
| `applicants_count` on `/recruiter/jobs/mine` | Add the field to `JobModel.fromApi` to drop the local `RecruiterService.applicantsFor(job).length` count |
| `GET /conversations` | Add a repository method; `ConversationsController` currently derives the list from local services and calls `ChatService.lastMessage()` per row |
| `interview` eager-load | None — same payload, fewer queries |

### Mock-data removal (follow-up pass)

The Flutter app shipped a `MockDataProvider` of static fixtures — six sample
jobs, a field dictionary, option lists, a seed profile — plus a
`RecruiterService` that **generated 6–26 fake applicants per job**, a
`ChatService` that seeded canned message histories and auto-replied on the
other party's behalf, and a search screen returning invented result counts
(`34 - i * 5`).

All of it is gone. `MockDataProvider` is deleted, the `useMockData` flag and
every branch behind it are removed, and nothing in the app fabricates a job, a
person, a message or a count any more:

| Was fabricated | Now |
|---|---|
| Option lists (categories, qualifications, skills, cities, shifts, job types, experience bands, certifications, languages, designations, institutes) | `GET /config/options` via a new `ConfigService`, loaded during splash |
| Smart Apply field dictionary | Question copy is app constants (`FieldCopy`); option values come from config |
| Six sample jobs | `GET /jobs` — `JobService` starts empty |
| 6–26 generated applicants per job | `GET /recruiter/jobs/{id}/applicants` into a per-job cache |
| Seeded chat threads + canned auto-replies | `GET/POST /conversations/{id}/messages`, with polling |
| Invented search counts and trending terms | `/jobs/search/suggestions` and `/jobs/search/trending` |
| Hardcoded city coordinates | `city_coordinates` from config |
| "Show N jobs" counted from a local list | `per_page=1` probe reading `meta.total` |
| Recent searches | Device-local storage (no endpoint exists by design) |

One deliberate exception, agreed with the product owner: **subscription plans
remain a local static catalogue.** There is no subscription backend at all —
no plans table, no payment gateway — so removing the catalogue would strand the
screen rather than make it real. `SubscriptionCatalog.plansFor` is the seam a
`GET /subscription/plans` would replace.

A side effect worth knowing about: those screens previously fell back to "the
first job in the list" when a lookup missed, which silently showed a recruiter
a **different posting than the one they tapped**. With no sample catalogue to
fall back on, that would now throw, so the lookups are nullable and the screens
show a proper not-found state (`MissingRecordScaffold`) instead.

Separately, two client-side observations found while auditing (backend is
correct; noting them so they aren't lost):

1. `ApplicationRecord.snapshotProfile` calls `CandidateProfile.fromJson()` on
   `profile_snapshot`, but the snapshot is the **API** shape (snake_case) while
   `fromJson` expects the local-storage shape (camelCase) — so almost every
   field decodes empty. `CandidateProfile.fromApi()` is the right parser there.
2. The recruiter side (`my_jobs`, `recruiter_home`, `applicants`,
   `conversations`) still reads local services rather than
   `RecruiterRepository`, which is already fully written against these
   endpoints.

---

## 6. Testing

`php artisan test` — **184 passed** (173 pre-existing, 11 added), 643
assertions. The additions in `tests/Feature/ApiAuditRegressionTest.php` cover:

- the real OTP registration path (rather than `UserFactory`), asserting
  `users.name` is null, then that the recruiter's notification names the
  candidate
- chat notifications naming the candidate
- `applicants_count` present for the owner, **absent** on the public job payload
- `GET /conversations`: candidate view, recruiter view, unread counting,
  unread cleared by opening the thread, traffic-first ordering, cross-recruiter
  isolation, 401 without a token, and an empty inbox returning `[]` with valid
  `meta` rather than an error

Beyond the suite, every endpoint touched here was also exercised over HTTP
against the MySQL database (the suite runs on SQLite) to confirm the new
ordering subquery and `withCount` behave identically on both engines.
