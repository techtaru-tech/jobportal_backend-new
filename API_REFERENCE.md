# Inthes API Reference (App Integration Guide)

Complete request/response reference for the Flutter app team. Every shape
below is pulled directly from the current backend code — controllers,
resources, and enums — not from the original spec doc. If this ever disagrees
with `API_NOTES.md` or `API_REQUIREMENTS.md`, **this document wins**: it
describes what is actually deployed today.

**Base URL:** `/api/v1` (e.g. `https://api.inthes.example.com/api/v1`)

---

## 1. Conventions

### 1.1 Auth

OTP-based login, no passwords. Every authenticated request sends:

```
Authorization: Bearer <token>
```

Tokens last 30 days. `POST /auth/refresh` **rotates** the token — it deletes
the old one and issues a new one; it does not extend the old token's life.

### 1.2 Response envelope

Every response is one of these four shapes:

**Success, single object:**
```json
{ "data": { }, "message": "optional human-readable string" }
```
`message` is only present when the endpoint sends one — do not assume it's
always there.

**Success, list (paginated):**
```json
{
  "data": [ ],
  "meta": { "page": 1, "per_page": 20, "total": 143, "total_pages": 8 }
}
```
`meta` is a **sibling** of `data`, never nested inside it.

**Success, list (not paginated):** a few endpoints (`GET /applications`,
`GET /candidate/saved-jobs`) return a plain array in `data` with no `meta` at
all — see each endpoint below for which kind it is.

**Message-only** (no `data` key at all):
```json
{ "message": "Signed out." }
```

**Error:**
```json
{
  "message": "Human-readable error the app can show directly in a toast",
  "errors": { "field_name": ["Specific validation message"] }
}
```
`message` is **always present** on every error and is always safe to show
verbatim in a toast. `errors` is only present on `422` field-validation
failures.

| Status | Meaning |
|---|---|
| 200 | OK |
| 201 | Created |
| 401 | Not signed in / token invalid or expired |
| 403 | Signed in, but not allowed to do this (wrong role, or not the resource owner) |
| 404 | Not found (or not yours — see note in §1.5) |
| 422 | Validation failed (`errors` present) or a business-rule check failed (e.g. Smart Apply gate, invalid status transition) |
| 429 | Rate-limited (OTP spam) |
| 500 | Server error — `message` is always a generic safe string, never a stack trace |

### 1.3 IDs

Every ID is a **prefixed string**, except application IDs (see below).
Accept and send them exactly as returned — do not strip the prefix.

| Prefix | Entity |
|---|---|
| `u_` | user |
| `j_` | job posting |
| `org_` | organisation |
| `edu_` | education entry |
| `exp_` | work experience entry |
| `n_` | notification |
| `m_` | chat message |

**Application IDs are different**: `id` (candidate side) and
`application_id` (recruiter side) are the same raw `reference` string, shaped
like `MC-10245-a1b2c3d4e5` (job code + random suffix) — **no prefix**, and
this string is also the chat conversation key (`/conversations/{applicationId}/...`).

### 1.4 Dates

Timestamps are ISO-8601 UTC ("Zulu"): `"2026-08-12T10:30:00Z"`. Plain dates
(`dob`, education `year`, interview `date`) are `"YYYY-MM-DD"`.

### 1.5 "404" can mean "not yours"

