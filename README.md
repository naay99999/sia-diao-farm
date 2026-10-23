# Sia Diao Farm

A personal farm management app built for tracking a family fruit orchard. This is a hobby project — not production-grade software, but it works well enough for daily use in the field.

Feel free to fork it and adapt it for your own farm. No license restrictions.

## About

Sia Diao Farm (เสี่ยเดียวฟาร์ม) is a lean, single-owner durian tree tracker. It replaces paper notebooks with a mobile-friendly web app: scan a tree's QR label in the orchard to see its history and record what happened.

**Core features:**
- Orchards, each with its own trees
- Trees with printable QR labels (one sheet per orchard) that open the tree page directly
- Seasons per orchard (no overlapping dates, at most one open season)
- Care records (fertilizer, pesticide, other) applied to one or many trees at once
- Harvest recording per tree
- Sales per orchard
- Expenses with fixed categories, optionally tied to a season
- Season summaries and an orchard overview

The UI is Thai-only. There is a single owner account and no roles.

## Tech Stack

- **Backend:** Laravel 13, PHP 8.5, SQLite
- **Frontend:** React 19, Inertia.js v3, Tailwind CSS v4
- **Auth:** Laravel Fortify
- **Testing:** Pest v4

## Getting Started

**Requirements:** PHP 8.5+, Composer, Node.js 20+

```bash
# 1. Clone
git clone https://github.com/your-username/sia-diao-farm.git
cd sia-diao-farm

# 2. Environment
cp .env.example .env

# 3. PHP dependencies
composer install

# 4. Generate app key
php artisan key:generate

# 5. Database — creates SQLite file, runs migrations, seeds the owner account
#    (in the local environment also 2 demo orchards with 20 trees each)
php artisan migrate --seed

# 6. Frontend
npm install
npm run build

# 7. Start
php artisan serve
```

Open [http://localhost:8000](http://localhost:8000) and log in as the owner. Set the credentials in `.env` before seeding:

| Field    | Value                                          |
|----------|------------------------------------------------|
| Email    | `OWNER_EMAIL` (default `owner@example.com`)    |
| Password | `OWNER_PASSWORD` (default `password`)          |

`OWNER_PASSWORD` must be set to a non-default value for seeding outside `local`/`testing` environments; the seeder refuses to run otherwise. Re-running `db:seed` never overwrites an existing owner.

### Resetting the owner password

There is no password reset flow. Reset it from the command line:

```bash
php artisan tinker
>>> App\Models\User::where('email', config('farm.owner.email'))->first()->update(['password' => 'new-password']);
```

The password is hashed automatically by the model. The example looks the owner up by your `OWNER_EMAIL`.

## Dev Mode

```bash
composer run dev
```

Starts the Laravel server, Vite dev server, and Pail log viewer together.

## Running Tests

```bash
php artisan test --compact
```

## Deployment

The app is a single Laravel server with an SQLite file. No queue worker or scheduler is needed. Any host that runs PHP 8.5 works (Laravel Cloud, Forge, a VPS).

### 1. Environment

Set these in the server's `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://farm.example.com   # must be https and publicly reachable for the AI connection
APP_LOCALE=th
APP_FALLBACK_LOCALE=th

OWNER_EMAIL=you@example.com
OWNER_PASSWORD=a-long-unique-password
```

In production, passwords changed from the settings page must be at least 12 characters with mixed case, numbers and symbols.

### 2. Install and build

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate          # first deploy only
php artisan passport:keys         # first deploy only; the MCP OAuth flow fails without these keys
php artisan migrate --force
php artisan db:seed --force       # first deploy only; creates the owner account
npm ci && npm run build
php artisan optimize
```

On later deploys, run everything except the `first deploy only` lines. Keep `storage/oauth-private.key` and `storage/oauth-public.key` across deploys, or every connected AI app has to reconnect.

### 3. Observation photos

Photos are stored on the server in `storage/app/private/photos` and served through short-lived signed URLs. Make sure that folder survives deploys (it does unless you replace `storage/` wholesale).

Each observation takes up to 3 photos of 8 MB each, so raise PHP's upload limits (and the web server's body size, e.g. nginx `client_max_body_size 30M`):

```ini
upload_max_filesize = 8M
post_max_size = 30M
```

### 4. Backups

Everything lives on the server: the farm data in `database/database.sqlite`, the photos in `storage/app/private/photos` and the OAuth keys in `storage/oauth-*.key`. If the server's disk is lost, so is the farm, so copy all three off the server every day. A daily cron that snapshots the database and syncs to any off-site storage with [rclone](https://rclone.org) (an S3-compatible bucket, Google Drive, another machine):

```bash
cd /path/to/sia-diao-farm
mkdir -p ~/farm-db && sqlite3 database/database.sqlite ".backup '$HOME/farm-db/farm.sqlite'"
rclone sync ~/farm-db remote:farm-backup/database --backup-dir remote:farm-backup/database-old/$(date +%F)
rclone sync storage/app/private/photos remote:farm-backup/photos
rclone copy storage --include "oauth-*.key" remote:farm-backup/keys
```

Use `.backup` rather than `cp` so the copy stays consistent while the app is writing. `--backup-dir` keeps each day's previous database copy, so a bad day can be rolled back.

### 5. Connect Claude

After logging in, open **ตั้งค่า → เชื่อมต่อ Claude** and add the MCP URL it shows (`https://farm.example.com/mcp/farm`) as a custom connector in Claude. Claude asks you to log in and approve access once.
