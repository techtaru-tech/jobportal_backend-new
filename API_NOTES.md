# Inthes API — implementation notes

Companion to `API_REQUIREMENTS.md`. That document is the spec; this one records
what was built, where the build deviates from the spec and why, and the open
questions worth resolving against the Flutter source before cutover.

For an endpoint-by-endpoint audit against what the Flutter app *actually calls*
— including the defects that audit found and what changed — see
**`API_AUDIT.md`**.

Every section number below refers to the **current** `API_REQUIREMENTS.md`
(the one with §7 Recruiter Profile & Organisations and the collapsed
`applied`/`shortlisted`/`selected`/`rejected` status set). An earlier revision
of the spec had a 7-stage pipeline, no organisations, and a `#`-prefixed
application id — this build was migrated from that revision to this one; see
"What changed from the previous spec revision" below if you're diffing against
an older deployment.

---

## Running it

```bash
composer install
php artisan migrate --seed      # demo data mirroring MockDataProvider
php artisan storage:link        # photo/logo public URLs are served from public/storage
php artisan serve
php artisan test                # 173 tests
```

Base URL: **`/api/v1`** — e.g. `http://localhost:8000/api/v1/jobs`.

Resumes, intro videos, and organisation verification documents are **not** on
the public disk — they're private files served through Laravel's signed
`storage.local` route (`config('filesystems.disks.local.serve')`), which is
why `storage:link` alone doesn't expose them. `PrivateFiles::url()` mints a
15-minute signed link on every read; nothing about them is guessable or
permanent, matching §9.1/§7.3's "short-lived signed URLs" requirement.

### Dev-only env flags

| Flag | Effect |
|---|---|
| `OTP_EXPOSE_CODE=true` | The OTP send response also returns `data.code`, so the login flow is testable without an SMS gateway. **Never enable in production.** |
| `OTP_DEBUG_CODE=123456` | Additionally accepts this fixed code on verify. |

Without either, the generated code is written to `storage/logs/laravel.log`
(`OtpService::dispatch()` is the single place to wire MSG91/Twilio).

### Seeded accounts

| Phone | Role | Notes |
|---|---|---|
| `9876543210` | candidate | Yash Saraswat — complete profile, home location set, 3 applications |
| `9812345678` | candidate | Riya Sharma — shortlisted with a scheduled interview + chat thread |
| `9799001122` | candidate | Aman Verma |
| `9700112233` | candidate | Neha Joshi — sparse profile, useful for Smart Apply gaps |
| `9000000001` | recruiter | Fortis Hospital (**verified** organisation) — 4 postings incl. one paused |
| `9000000002` | recruiter | Apollo Hospitals (**unverified** organisation) — 4 postings incl. one closed |

---

## Deviations from the spec, and why

### 1. Organisation document upload is not required to create an organisation