For anything owned by the caller (an application, a chat conversation, an
applicant on a job that isn't yours), a 404 is used for **both** "doesn't
exist" and "exists but isn't yours" — this is deliberate, so the API never
confirms the existence of something you shouldn't see.

### 1.6 Freeform vs. closed-enum fields

Only fields validated against a specific enum/config list are truly closed —
sending an unlisted value gets a `422`. Everything else accepts any string;
the `GET /config/options` lists for these are **suggestions only**, never
enforced:

**Closed (422 on an unlisted value):** `gender`, `preferred_job_types`,
`preferred_shifts`, `skill_levels.*`, `language_levels.*`, job `type`, job
`shift`, job `required_fields.*`, organisation `industry`, organisation
`size`, application `status`, job-posting `status` (restricted subset — see
§8.4), interview `type`, notification `audience`.

**Freeform (any string accepted):** `qualification`, `skills`,
`certifications`, `languages`, `preferred_roles`, work-experience
`designation`/`organization`/`department`, job `qualifications`/`skills`/
`duties`/`benefits`.

### 1.7 Re-applying is allowed

A candidate can submit more than one application to the same job — there is
no duplicate check. `already_applied` on the requirements endpoint is
informational only, never a blocker.

---

## 2. Authentication

### POST `/auth/otp/send`

No auth. Rate-limited per phone (429 after repeated attempts).

**Request**
```json
{ "phone": "9876543210", "role": "candidate" }
```
| Field | Rules |
|---|---|
| `phone` | required, string, 10–15 digits |
| `role` | required, `candidate` \| `recruiter` |

**Response 200**
```json
{ "data": { "verification_id": "vf_8f2a1c" }, "message": "Verification code sent." }
```
In non-production dev builds only, `data.code` may also be present (see
`OTP_EXPOSE_CODE` in the deploy notes) — never rely on this in the shipped app.

**Errors**
- `422` malformed phone → `{"message": "Enter a valid mobile number.", "errors": {"phone": ["Enter a valid mobile number."]}}`
- `429` → `{"message": "Too many attempts, try again in a few minutes."}`

### POST `/auth/otp/verify`

**Request**
```json
{
  "phone": "9876543210",
  "otp": "482913",
  "verification_id": "vf_8f2a1c",
  "role": "candidate"
}
```
All four fields required.

**Response 200**
```json
{
  "data": {
    "token": "1|GPYclnDCU0b4u59wGR8A1QzjXxerrYu7JAZYHPHAbd9",
    "user": {
      "id": "u_123",
      "phone": "9876543210",
      "role": "candidate",
      "is_new_user": false
    }
  }
}
```
No `message` key on this one. `is_new_user: true` means this phone has no
profile yet — show onboarding.

**Errors**
- `422` → `{"message": "Incorrect or expired code.", "errors": {"otp": ["Incorrect or expired code."]}}` — wrong code, expired code, or a code already used once.

### POST `/auth/refresh` — auth required

No body. **Rotates** the token.

**Response 200**
```json
{ "data": { "token": "2|newTokenStringHere..." } }
```
The old token stops working immediately.

### POST `/auth/logout` — auth required

No body.

**Response 200**
```json
{ "message": "Signed out." }
```

---

## 3. Candidate Profile

Everything in this section requires `Authorization: Bearer <token>` for a
**candidate**-role account. A recruiter token gets `403`:
```json
{ "message": "This action is only available to candidate accounts." }
```

### The `CandidateProfile` shape

This exact shape is returned by `GET /candidate/profile`, and is what's
frozen into every application's `profile_snapshot` / `profile` field later —
learn it once:

```json
{
  "name": "Yash Saraswat",
  "phone": "9876543210",
  "email": "yash@example.com",
  "gender": "Male",
  "dob": "1998-04-12",
  "address": "204, Green Park, Jaipur",

  "home_city": "Jaipur",
  "home_pincode": "302017",
  "home_latitude": 26.9124,
  "home_longitude": 75.7873,

  "qualification": "B.Sc Nursing",
  "experience": "3–5 yrs",
  "experience_min_years": 3,
  "experience_max_years": 5,

  "skills": ["ICU", "Patient Care", "Emergency Care"],
  "skill_levels": { "ICU": "Expert", "Patient Care": "Intermediate" },
  "specialization": [],

  "location": ["Jaipur"],
  "preferred_roles": ["Nurse"],
  "preferred_job_types": ["Full Time"],
  "preferred_shifts": ["Day", "Rotational"],
  "expected_salary": "35K",

  "certifications": ["BLS"],
  "certification_years": { "BLS": "2024" },
  "languages": ["Hindi", "English"],
  "language_levels": { "Hindi": "Native", "English": "Fluent" },

  "about": "Registered nurse with 4 years of ICU experience...",
  "photo": true,
  "photo_url": "https://cdn.example.com/storage/photos/123/me.jpg",

  "resume": "Yash_Saraswat_CV.pdf",
  "resume_url": "https://api.example.com/storage/resumes/123/abc.pdf?expires=...&signature=...",

  "intro_video_url": null,
  "intro_video_thumbnail_url": null,
  "intro_video_seconds": null,

  "educations": [
    { "id": "edu_1", "qualification": "B.Sc Nursing", "specialization": "Critical Care", "institute": "RUHS", "year": "2022" }
  ],
  "experiences": [
    {
      "id": "exp_1", "designation": "Staff Nurse", "organization": "Fortis Hospital",
      "department": "ICU", "city": "Jaipur", "start_date": "Mar 2023", "end_date": "Present",
      "currently_working": true, "description": "Managed ventilated patients across a 24-bed medical ICU.",
      "period": "Mar 2023 – Present"
    }
  ],

  "profile_strength": 95
}
```

**Notes:**
- `home_city`/`home_pincode`/`home_latitude`/`home_longitude` = where the
  candidate **lives**. `location` = cities they want to **work** in. Two
  different things, two different fields — don't conflate them.
- `experience_min_years`/`experience_max_years` are **read-only**, derived
  server-side from whatever string you put in `experience`.
- `resume_url` and `intro_video_url` are **signed, expiring URLs** (15
  minutes). Don't cache them — re-fetch the profile if a link goes stale.
- `intro_video_thumbnail_url` is currently always `null` — poster-frame
  generation isn't wired up yet. Don't build UI that assumes it's populated.
- `profile_strength` (0–100) is computed server-side. Weights: name 10,
  qualification 15, experience 15, skills 10, location 10, resume 15, photo 5,
  certifications 5, languages 5, about 10. The intro video is **not** in this
  score by design.

### GET `/candidate/profile`

**Response 200:** `{ "data": <CandidateProfile shape above> }`

### PATCH `/candidate/profile`

Partial update — send only the fields you're changing. Covers basic info,
home location, and the Smart Apply fields (`qualification`/`experience`/
`skills`/`location`/`specialization`). **Phone cannot be changed here.**

**Request** (any subset of):
```json
{
  "name": "Yash Saraswat",
  "email": "yash@example.com",
  "gender": "Male",
  "dob": "1998-04-12",
  "address": "204, Green Park, Jaipur",
  "home_city": "Jaipur",
  "home_pincode": "302017",
  "home_latitude": 26.9124,
  "home_longitude": 75.7873,
  "qualification": "B.Sc Nursing",
  "experience": "3–5 yrs",
  "skills": ["ICU", "Patient Care"],
  "location": ["Jaipur", "Jodhpur"],
  "specialization": ["CT Scan"]
}
```
| Field | Rules |
|---|---|
| `name` | string, ≤120 |
| `email` | valid email, ≤190 |
| `gender` | `Male` \| `Female` \| `Other` |
| `dob` | date, must be in the past |
| `address` | string, ≤255 |
| `home_city` | string, ≤80 |
| `home_pincode` | string, ≤10 |
| `home_latitude` | numeric, -90..90 |
| `home_longitude` | numeric, -180..180 |
| `qualification` | string, ≤120, freeform |
| `experience` | string, ≤40, freeform |
| `skills` | array of string ≤80 each, freeform |
| `location` | array of string ≤80 each |
| `specialization` | array of string ≤80 each |

List fields are automatically trimmed and de-duplicated server-side.

**Response 200:** `{ "data": <full CandidateProfile>, "message": "Profile updated." }`

**Error 422** (bad gender, e.g.):
```json
{ "message": "The gender field is invalid.", "errors": { "gender": ["The selected gender is invalid."] } }
```

### PATCH `/candidate/profile/preferences`

**Request**
```json
{
  "preferred_roles": ["Nurse", "ICU Nurse"],
  "preferred_job_types": ["Full Time"],
  "preferred_shifts": ["Day", "Rotational"],
  "expected_salary": "35K"
}
```
| Field | Rules |
|---|---|
| `preferred_roles` | array of string ≤80, freeform |
| `preferred_job_types` | array, each must be `Full Time`\|`Part Time`\|`Contract`\|`Internship` |
| `preferred_shifts` | array, each must be `Day`\|`Night`\|`Rotational`\|`Flexible` |
| `expected_salary` | string, ≤40, freeform (e.g. `"35K"`) |

**Response 200:** `{ "data": <full profile>, "message": "Preferences updated." }`

### PUT `/candidate/profile/skills` — full replace

The candidate's whole skills screen sends its complete state every time; this
is not an add/remove call.

**Request**
```json
{
  "skills": ["ICU", "Patient Care", "Wound Dressing"],
  "skill_levels": { "ICU": "Expert", "Patient Care": "Intermediate", "Wound Dressing": "Beginner" }
}
```
| Field | Rules |
|---|---|
| `skills` | required (may be `[]`), array of string ≤80. **Any string accepted** — the config seed list is a suggestion shortlist, not a whitelist. De-duplicated case-insensitively. |
| `skill_levels` | object, values must be `Beginner`\|`Intermediate`\|`Expert` or `null` |

`skill_levels` entries for skills not in the final `skills` list are dropped
silently.

**Response 200:** `{ "data": <full profile>, "message": "Skills updated." }`

### PUT `/candidate/profile/certifications` — full replace

**Request**
```json
{
  "certifications": ["BLS", "ACLS"],
  "certification_years": { "BLS": "2024", "ACLS": "2023" }
}
```
`certifications`: required array of string ≤40. `certification_years.*`:
nullable string ≤10. Years for certifications no longer in the list are
dropped.

**Response 200:** `{ "data": <full profile>, "message": "Certifications updated." }`

### PUT `/candidate/profile/languages` — full replace

**Request**
```json
{
  "languages": ["Hindi", "English"],
  "language_levels": { "Hindi": "Native", "English": "Fluent" }
}
```
`languages`: required array of string ≤40. `language_levels.*` must be
`Basic`\|`Intermediate`\|`Fluent`\|`Native` or `null`.

**Response 200:** `{ "data": <full profile>, "message": "Languages updated." }`

### PATCH `/candidate/profile/about`

**Request:** `{ "about": "Registered nurse with 4 years of ICU experience..." }`
`about`: required key (may be `null`), string ≤2000.

**Response 200:** `{ "data": <full profile>, "message": "About updated." }`

### POST `/candidate/profile/resume` — multipart

**Form field:** `file` — PDF/DOC/DOCX, max 5MB.

**Response 200**
```json
{
  "data": {
    "resume": "Yash_Saraswat_CV.pdf",
    "resume_url": "https://api.example.com/storage/resumes/123/xyz.pdf?expires=...&signature=..."
  },
  "message": "Resume uploaded."
}
```

**Error 422**
```json
{ "message": "Upload your resume as a PDF or Word document.", "errors": { "file": ["Upload your resume as a PDF or Word document."] } }
```
or `{"errors": {"file": ["Your resume must be smaller than 5 MB."]}}`.

### POST `/candidate/profile/resume/generate`

No body. Builds a resume PDF server-side from the candidate's current profile
(name, qualification, experience, skills, work history) — for candidates with
no file to upload.

**Response 200**
```json
{ "data": { "resume": "Yash_Saraswat_Resume.pdf", "resume_url": "https://..." }, "message": "Resume created from your profile." }
```

### POST `/candidate/profile/photo` — multipart

**Form field:** `file` — JPG/PNG, max 3MB. (Square-crop client-side before
upload.)

**Response 200:** `{ "data": { "photo_url": "https://..." }, "message": "Photo updated." }`

**Error 422:** wrong type or over 3MB, same pattern as resume.

### POST `/candidate/profile/intro-video` — multipart

**Form field:** `file` — MP4/MOV, max 50MB, **max 60 seconds**. The app
should enforce 60s on capture, but the server re-checks the real duration
regardless — some camera apps ignore the requested cap.

**Response 200:** `{ "data": { "intro_video_url": "https://..." }, "message": "Intro video uploaded." }`

**Error 422**
- Wrong type: `{"errors": {"file": ["Upload your intro video as an MP4 or MOV file."]}}`
- Over 50MB: `{"errors": {"file": ["Your intro video must be smaller than 50 MB."]}}`
- Over 60s (server-measured, even if the client thought it was shorter): `{"message": "Your intro video must be 60 seconds or shorter.", "errors": {"file": ["Your intro video must be 60 seconds or shorter."]}}`

### DELETE `/candidate/profile/intro-video`

No body.

**Response 200:** `{ "message": "Intro video removed." }`

---

## 4. Education & Work Experience (CRUD)

Same candidate-only auth as §3.

### POST `/candidate/profile/educations`

**Request**
```json
{ "qualification": "M.Sc Nursing", "specialization": "Critical Care", "institute": "RUHS", "year": "2026" }
```
`qualification` required ≤120; `specialization`/`institute` nullable ≤120/150;
`year` nullable ≤10.

**Response 201**
```json
{ "data": { "id": "edu_2", "qualification": "M.Sc Nursing", "specialization": "Critical Care", "institute": "RUHS", "year": "2026" }, "message": "Education added." }
```

**Side effect:** the profile's single `qualification` field is set to this
entry's `qualification` — the most-recently-touched education entry always
wins. Re-fetch the profile (or trust the value you just sent) if you display
`qualification` elsewhere on screen.

### PATCH `/candidate/profile/educations/{educationId}`

Same body/rules as POST. `educationId` is the `edu_...` id.

**Response 200:** `{ "data": <EducationEntry>, "message": "Education updated." }`

**Error 404** (not this candidate's entry): `{ "message": "That education entry no longer exists." }`

### DELETE `/candidate/profile/educations/{educationId}`

**Response 200:** `{ "message": "Education removed." }`

### POST `/candidate/profile/experiences`

**Request**
```json
{
  "designation": "Staff Nurse",
  "organization": "Fortis Hospital",
  "department": "ICU",
  "city": "Jaipur",
  "start_date": "Mar 2023",
  "end_date": "Jan 2024",
  "currently_working": true,
  "description": "Managed ventilated patients across a 24-bed medical ICU."
}
```
| Field | Rules |
|---|---|
| `designation` | required, ≤120, **freeform** — not an enum |
| `organization` | required, ≤150, freeform |
| `department` | nullable, ≤120, freeform |
| `city` | nullable, ≤80 |
| `start_date` / `end_date` | nullable, ≤30, free text (e.g. `"Mar 2023"`) |
| `currently_working` | boolean |
| `description` | nullable, ≤2000, free text |

**If `currently_working: true`, whatever you send for `end_date` is ignored
and overwritten with `"Present"` server-side.**

**Response 201**
```json
{
  "data": {
    "id": "exp_2", "designation": "Staff Nurse", "organization": "Fortis Hospital",
    "department": "ICU", "city": "Jaipur", "start_date": "Mar 2023", "end_date": "Present",
    "currently_working": true, "description": "Managed ventilated patients across a 24-bed medical ICU.",
    "period": "Mar 2023 – Present"
  },
  "message": "Experience added."
}
```
`period` is a derived display string — don't build it yourself client-side.

### PATCH `/candidate/profile/experiences/{experienceId}`

Same body/rules as POST.

### DELETE `/candidate/profile/experiences/{experienceId}`

**Response 200:** `{ "message": "Experience removed." }`

---

## 5. Jobs (public browse)

Readable **without a token**. If you send a bearer token for a signed-in
candidate, jobs additionally carry `is_saved`/`has_applied`.

### The `JobModel` shape

```json
{
  "id": "j_501",
  "code": "MC-10245",
  "role": "Nurse",
  "title": "Staff Nurse",
  "organisation_id": "org_1",
  "organisation": "Fortis Hospital",
  "organisation_verified": true,
  "organisation_note": "Multi-speciality hospital, 450 beds",
  "city": "Jaipur",
  "pincode": "302017",
  "latitude": 26.9124,
  "longitude": 75.7873,

  "salary_min": 25000,
  "salary_max": 40000,
  "salary_display": "₹25K – ₹40K",
  "salary": "₹25K – ₹40K",

  "experience": "2–4 yrs",
  "experience_display": "2–4 yrs",
  "experience_min_years": 2,
  "experience_max_years": 4,

  "type": "Full Time",
  "shift": "Rotational",

  "posted_at": "2026-08-05T09:00:00Z",
  "posting_status": "active",

  "required_fields": ["qualification", "experience", "location", "resume"],

  "about": "We're looking for a compassionate, detail-oriented...",
  "duties": ["Monitor patient vitals every 2 hours"],
  "qualifications": ["B.Sc Nursing", "GNM"],
  "skills": ["ICU", "Patient Care", "Emergency Care"],
  "benefits": ["PF", "Health insurance", "Night shift allowance"],

  "is_saved": false,
  "has_applied": false
}
```
- `salary` and `salary_display` are identical — the duplicate key exists for
  compatibility. Use either.
- Same for `experience` and `experience_display`.
- `organisation_verified` reflects the employer's **current** verification
  state — show a "Verified employer" badge only when `true`.
- `is_saved`/`has_applied` are **absent from the payload entirely** (not
  `null`) for a guest request or a recruiter token — check for key presence,
  don't assume `false` when missing.
- `required_fields` values are drawn from: `name`, `qualification`,
  `experience`, `skills`, `location`, `specialization`, `certificationBls`,
  `resume`.

### GET `/jobs`

Query params (all optional, combine with AND):

| Param | Notes |
|---|---|
| `category` | exact match against `role` |
| `query` (or `q`) | free-text over title/organisation/role/skills |
| `city` | exact match |
| `experience` | repeatable or comma-separated |
| `job_type` | repeatable or comma-separated, values from `job_types` |
| `shift` | repeatable or comma-separated, values from `shifts` |
| `min_salary` | int — filters on the job's `salary_min` (i.e. its floor must clear this number, not its ceiling) |
| `page`, `per_page` | pagination, `per_page` capped at 100, default 20 |

**Response 200:** paginated envelope of `JobModel` (see §1.2).

### GET `/jobs/{jobId}`

**Response 200:** `{ "data": <JobModel> }`

**Error 404:** `{ "message": "That job is no longer available." }` — for a
missing id **or** a job that's paused/closed/draft/expired (only `active`
jobs are visible here).

### GET `/jobs/categories`

**Response 200**
```json
{ "data": [ { "name": "Nurse", "job_count": 128 }, { "name": "Doctor", "job_count": 54 }, { "name": "Dietitian", "job_count": 0 } ] }
```
Every seeded category always appears, even at `0`, so chips stay stable.

### GET `/jobs/search/suggestions?q=nurs`

**Response 200**
```json
{ "data": [ { "term": "Staff Nurse", "job_count": 34 }, { "term": "ICU Nurse", "job_count": 21 } ] }
```
Empty `q` → `{"data": []}`. Up to 10 results, matching live job titles plus a
curated synonym list.

### GET `/jobs/search/trending`

**Response 200**
```json
{ "data": [ { "term": "Staff Nurse", "job_count": 120 } ] }
```
Top 8 posted titles right now. (Recent/per-device search history has no
endpoint — keep that local to the device.)

---

## 6. Saved Jobs

Candidate auth required.

### GET `/candidate/saved-jobs`

**Response 200:** `{ "data": [ <JobModel>, ... ] }` — **not paginated**, plain
array. Every item has `is_saved: true` forced, plus a live `has_applied`.

### POST `/candidate/saved-jobs` — toggle

**Request:** `{ "job_id": "j_501" }`

**Response** if it was unsaved → now saved:
```json
{ "data": { "job_id": "j_501", "is_saved": true }, "message": "Saved." }
```
(status `201`). If it was saved → now unsaved:
```json
{ "data": { "job_id": "j_501", "is_saved": false }, "message": "Removed from saved jobs." }
```
(status `200`).

**Error 404:** `{ "message": "That job is no longer available." }`

### DELETE `/candidate/saved-jobs/{jobId}`

Explicit unsave, idempotent (no error if it wasn't saved).

**Response 200:** `{ "data": { "job_id": "j_501", "is_saved": false }, "message": "Removed from saved jobs." }`

---

## 7. Applications — Smart Apply (candidate side)

Candidate auth required. **A candidate may apply to the same job more than
once** — there's no duplicate check.

### GET `/applications/requirements/{jobId}`

Check before showing the apply button / walking through gap-filling screens.

**Response 200**
```json
{
  "data": {
    "job_id": "j_501",
    "required_fields": ["qualification", "experience", "location", "resume"],
    "missing_fields": ["resume"],
    "can_apply": false,
    "already_applied": false
  }
}
```
Walk the candidate through filling `missing_fields` one at a time, then call
`POST /applications`. **The server re-checks this on submit — don't skip it,
but also don't trust the client-side check alone.**

### POST `/applications`

**Request:** `{ "job_id": "j_501" }`

(You may also send `profile_snapshot`, but it's ignored — the server always
builds its own snapshot from the live profile at submit time.)

**Response 201**
```json
{
  "data": {
    "id": "MC-10245-a1b2c3d4e5",
    "job_id": "j_501",
    "job": <JobModel>,
    "status": "applied",
    "applied_at": "2026-08-12T10:15:00Z",
    "stage_updated_at": "2026-08-12T10:15:00Z",
    "progress_percent": 33,
    "interview": null
  },
  "message": "Application submitted."
}
```

**Error 422** if the profile still has gaps the client didn't fill (server
double-checks §7.1 requirements):
```json
{ "message": "Complete your profile before applying: resume", "errors": { "profile": ["Complete your profile before applying: resume"] } }
```
**Error 404:** `{ "message": "That job is no longer available." }`

### GET `/applications`

Query: `?status=applied` — one value, or comma-separated /repeated for
several (e.g. `?status=applied,shortlisted`). Values: `applied` \|
`shortlisted` \| `selected` \| `rejected`.

**Response 200:** `{ "data": [ <application, list shape>, ... ] }` — **not
paginated**, sorted newest-applied first. List shape = the same object as the
`POST /applications` response above (no `profile_snapshot`/`timeline`).

### GET `/applications/{applicationId}`

`applicationId` = the `reference` string (e.g. `MC-10245-a1b2c3d4e5`), used
exactly as returned — no `#`, no re-encoding.

**Response 200** — detail shape, adds `profile_snapshot` and `timeline`:
```json
{
  "data": {
    "id": "MC-10245-a1b2c3d4e5",
    "job_id": "j_501",
    "job": <JobModel>,
    "status": "shortlisted",
    "applied_at": "2026-08-05T09:00:00Z",
    "stage_updated_at": "2026-08-06T14:00:00Z",
    "progress_percent": 67,
    "interview": {
      "date": "2026-08-20",
      "time": "11:00 AM",
      "type": "online",
      "location_or_link": "https://meet.google.com/abc-defg-hij",
      "notes": "Bring original certificates if in-person."
    },
    "profile_snapshot": { "...full CandidateProfile shape, frozen at apply time..." },
    "timeline": [
      { "stage": "applied", "at": "2026-08-05T09:00:00Z" },
      { "stage": "shortlisted", "at": "2026-08-06T14:00:00Z" }
    ]
  }
}
```
- **`progress_percent`**: `applied` = 33, `shortlisted` = 67, `selected` =
  100, `rejected` = furthest pipeline stage actually reached (e.g. rejected
  after being shortlisted = 67, not 0).
- **`interview`** is `null` until one is scheduled, then always present
  inline — render it as its own card, not a progress dot. There is **no**
  `"interview"` status; scheduling one sets `status: "shortlisted"` (unless
  the candidate is already `"selected"`, which is left alone).
- **`profile_snapshot`** is frozen exactly as it was at apply time. Editing
  the profile afterward **never** changes what's shown here — this is
  intentional, not a bug, if the app displays stale-looking data on an old
  application.
- `timeline` only lists stages actually reached; render remaining pipeline
  steps (`applied → shortlisted → selected`) as pending.

**Error 404:** `{ "message": "That application no longer exists." }` — for a
missing id or one that belongs to a different candidate.

---

## 8. Recruiter: Profile, Organisations, Jobs

Recruiter auth required (a candidate token gets 403). **A recruiter account
is not tied to one company** — the contact profile (§8.1) is one per account;
organisations (§8.2) are a list of employers that account posts jobs for.

### 8.1 Recruiter contact profile

**GET `/recruiter/profile`**
```json
{ "data": { "contact_person_name": "Priya Menon", "contact_email": "hr@fortishealthcare.com", "contact_phone": "9000000001" } }
```

**PATCH `/recruiter/profile`** — partial update, all fields optional:
```json
{ "contact_person_name": "Priya Menon", "contact_email": "hr@fortishealthcare.com", "contact_phone": "9000000001" }
```
`contact_person_name` ≤120, `contact_email` valid email ≤190, `contact_phone`
≤20.

**Response 200:** `{ "data": <RecruiterProfile>, "message": "Profile updated." }`

### 8.2 Organisations

**The `Organisation` shape:**
```json
{
  "id": "org_1",
  "name": "Sunrise Multispecialty",
  "industry": "Hospital",
  "size": "201–500",
  "address": "Tonk Road, Jaipur",
  "website": "https://sunrisehealth.com",
  "gst_number": "08AABCU9603R1ZM",
  "about": "A 300-bed multi-speciality hospital...",
  "logo": true,
  "logo_url": "https://cdn.example.com/storage/organisations/1/logo/x.png",
  "document_name": "GST_Certificate.pdf",
  "document_url": "https://api.example.com/storage/organisations/1/y.pdf?expires=...&signature=...",
  "verified": false,
  "job_count": 3
}
```
- **`verified` is entirely server-owned.** Any value you send for it is
  silently ignored — a new organisation always starts `false`. Only an
  admin/automated check flips it. Uploading a new verification document
  **resets it to `false`** and re-queues the check.
- `job_count` is present on the list endpoint (`GET /recruiter/organisations`)
  but **absent** from the create/update/delete responses.
- `size` uses an **en dash** (`–`, not a hyphen): `1–10`, `11–50`, `51–200`,
  `201–500`, `500+`.

**GET `/recruiter/organisations`**
```json
{ "data": [ <Organisation>, ... ] }
```
Not paginated.

**POST `/recruiter/organisations`**
```json
{
  "name": "Sunrise Multispecialty",
  "industry": "Hospital",
  "size": "201–500",
  "address": "Tonk Road, Jaipur",
  "website": "https://sunrisehealth.com",
  "gst_number": "08AABCU9603R1ZM",
  "about": "A 300-bed multi-speciality hospital..."
}
```
| Field | Rules |
|---|---|
| `name` | required, ≤150 |
| `industry` | nullable, one of: `Hospital`, `Clinic`, `Diagnostic Lab`, `Pharmacy`, `Nursing Home`, `Home Healthcare`, `Medical College`, `Staffing Agency`, `Other` |
| `size` | nullable, one of: `1–10`, `11–50`, `51–200`, `201–500`, `500+` |
| `address` | nullable, ≤255 |
| `website` | nullable, valid URL, ≤255 |
| `gst_number` | nullable, ≤30 |
| `about` | nullable, ≤2000 |

**Response 201:** `{ "data": <Organisation>, "message": "Organisation added." }`

**PATCH `/recruiter/organisations/{organisationId}`** — same fields, all
optional. **Error 404** if not owned by the caller: `{ "message": "That organisation was not found." }`

**DELETE `/recruiter/organisations/{organisationId}`**
`{ "message": "Organisation removed." }` — also deletes its logo/document files.

**POST `/recruiter/organisations/{organisationId}/document`** — multipart,
field `file`: PDF/JPG/PNG, max 10MB. This is the GST certificate / company
registration doc the verification check runs against.
```json
{ "data": { "document_name": "GST_Certificate.pdf", "document_url": "https://..." }, "message": "Document uploaded." }
```
Re-uploading resets `verified` to `false`.

**POST `/recruiter/organisations/{organisationId}/logo`** — multipart, field
`file`: JPG/PNG, max 3MB.
```json
{ "data": { "logo_url": "https://..." }, "message": "Logo updated." }
```

### 8.3 Posting a job

**POST `/recruiter/jobs`**
```json
{
  "role": "Nurse",
  "title": "Staff Nurse",
  "organisation_id": "org_1",
  "organisation_note": "Multi-speciality hospital, 450 beds",
  "city": "Jaipur",
  "pincode": "302017",
  "latitude": 26.9124,
  "longitude": 75.7873,
  "salary_min": 25000,
  "salary_max": 40000,
  "experience": "2–4 yrs",
  "type": "Full Time",
  "shift": "Rotational",
  "qualifications": ["B.Sc Nursing", "GNM"],
  "skills": ["ICU", "Patient Care", "Emergency Care"],
  "duties": ["Monitor patient vitals every 2 hours"],
  "benefits": ["PF", "Health insurance"],
  "about": "We're looking for a compassionate...",
  "required_fields": ["qualification", "experience", "location", "resume"]
}
```
| Field | Rules |
|---|---|
| `role`, `title` | required, ≤80/≤120 |
| `organisation_id` | **required**, must be one of this recruiter's own organisations |
| `organisation_note` | nullable, ≤255 |
| `city` | required, ≤80 |
| `pincode` | nullable, ≤10 |
| `latitude`/`longitude` | nullable, numeric, valid coordinate ranges — only present if the recruiter used a map picker |
| `salary_min`/`salary_max` | nullable ints, 0–100,000,000; `salary_max` must be ≥ `salary_min` |
| `experience` | nullable, freeform |
| `type` | required, one of `Full Time`/`Part Time`/`Contract`/`Internship` |
| `shift` | required, one of `Day`/`Night`/`Rotational`/`Flexible` |
| `qualifications`, `skills`, `duties`, `benefits` | arrays of freeform strings |
| `about` | nullable, ≤4000 |
| `required_fields` | array, each must be one of the 8 `ProfileField` values |

**Response 201:** `{ "data": <JobModel>, "message": "Job posted." }` — the
job's `code` (`MC-XXXXX`) and `posted_at` are server-generated;
`posting_status` starts `active`.

**Error 403** if `organisation_id` isn't yours:
```json
{ "message": "That organisation does not belong to your account." }
```
**Error 422** for a bad salary range:
```json
{ "message": "The maximum salary must be at least the minimum salary.", "errors": { "salary_max": ["The maximum salary must be at least the minimum salary."] } }
```

### GET `/recruiter/jobs/mine`

Every job this recruiter has posted, **regardless of status** (unlike the
public `/jobs` list, which only shows `active`).

Query: `?status=active` — comma-separated/repeated to filter by
`JobPostingStatus` (`active`/`paused`/`draft`/`closed`/`expired`).

**Response 200:** paginated envelope of `JobModel`.

### PATCH `/recruiter/jobs/{jobId}`

Edit a posting — same fields as create, all optional. If you change
`organisation_id`, it's re-validated for ownership.

**Response 200:** `{ "data": <JobModel>, "message": "Job updated." }`

**Error 404** if not yours: `{ "message": "That job posting was not found." }`

### 8.4 PATCH `/recruiter/jobs/{jobId}/status`

**Request:** `{ "status": "paused" }`

Only `active`, `paused`, `closed` are settable here — `draft`/`expired` are
system-managed and rejected with `422` if you try to send them.

**Allowed transitions:** `active ↔ paused`, `active|paused → closed`. A
`closed` job (or `draft`/`expired`) cannot transition anywhere via this
endpoint.

**Response 200:** `{ "data": <JobModel>, "message": "Job status updated." }`

**Error 422** for an invalid transition:
```json
{ "message": "A closed job cannot be moved to active.", "errors": { "status": ["Invalid transition from closed to active."] } }
```

### GET `/recruiter/jobs/{jobId}/stats`

```json
{ "data": { "total_applicants": 18, "by_status": { "applied": 11, "shortlisted": 5, "selected": 1, "rejected": 1 } } }
```
`by_status` always has all four keys, zero-filled.

---

## 9. Applicant Management (recruiter side)

**The shape here is different from a candidate's own profile view**: an
applicant is *one application wrapped around a whole frozen `CandidateProfile`*
under `profile` — not a flattened list of fields. Derive `name`,
`designation`, etc. from `profile` client-side; they are not duplicated at
the top level.

### The `ApplicantModel` shape

```json
{
  "application_id": "MC-10245-a1b2c3d4e5",
  "job_id": "j_501",
  "status": "shortlisted",
  "applied_at": "2026-08-06T11:00:00Z",
  "stage_updated_at": "2026-08-07T09:00:00Z",
  "interview": null,
  "profile": {
    "name": "Riya Sharma",
    "phone": "9812345678",
    "email": "riya.sharma@example.com",
    "...every other CandidateProfile field...": "...",
    "educations": [ ],
    "experiences": [ ]
  }
}
```
- `application_id` here has **no `#` prefix** — same raw reference string as
  the candidate side.
- `profile.phone`/`profile.email` are included because this recruiter owns
  the job — show them only on the full applicant-profile screen, not on list
  cards (that's a UI choice, not something the API hides).
- `profile` is the snapshot **frozen at submission time** — if the candidate
  edited their profile since applying, this will differ from their current
  live profile. That's intentional (§7's "one row, two views" — the employer
  sees what was actually submitted).
- `profile.resume_url`/`intro_video_url`/`photo_url` inside a snapshot are
  re-signed on every read (the signature always reflects "now", but the
  underlying **file** is the one that existed when the candidate applied,
  even if they've since replaced it).

### GET `/recruiter/jobs/{jobId}/applicants`

Query params:

| Param | Notes |
|---|---|
| `query` | free text over the submitted name/qualification/designation/location/skills |
| `status` | one of `applied`/`shortlisted`/`selected`/`rejected` |
| `experience`, `qualification` | repeatable/comma-separated, exact match against what was submitted |
| `location`, `skills` | repeatable/comma-separated, matches within the submitted list |
| `sort` | `newest` (default) \| `oldest` \| `most_experience` \| `highest_strength` \| `best_match` |
| `page`, `per_page` | pagination |

`best_match` ranks by how many of the applicant's submitted skills overlap
this job's `skills` list, descending.

**Response 200:** paginated envelope of `ApplicantModel`.

### GET `/recruiter/jobs/{jobId}/applicants/facets`

```json
{
  "data": {
    "experience": ["Fresher", "1–3 yrs", "3–5 yrs"],
    "qualification": ["B.Sc Nursing", "GNM"],
    "location": ["Jaipur", "Jodhpur"],
    "skills": ["ICU", "Patient Care", "Emergency Care", "OPD"]
  }
}
```
Only values actually present among this job's applicants — use these to
populate filter chips so nothing ever matches zero results.

### GET `/recruiter/jobs/{jobId}/applicants/{applicationId}`

Single `ApplicantModel`. **Error 404:** `{ "message": "That applicant was not found for this job." }`

### PATCH `/recruiter/jobs/{jobId}/applicants/{applicationId}/status`

**Request:** `{ "status": "shortlisted" }` — any of `applied`/`shortlisted`/
`selected`/`rejected` is a legal target from any current status (including
reopening a `rejected` application back to `shortlisted`).

**Response 200:** `{ "data": <ApplicantModel>, "message": "Applicant status updated." }`

This also writes a timeline entry the candidate's `GET
/applications/{id}` picks up, and fires a notification to the candidate.

### POST `/recruiter/jobs/{jobId}/applicants/{applicationId}/interview`

**Request**
```json
{
  "date": "2026-08-20",
  "time": "11:00 AM",
  "type": "online",
  "location_or_link": "https://meet.google.com/abc-defg-hij",
  "notes": "Bring original certificates if in-person."
}
```
| Field | Rules |
|---|---|
| `date` | required, valid date |
| `time` | required, ≤20, free text (e.g. `"11:00 AM"`) |
| `type` | required, `online` \| `inPerson` |
| `location_or_link` | required, ≤255 — a meeting URL for `online`, a physical address for `inPerson` |
| `notes` | nullable, ≤1000 |

**Re-posting this replaces the existing interview** (reschedule) — there's no
separate edit call.

**Side effect:** sets the application to `shortlisted`, **unless it's
already `selected`**, in which case the status is left alone. There is no
dedicated `"interview"` status.

**Response 200:** `{ "data": <ApplicantModel with interview populated>, "message": "Interview scheduled." }`

**Error 422** for a bad `type`: standard validation shape.

---

## 10. Notifications

Auth required (either role). **`audience` is required on every call** — one
account can be both a candidate and a recruiter, and each mode only ever sees
its own inbox.

### GET `/notifications?audience=jobSeeker`

`audience`: required, `jobSeeker` \| `recruiter`.

**Response 200** — plain array, newest first, capped at 100:
```json
{
  "data": [
    {
      "id": "n_1",
      "audience": "jobSeeker",
      "text": "Fortis Hospital moved your Staff Nurse application to Shortlisted.",
      "at": "2026-08-12T09:30:00Z",
      "read": false,
      "type": "application_update",
      "application_id": "MC-10245-a1b2c3d4e5"
    }
  ]
}
```
**Important:** keys with a `null` value are **stripped entirely** from each
notification object. `application_id`/`job_id`/`conversation_id` may be
**absent** (not `null`) on a notification that doesn't relate to one — check
key presence, don't assume the key exists.

`type` is one of: `application_update`, `new_message`, `job_match`, `system`.

**Error 422** if `audience` is missing: `{ "message": "The audience field is required.", "errors": { "audience": [...] } }`

### POST `/notifications/read`

**Request:** `{ "audience": "jobSeeker" }` — marks every unread notification
in that inbox as read. Call this when the inbox screen opens; there is no
per-notification mark-read call.

**Response 200:** `{ "data": { "marked_read": 3 }, "message": "Notifications marked as read." }`

---

## 11. Chat

Auth required. Either party to an application (the candidate, or the
recruiter who owns the job) may read/write; anyone else gets a **404**
(never 403 — the API doesn't confirm the conversation exists to an outsider).

The conversation key is the application's `reference` string — the same id
used everywhere else for that application.

### GET `/conversations/{applicationId}/messages`

**Response 200**
```json
{
  "data": [
    { "id": "m_1", "sender": "recruiter", "text": "Hi! Thanks for applying...", "sent_at": "2026-08-11T14:00:00Z", "status": "read" }
  ]
}
```
`sender`: `recruiter` \| `candidate`. `status`: `sending` \| `sent` \|
`delivered` \| `read`.

**Side effect:** opening the thread automatically marks every message from
the *other* party as `read` — there's no separate mark-as-read call needed.
Response also carries a header `X-Typing: 1` or `0` reflecting whether the
other party is currently typing (in addition to the `typing` endpoint below).

**Error 404:** `{ "message": "That conversation was not found." }`

### POST `/conversations/{applicationId}/messages`

**Request:** `{ "text": "Hi! Thanks for applying..." }` — required, ≤2000,
non-blank.

**Response 201** (no `message` key on this one):
```json
{ "data": { "id": "m_2", "sender": "candidate", "text": "Thank you!", "sent_at": "2026-08-11T14:05:00Z", "status": "sent" } }
```
Sending a message also clears your own typing flag, and notifies the other
party.

### GET / POST `/conversations/{applicationId}/typing`

**POST request:** `{ "typing": true }` — sets your side's typing flag (auto
expires after ~8 seconds either way, so re-send while the user is actively
typing).

**Response (both GET and POST):**
```json
{ "data": { "recruiter": false, "candidate": true } }
```

---

## 12. Reference Data

### GET `/config/options`

No auth. One call gets every static list the app needs — call it once at
startup and cache it.

```json
{
  "data": {
    "categories": ["Nurse", "Doctor", "Pharmacist", "Lab Technician", "Radiology Technician", "Physiotherapist", "Dietitian", "Medical Officer"],
    "experience_bands": ["Fresher", "0–1 yr", "1–3 yrs", "3–5 yrs", "5–10 yrs", "10+ yrs"],
    "qualifications": ["B.Sc Nursing", "GNM", "ANM", "MBBS", "BDS", "BAMS", "BHMS", "B.Pharm", "D.Pharm", "M.Pharm", "BPT", "MPT", "B.Sc MLT", "DMLT", "M.Sc Nursing"],
    "skills": ["ICU", "Patient Care", "Emergency Care", "OPD", "OT", "Ventilator Care", "Wound Dressing", "Vaccination", "Phlebotomy", "X-Ray", "CT Scan", "MRI", "Physiotherapy", "Counselling"],
    "job_types": ["Full Time", "Part Time", "Contract", "Internship"],
    "shifts": ["Day", "Night", "Rotational", "Flexible"],
    "cities": ["Jaipur", "Jodhpur", "Udaipur", "Kota", "Ajmer", "Bikaner", "Alwar", "Bharatpur"],
    "certifications": ["BLS", "ACLS", "PALS", "NRP", "CPR"],
    "languages": ["Hindi", "English", "Rajasthani", "Punjabi", "Gujarati", "Marwari"],
    "language_levels": ["Basic", "Intermediate", "Fluent", "Native"],
    "skill_levels": ["Beginner", "Intermediate", "Expert"],
    "organisation_industries": ["Hospital", "Clinic", "Diagnostic Lab", "Pharmacy", "Nursing Home", "Home Healthcare", "Medical College", "Staffing Agency", "Other"],
    "organisation_sizes": ["1–10", "11–50", "51–200", "201–500", "500+"],
    "salary_steps": ["10K", "15K", "20K", "25K", "30K", "35K", "40K", "50K", "60K", "75K", "1L"],
    "enums": {
      "application_status": ["applied", "shortlisted", "selected", "rejected"],
      "application_status_pipeline": ["applied", "shortlisted", "selected"],
      "job_posting_status": ["active", "paused", "draft", "closed", "expired"],
      "profile_field": ["name", "qualification", "experience", "skills", "location", "specialization", "certificationBls", "resume"],
      "interview_type": ["online", "inPerson"],
      "chat_sender": ["recruiter", "candidate"],
      "chat_message_status": ["sending", "sent", "delivered", "read"],
      "skill_level": ["Beginner", "Intermediate", "Expert"],
      "language_level": ["Basic", "Intermediate", "Fluent", "Native"],
      "organisation_industry": ["Hospital", "Clinic", "Diagnostic Lab", "Pharmacy", "Nursing Home", "Home Healthcare", "Medical College", "Staffing Agency", "Other"],
      "organisation_size": ["1–10", "11–50", "51–200", "201–500", "500+"],
      "notification_audience": ["jobSeeker", "recruiter"]
    },
    "uploads": {
      "resume": { "max_kb": 5120, "mimes": ["pdf", "doc", "docx"] },
      "photo": { "max_kb": 3072, "mimes": ["jpg", "jpeg", "png"] },
      "intro_video": { "max_kb": 51200, "max_seconds": 60, "mimes": ["mp4", "mov", "quicktime"] },
      "organisation_logo": { "max_kb": 3072, "mimes": ["jpg", "jpeg", "png"] },
      "organisation_document": { "max_kb": 10240, "mimes": ["pdf", "jpg", "jpeg", "png"] }
    }
  }
}
```
`salary_steps`, `cities`, `qualifications`, `skills`, `certifications` are
**seed suggestions only** — never validated server-side as a closed set (see
§1.6). Cities in particular are Rajasthan-first for launch but the field
accepts any city name.

---

## 13. Quick lookup — every endpoint at a glance

| Method | Path | Auth | Notes |
|---|---|---|---|
| POST | `/auth/otp/send` | none | rate-limited |
| POST | `/auth/otp/verify` | none | |
| POST | `/auth/refresh` | any | rotates token |
| POST | `/auth/logout` | any | |
| GET | `/config/options` | none | |
| GET | `/jobs` | optional | guest OK, richer if candidate |
| GET | `/jobs/categories` | none | |
| GET | `/jobs/search/suggestions` | none | |
| GET | `/jobs/search/trending` | none | |
| GET | `/jobs/{jobId}` | optional | |
| GET | `/candidate/profile` | candidate | |
| PATCH | `/candidate/profile` | candidate | |
| PATCH | `/candidate/profile/preferences` | candidate | |
| PUT | `/candidate/profile/skills` | candidate | full replace |
| PUT | `/candidate/profile/certifications` | candidate | full replace |
| PUT | `/candidate/profile/languages` | candidate | full replace |
| PATCH | `/candidate/profile/about` | candidate | |
| POST | `/candidate/profile/resume` | candidate | multipart |
| POST | `/candidate/profile/resume/generate` | candidate | |
| POST | `/candidate/profile/photo` | candidate | multipart |
| POST | `/candidate/profile/intro-video` | candidate | multipart |
| DELETE | `/candidate/profile/intro-video` | candidate | |
| POST | `/candidate/profile/educations` | candidate | |
| PATCH | `/candidate/profile/educations/{id}` | candidate | |
| DELETE | `/candidate/profile/educations/{id}` | candidate | |
| POST | `/candidate/profile/experiences` | candidate | |
| PATCH | `/candidate/profile/experiences/{id}` | candidate | |
| DELETE | `/candidate/profile/experiences/{id}` | candidate | |
| GET | `/candidate/saved-jobs` | candidate | not paginated |
| POST | `/candidate/saved-jobs` | candidate | toggle |
| DELETE | `/candidate/saved-jobs/{jobId}` | candidate | |
| GET | `/applications` | candidate | not paginated |
| POST | `/applications` | candidate | re-apply allowed |
| GET | `/applications/requirements/{jobId}` | candidate | |
| GET | `/applications/{applicationId}` | candidate | detail shape |
| GET | `/recruiter/profile` | recruiter | |
| PATCH | `/recruiter/profile` | recruiter | |
| GET | `/recruiter/organisations` | recruiter | not paginated |
| POST | `/recruiter/organisations` | recruiter | |
| PATCH | `/recruiter/organisations/{id}` | recruiter | |
| DELETE | `/recruiter/organisations/{id}` | recruiter | |
| POST | `/recruiter/organisations/{id}/document` | recruiter | multipart, resets verified |
| POST | `/recruiter/organisations/{id}/logo` | recruiter | multipart |
| POST | `/recruiter/jobs` | recruiter | needs owned `organisation_id` |
| GET | `/recruiter/jobs/mine` | recruiter | all statuses |
| PATCH | `/recruiter/jobs/{jobId}` | recruiter | |
| PATCH | `/recruiter/jobs/{jobId}/status` | recruiter | active/paused/closed only |
| GET | `/recruiter/jobs/{jobId}/stats` | recruiter | |
| GET | `/recruiter/jobs/{jobId}/applicants` | recruiter | |
| GET | `/recruiter/jobs/{jobId}/applicants/facets` | recruiter | |
| GET | `/recruiter/jobs/{jobId}/applicants/{id}` | recruiter | |
| PATCH | `/recruiter/jobs/{jobId}/applicants/{id}/status` | recruiter | any→any |
| POST | `/recruiter/jobs/{jobId}/applicants/{id}/interview` | recruiter | replaces on repost |
| GET | `/notifications` | any | `?audience=` required |
| POST | `/notifications/read` | any | body `{audience}` |
| GET | `/conversations/{applicationId}/messages` | any | participant only |
| POST | `/conversations/{applicationId}/messages` | any | participant only |
| GET/POST | `/conversations/{applicationId}/typing` | any | participant only |

---

*This document reflects the code in `app/Http/Controllers/Api/`,
`app/Http/Resources/`, and `app/Enums/` as of the last update. If you find a
mismatch between this doc and the live API's actual behaviour, the code wins
— please flag it so this file gets corrected.*
