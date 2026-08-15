# Running the Inthes API in Docker

```bash
docker compose up -d --build
```

Then: **http://localhost:8000/api/v1/jobs**

First start takes a few minutes (installs Composer + npm deps, builds assets,
runs migrations and the demo seeder). Subsequent starts are seconds.

---

## What comes up

| Service | Image | Port | Role |
|---|---|---|---|
| `nginx` | built (`web` stage) | `8000` → 80 | Public entrypoint; serves `public/` and uploads directly |
| `app` | built (`runtime` stage) | 9000 (internal) | php-fpm; runs migrations and cache warming on start |
| `queue` | same image as `app` | — | `queue:work`; `QUEUE_CONNECTION=database` |
| `mysql` | `mysql:8.4` | `3307` → 3306 | Data; `3307` on the host so it doesn't clash with Laragon |

Two named volumes survive `docker compose down`: `mysql_data` (the database) and
`storage_data` (uploaded resumes, photos, intro videos, logs).

---

## Configuration

Everything is read from `.env.docker`, which is committed because it holds only
local-stack defaults. **Do not put real credentials in it** — use a
git-ignored `.env.docker.local`, or your platform's secret store.

Settings worth knowing about before the first run:

| Variable | Default | Notes |
|---|---|---|
| `APP_PORT` | `8000` | Host port for the API |
| `APP_URL` | `http://localhost:8000` | **Must match the host clients actually use.** Signed resume / intro-video / document URLs are generated against it — a mismatch loads the API fine and then 404s every file |
| `APP_KEY` | empty | Generated on first start. Set it explicitly for anything but local dev (see below) |
| `RUN_SEEDERS` | `true` | Demo data. **Set to `false` after the first run** or the seeder duplicates its rows on every restart |
| `CACHE_CONFIG` | `false` | Off in dev so code edits are picked up. Turn on for staging/production |
| `OTP_DEBUG_CODE` | `123456` | Dev-only. See the warning at the bottom |

### APP_KEY

Left empty, the entrypoint generates one at container start — which means a
**new key on every rebuild**. That invalidates every encrypted value and every
signed file URL already handed out. Fine for local dev; wrong everywhere else.

Generate one and pin it:

```bash
docker compose run --rm app php artisan key:generate --show
# copy the base64:... value into .env.docker
```

---

## Common commands

```bash
# logs
docker compose logs -f app
docker compose logs -f queue

# artisan
docker compose exec app php artisan migrate:status
docker compose exec app php artisan tinker

# tests
docker compose exec app php artisan test

# mysql shell
docker compose exec mysql mysql -uroot -proot jobportal_new

# rebuild after changing Dockerfile / dependencies / assets
docker compose up -d --build

# stop, keeping data
docker compose down

# stop and DELETE the database and all uploads
docker compose down -v
```

### Seeded logins

OTP is `123456` for every account (`OTP_DEBUG_CODE`).

| Phone | Role |
|---|---|
| `9876543210` | candidate — complete profile |
| `9812345678` | candidate — shortlisted, has a chat thread |
| `9000000001` | recruiter — verified organisation |
| `9000000002` | recruiter — unverified organisation |

---

## Connecting the Flutter app

The container publishes on the host's port 8000, so the same options as a
`php artisan serve` setup apply:

| Client | Base URL |
|---|---|
| Android emulator | `http://10.0.2.2:8000/api/v1` |
| Physical phone over USB | `http://127.0.0.1:8000/api/v1` after `adb reverse tcp:8000 tcp:8000` |
| Phone on the same WiFi | `http://<host-lan-ip>:8000/api/v1` |

Set `APP_URL` in `.env.docker` to the **same** host, or file downloads break
while the rest of the API works.

---

## Notes on how it is built

**Multi-stage.** `assets` (Node) builds the Vite bundle, `vendor` (Composer)
resolves PHP dependencies, `runtime` (php-fpm) takes both. Neither Node nor
Composer ends up in the final image.

**The asset build needs outbound HTTPS.** `vite.config.js` uses
laravel-vite-plugin's `bunny()` font plugin, which downloads Instrument Sans at
build time. On a network that can't reach `fonts.bunny.net` the build fails with
a connect timeout — remove the `fonts:` block from `vite.config.js` to build
offline.

**nginx carries its own copy of `public/`.** Sharing it as a named volume looks
tidier but is a trap: a named volume is seeded from the image only when first
created, so after a rebuild it keeps serving the *previous* build's assets until
someone deletes the volume by hand. `storage/` is still a volume — uploads have
to survive rebuilds — and is mounted read-only into nginx so the
`public/storage` symlink resolves.

**Upload limits are set in three places** and all three have to clear the app's
50MB intro-video cap: `client_max_body_size` (nginx), `upload_max_filesize` /
`post_max_size` (PHP), and the app's own validation. nginx and PHP both reject
an oversized body *before* Laravel sees it, and neither returns the JSON error
envelope the API contract promises — so both are set above the cap, not at it.

**`opcache.validate_timestamps=0`.** Code in an image never changes at runtime;
a rebuild replaces the container. This is also why `CACHE_CONFIG=false` is the
dev default — with config cached *and* opcache not revalidating, a change needs
a rebuild to appear.

**Migrations run in the `app` container only.** The queue worker shares the
image but starts with `RUN_MIGRATIONS=false`, so the two don't race each other
on startup.

---

## ⚠️ Before deploying this anywhere real

`.env.docker` is tuned for local development. At minimum:

- **Remove `OTP_DEBUG_CODE`, `OTP_MAX_SENDS`, `OTP_SEND_WINDOW_MINUTES` and
  `OTP_MAX_ATTEMPTS`.** Together they let anyone sign in as any account knowing
  only the phone number, with unlimited attempts.
- Set a fixed `APP_KEY`.
- `APP_DEBUG=false`, `APP_ENV=production`.
- Real database credentials, not `secret` / `root`.
- `RUN_SEEDERS=false`, `CACHE_CONFIG=true`.
- Put TLS in front of nginx; the app issues `http://` signed URLs otherwise.
