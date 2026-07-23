# Deploying SwiftTrack to Render + TiDB Cloud (free)

This hosts the app on **Render** (free web service, built from the `Dockerfile`
in this repo) with the database on **TiDB Cloud Serverless** (free, MySQL-8
compatible).

You do **not** need Docker installed on your computer — Render builds the
image in the cloud. You only need **Git** and a **GitHub** account.

**Time:** ~20–30 minutes. **Cost:** $0, no card required.

---

## What's already set up for you

| File | Purpose |
|---|---|
| `Dockerfile` | Builds the app image (PHP 8.3 + nginx + PHP-FPM, compiles assets) |
| `render.yaml` | Render Blueprint — provisions the service and its env vars |
| `docker/` | nginx, PHP, supervisor and start-up config |
| `.dockerignore` | Keeps the build lean |
| `.env.production.example` | Reference for every production env var |

The `config/database.php` already supports TiDB's required TLS connection, and
sessions/cache/queue are set to run **off** the database to spare TiDB's free
request-unit quota.

---

## Step 1 — Push this project to GitHub

If you don't have a GitHub account, create one at <https://github.com/signup>.

1. Create a new **empty** repo on GitHub (no README) — e.g. `swifttrack-courier`.
2. In the project folder, push this code (a git repo is already initialised):

```bash
git remote add origin https://github.com/<your-username>/swifttrack-courier.git
```

```bash
git push -u origin main
```

> `vendor/`, `node_modules/` and `.env` are gitignored on purpose — Render
> installs dependencies itself during the build.

---

## Step 2 — Create the TiDB Cloud Serverless database

1. Sign up at <https://tidbcloud.com> (free, no card).
2. Create a **Serverless** cluster (pick the region nearest you).
3. When it's ready, open **Connect**:
   - Set a **password** for the user if prompted.
   - Choose **Connect With: General**. Note these values:

   | TiDB field | Goes into |
   |---|---|
   | Host (e.g. `gateway01.<region>.prod.aws.tidbcloud.com`) | `DB_HOST` |
   | Port (`4000`) | `DB_PORT` |
   | User (e.g. `3xAmpLe.root`) | `DB_USERNAME` |
   | Password | `DB_PASSWORD` |

4. Create a database named **`courier_db`**: open the **SQL Editor** (or Chat2Query)
   and run:

   ```sql
   CREATE DATABASE courier_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

   Use `courier_db` as your `DB_DATABASE`.

> TiDB Serverless requires TLS. The image already trusts it via the system CA
> bundle (`MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt`) — nothing to
> download.

---

## Step 3 — Generate an APP_KEY

Render needs an application key. Generate one locally and copy the output
(it starts with `base64:`):

```bash
php artisan key:generate --show
```

Keep it handy for the next step. (You can reuse the `APP_KEY` from your local
`.env` if you prefer.)

---

## Step 4 — Deploy on Render

1. Sign up at <https://render.com> (free, no card) and connect your GitHub.
2. **New +** → **Blueprint** → pick your `swifttrack-courier` repo.
   Render reads `render.yaml` and shows the `swifttrack` web service.
3. It will prompt for the values marked `sync: false`. Fill them in:

   | Key | Value |
   |---|---|
   | `APP_KEY` | the `base64:...` key from Step 3 |
   | `APP_URL` | leave blank for now, or `https://swifttrack.onrender.com` |
   | `DB_HOST` | TiDB host |
   | `DB_DATABASE` | `courier_db` |
   | `DB_USERNAME` | TiDB user |
   | `DB_PASSWORD` | TiDB password |

   (`DB_PORT`, `MYSQL_ATTR_SSL_CA`, and the rest come from `render.yaml`.)

4. Click **Apply**. Render builds the image and deploys.
   **The first deploy takes a few minutes** — it compiles assets, then on boot
   it runs migrations and seeds the demo data (a one-time step).

5. When it's live, copy your URL (e.g. `https://swifttrack.onrender.com`),
   set it as **`APP_URL`** in the Render dashboard → **Save** (this triggers a
   quick redeploy so QR codes and email links use the right domain).

---

## Step 5 — Sign in

Open your Render URL. Use any demo account (password **`password`**):

| Role | Email |
|---|---|
| Super Admin | `admin@swifttrack.lk` |
| Branch Manager | `manager.colombo@swifttrack.lk` |
| Dispatcher | `dispatcher.colombo@swifttrack.lk` |
| Driver | `driver1@swifttrack.lk` |

The public tracking page works with no login at `/track`.

---

## Good to know

- **The free service sleeps** after ~15 minutes of no traffic. The next visit
  takes ~30–60 seconds to wake — normal for Render's free tier.
- **Uploads are ephemeral.** Driver photos / parcel images / signatures are
  wiped on each redeploy; the seeded demo uses placeholder avatars so it still
  looks complete. For permanent uploads, add free S3-compatible storage
  (Cloudflare R2 or Backblaze B2) and switch `FILESYSTEM_DISK`.
- **Emails** use the `log` driver, so password-reset / verification links are
  written to the Render logs, not sent. For real email set `MAIL_MAILER=smtp`
  with a provider like Brevo or Mailtrap (both have free tiers).
- **Re-seeding** never happens automatically after the first boot (it's guarded
  on an empty database). To wipe and reseed, use Render's **Shell** tab:
  `php artisan migrate:fresh --seed --force`.

---

## Troubleshooting

**Build fails on `composer install`** — check the Render build log; usually a
missing PHP extension. All required ones are in the `Dockerfile`.

**`SQLSTATE[HY000] [2002]` / connection refused** — a `DB_*` value is wrong, or
`MYSQL_ATTR_SSL_CA` was changed. Re-check the TiDB Connect values.

**`SQLSTATE ... SSL connection error`** — the TLS CA path is wrong. It must be
exactly `/etc/ssl/certs/ca-certificates.crt`.

**Page loads but unstyled** — the asset build didn't run. Confirm the build log
shows the `npm run build` (assets) stage completing.

**500 error, blank screen** — temporarily set `APP_DEBUG=true` in Render to see
the error, then set it back to `false`.

**Health check never passes on first deploy** — first boot seeds ~800 parcels,
which can take a minute over the network. Give it time; subsequent deploys are
fast. To skip seeding, set `RUN_SEED=false`.