§7.3 says "the app will not let a recruiter save an organisation without one
[a document], because this is the artefact your verification check runs
against." That's a client-side UX description, and the API is split into two
calls (`POST /recruiter/organisations` then
`POST /recruiter/organisations/{id}/document`) — there's no way to attach a
file to the first call. **Built:** the document endpoint is available
immediately after creation but not enforced as a precondition of creating the
organisation record itself. If the intent is a hard server-side gate (e.g. a
recruiter can't post a job for an organisation with no document on file),
that check belongs in `Recruiter\JobController::ownedOrganisation()` — it
isn't there today.

### 2. `min_salary` filters on `salary_min`

§4.1's parameter table says this filters on `salary_min`, so it does: a job
qualifies only when its own floor clears the candidate's floor. The arguably
friendlier reading is "any job that *could* pay this much"
(`salary_max >= min_salary`), which would surface a ₹30K–₹50K job to someone
filtering at ₹50K. Changing it is a one-line edit in `JobController::index()`.
The spec's wording won as written.

### 3. Endpoints added beyond the spec

| Endpoint | Why |
|---|---|
| `GET /applications/requirements/{jobId}` | §6 describes Smart Apply asking only for missing fields, but lists no endpoint that reports which fields those are. Without it the client is the sole judge of readiness and §6.1's 422 becomes a dead end with no way to recover. Returns `missing_fields`, `can_apply`, `already_applied`. |
| `POST /auth/refresh` | §1.2 offers this as the backend's choice. Built, so short-lived tokens stay an option. |
| `GET\|POST /conversations/{id}/typing` | §12 names the typing indicator as something `ChatService` expects. Firestore gives it free; on REST it needs an endpoint. A flag older than 8s is treated as stale. |
| `PATCH /recruiter/jobs/{jobId}` | §8 has no edit-a-posting endpoint, only status changes. Added so a typo in a live posting is fixable. |
| `DELETE /candidate/saved-jobs/{jobId}` | §5 offers either a toggle or an explicit pair — **both** are built, so the app can use whichever it already calls. |
| `POST /recruiter/organisations/{id}/logo` | §7.2's `Organisation` model carries a `logo` bool + `logo_url`, but §7 lists no upload endpoint for it (only the verification `document`). Added by analogy with the candidate photo endpoint. |

### 4. Table naming

`jobs` is Laravel's queue table, so job postings live in **`job_postings`**
(model `JobPosting`). The API still calls them jobs everywhere — this is
internal only. Likewise `app_notifications`, leaving the conventional
`notifications` table free for Laravel's own notification system when FCM/APNs
lands.

### 5. Per-IP OTP throttling is deliberately loose

§2.1's rule — 3 sends per phone per 10 minutes — is implemented as specified
and is the real guard. The per-IP route throttle sits at 60/min rather than
something tight, because mobile users share carrier NAT addresses and a strict
IP cap would lock out legitimate traffic long before it stopped anyone.

---

## Discrepancies found in the spec — please confirm against the Flutter source

### A. `experience` is described as a closed enum but used as freeform

§1.7 says experience is "one of the fixed experience bands," but §4.1's
example job carries `"2–4 yrs"` and §8.1 posts the same value — neither of
which is in the §10.2 band list.

**Built:** stored as sent, freeform, with `experience_min_years` /
`experience_max_years` parsed out of whatever arrives so the values remain
filterable and sortable (§9.1's `most_experience` sort needs this). Both
`Fresher`/`10+ yrs` bands and freeform `2–4 yrs` work.

### B. `expected_salary` has two shapes

§3.1 has the candidate's `expected_salary` as `"35K"` (a single value); there
is no recruiter-facing rendering of it specified in this revision (the old
`ApplicantModel.expectedSalary` field was dropped along with the flattened
applicant shape — see §9.1's shape change). Since `profile` is now returned
whole, `expected_salary` reaches the recruiter exactly as the candidate typed
it. No divergence to resolve here anymore.

### C. Salary and dash characters

Display strings use the **en dash** (`–`, U+2013), not a hyphen: `₹25K – ₹40K`,
`1–3 yrs`, and the `organisation_size` enum values (`11–50`, etc). This matches
the spec's examples. `Display::EN_DASH` is the single definition;
string-matching clients should be aware.

### D. `mimes:quicktime` in the intro-video validation rule has no effect

Laravel's `mimes` rule matches file *extensions*, and `.quicktime` isn't a
real extension — `.mov` already covers QuickTime files. It's listed in
`config('options.uploads.intro_video.mimes')` for documentation clarity
(alongside the `video/quicktime` MIME type §3.13 implies MOV sends) but never
actually excludes anything beyond what `mp4,mov` already would.

---

## What changed from the previous spec revision

If you're comparing against an earlier deployment of this API, the spec itself
changed shape in several structural ways. All of the following were carried
through as migrations against existing data, not a fresh schema:

- **Application statuses collapsed** from a 7-stage pipeline
  (`submitted → received → underReview → shortlisted → interview → selected`,
  plus `rejected`) to 4: `applied`, `shortlisted`, `selected`, `rejected`.
  `submitted`/`received`/`underReview` all became `applied`; `interview`
  became `shortlisted` (an interview is now an *event* on the application, not
  a stage — see §9.4). The migration
  (`2026_08_13_000005_collapse_application_statuses_and_allow_reapplication`)
  remaps existing rows and their timeline entries by name, never by ordinal.
- **Application ids dropped the `#` prefix.** `§6.1`'s id is now the bare
  `MC-10245-<random>` string on both the candidate and recruiter side — no
  more rendering the same reference two ways.
- **Re-applying to the same job is now allowed** (§6.1) — the old unique
  `(job_posting_id, user_id)` constraint was dropped.
- **The applicant shape inverted** (§9.1): `ApplicantModel` used to be a
  flattened summary with individually-mapped fields; it's now one application
  wrapped around a whole frozen `CandidateProfile` under `profile`. The
  `snapshot_*` columns on `applications` exist purely as a queryable index
  over that frozen blob — they are never returned directly, only used to
  filter/sort/facet without deserializing the JSON snapshot for every row.
- **Recruiters gained an organisation model** (§7). A job posting now
  requires `organisation_id` instead of a freeform `organisation` string;
  `organisation`/`organisation_verified` are still denormalised onto the job
  for read performance, but ownership and verification live on `Organisation`.
- **Notifications gained `audience`** (§11): one account can be both a
  candidate and a recruiter, and every notification is now addressed to a
  specific inbox (`jobSeeker` or `recruiter`), never just a user.
- **Skills gained per-skill proficiency** (§3.6) and became their own
  full-replace endpoint (`PUT /candidate/profile/skills`), separate from the
  §3.2/§3.3 partial-update endpoint that still owns `qualification`,
  `experience`, and `location`.
- **Work experience gained free-text `description`**, replacing this build's
  earlier `bullets` array — folded together by the migration
  (`2026_08_13_000002_replace_bullets_with_description_on_work_experiences`).
- **The candidate gained a home location** (`home_city`/`home_pincode`/
  `home_latitude`/`home_longitude`, §3.1) distinct from `location` (where they
  want to *work*), and an optional intro video (§3.13) that is deliberately
  excluded from `profile_strength`.

---

## Things the spec calls out that are deliberately not built

- **Real SMS delivery** (§2.1) — out of scope per the spec.
  `OtpService::dispatch()` is the hook.
- **Push notifications** (§11) — the app has no FCM/APNs integration yet. Every
  push-worthy event already writes an in-app notification; `Notifier::push()`
  is the single method to fill in.
- **Firestore-backed chat** (§12) — the REST + polling alternative the spec
  offers was built instead, including delivery-status transitions and typing.
  Moving to Firestore later does not change any other endpoint.
- **Cross-device search history** (§4.5) — spec marks it out of scope for v1.
- **Intro video transcoding + poster frame** (§3.13) — the spec asks the
  server to "transcode to a web-playable MP4 (H.264/AAC)" and generate
  `intro_video_thumbnail_url`. Neither is built: this sandbox has no `ffmpeg`
  installed, and shelling out to a transcoder is real infrastructure (queue
  worker, storage for the transcode job, failure handling) that doesn't belong
  bolted onto a request/response cycle. What **is** built: the file is stored
  as uploaded and served back as-is, and `VideoProbe` re-validates the ≤60s
  cap server-side (via `ffprobe` if present on the host, falling back to a
  dependency-free MP4/MOV atom parser that reads the `moov/mvhd` duration
  directly) — so the one security-relevant part of §3.13 (never trust the
  client's duration check) is enforced even without a transcoder. Wiring real
  transcoding is a queued job away: dispatch one from
  `CandidateProfileController::uploadIntroVideo()`, have it write the
  H.264/AAC output and a poster frame back to the same paths, and fill
  `intro_video_thumbnail_path` when it completes.
- **`GET /config/options`** is built, but §10 notes the app may keep using
  local constants. Both work; the endpoint is there when wanted.

---

## Response contract quick reference

Success:
```json
{ "data": { }, "message": "optional" }
```
Lists with pagination (§1.5) — `meta` is a sibling of `data`, never nested:
```json
{ "data": [], "meta": { "page": 1, "per_page": 20, "total": 143, "total_pages": 8 } }
```
Errors (§1.4) — `message` is always present and always safe to show in a toast:
```json
{ "message": "Human-readable", "errors": { "field": ["..."] } }
```

A note on the error contract: an API request with no `Accept` header used to be
answered with an HTML redirect on a failed auth check, because Laravel defaults
unauthenticated guests to `route('login')`. `ForceJsonResponse` middleware plus
`redirectGuestsTo(null)` guarantee a JSON 401 instead — `AuthTest` covers the
no-`Accept`-header case specifically.

---

## Where things live

```
app/Enums/              closed enums from §1.8 (+ pipeline/transition rules)
app/Models/             Eloquent models; derived columns maintained in `saving` hooks
app/Http/Resources/     the JSON shapes — one per Flutter model, cross-referenced
app/Http/Controllers/Api/
    AuthController                  §2
    CandidateProfileController      §3
    EducationController             §3.4
    WorkExperienceController        §3.5
    JobController                   §4
    SavedJobController              §5
    ApplicationController           §6
    Recruiter/RecruiterProfileController  §7.1
    Recruiter/OrganisationController      §7.2, §7.3
    Recruiter/JobController         §8
    Recruiter/ApplicantController   §9
    NotificationController          §11
    ChatController                  §12
    ConfigController                §10
app/Services/           OtpService, ApplicationService (Smart Apply), Notifier
app/Support/            Display (§1.7 strings), ApiResponse (§1.3/§1.5),
                         PrivateFiles (signed URLs), FileRetention (snapshot-safe
                         deletes), ResumePdf, VideoProbe, PublicId
config/options.php       every §10 reference list, upload limits, OTP settings
routes/api.php           the full surface, grouped by spec section
```

`ResumePdf` is a dependency-free PDF writer for §3.11's "build resume from
profile" — a single-column CV needs a layout engine far less than it needs to
never break. Swap in dompdf if the design ever calls for real typography.

`FileRetention` exists because a frozen application snapshot (§9.1) points at
the exact file paths a candidate's resume/photo/intro-video lived at when they
applied. If a candidate later replaces their resume, the *old* file must
survive as long as any application snapshot still references it — otherwise
an employer's "download resume" link on an old application would 404.
`FileRetention::replacePrivate()`/`replacePublic()` check every application's
`snapshot_files` before deleting a superseded file.
