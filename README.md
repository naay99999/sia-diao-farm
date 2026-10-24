# Sia Diao Farm

A personal farm management app for a family's orchards. This is a hobby project — not production-grade software, but it works well enough for daily use in the field.

Feel free to fork it and adapt it for your own farm. No license restrictions.

## About

Sia Diao Farm (เสี่ยเดียวฟาร์ม) is a farm management app for one owner with several orchards, tracked tree by tree. It supports durian today; the data model is meant to take more crops later.

- **Desktop first.** The web app covers everything: orchards, trees, records, edits, reports.
- **Mobile for viewing.** Every page is responsive, and each tree's QR label opens its page on the phone.
- **Claude through MCP.** The standout feature. The farm data is exposed as an MCP server, so family members can ask Claude to analyse an orchard, suggest fertilizer or pesticide, and then record the work straight from the chat. Field work is meant to be recorded this way rather than through mobile forms.

**Core features:**
- Orchards, each with its own trees
- Trees with printable QR labels (one sheet per orchard) that open the tree page directly
- Seasons per orchard (no overlapping dates, at most one open season)
- Care records (fertilizer, pesticide, other) applied to one or many trees at once
- Tree observations (problems and growth stages) with follow-ups
- Harvest recording per tree
- Sales per orchard
- Expenses with fixed categories, optionally tied to a season
- Season summaries and an orchard overview
- Farm MCP server (OAuth) with read and write tools, and an in-app "ผู้ช่วย AI" page that shows what to ask Claude

The UI is Thai-only. There is one owner, who manages family member accounts; members see and edit every orchard. Every record keeps who made it and whether it came from the app or from Claude.

Product direction: [`docs/superpowers/specs/2026-10-24-product-direction-design.md`](docs/superpowers/specs/2026-10-24-product-direction-design.md).

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

### Docker on Dokploy

The repo ships a `Dockerfile` (FrankenPHP) and a `compose.yaml` for [Dokploy](https://dokploy.com). Dokploy's Traefik handles the domain, HTTPS and networking; the container only serves plain HTTP on port 80 and trusts the proxy's forwarded headers.

1. In Dokploy, create a **Docker Compose** service from this repository (compose path `./compose.yaml`).
2. Under **Environment**, set:

   ```dotenv
   APP_KEY=base64:...                 # generate once with: php artisan key:generate --show
   APP_URL=https://farm.example.com
   OWNER_EMAIL=you@example.com
   OWNER_PASSWORD=a-long-unique-password
   ```

   Keep `APP_KEY` unchanged across deploys; changing it logs everyone out and breaks encrypted values.
3. Under **Domains**, add `farm.example.com` for service `app`, container port `80`, with HTTPS on.
4. Deploy.

On every start the container creates the SQLite file and OAuth keys if they are missing, runs migrations, makes sure the owner account exists and caches the config. PHP upload limits for photos are already set in `docker/php.ini`.

Everything that must survive a redeploy lives in the `farm-storage` volume, mounted at `/app/storage`:

| Path in the volume         | Contents                      |
|----------------------------|-------------------------------|
| `database/database.sqlite` | All farm data                 |
| `app/private/photos`       | Observation photos            |
| `oauth-*.key`              | Keys for the Claude connection |

Back up that volume daily. A consistent database snapshot from the VPS:

```bash
docker exec $(docker ps -qf "label=com.docker.compose.service=app" | head -n1) \
  sqlite3 storage/database/database.sqlite ".backup 'storage/backup.sqlite'"
```

Then copy the volume's folder (`docker volume inspect` shows its path) off the server with `rclone`, as described under [Backups](#4-backups). Dokploy's own volume backups work too.
### Without Docker

#### 1. Environment

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

#### 2. Install and build

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

#### 3. Observation photos

Photos are stored on the server in `storage/app/private/photos` and served through short-lived signed URLs. Make sure that folder survives deploys (it does unless you replace `storage/` wholesale).

Each observation takes up to 3 photos of 8 MB each, so raise PHP's upload limits (and the web server's body size, e.g. nginx `client_max_body_size 30M`):

```ini
upload_max_filesize = 8M
post_max_size = 30M
```

#### 4. Backups

Everything lives on the server: the farm data in `database/database.sqlite`, the photos in `storage/app/private/photos` and the OAuth keys in `storage/oauth-*.key`. If the server's disk is lost, so is the farm, so copy all three off the server every day. A daily cron that snapshots the database and syncs to any off-site storage with [rclone](https://rclone.org) (an S3-compatible bucket, Google Drive, another machine):

```bash
cd /path/to/sia-diao-farm
mkdir -p ~/farm-db && sqlite3 database/database.sqlite ".backup '$HOME/farm-db/farm.sqlite'"
rclone sync ~/farm-db remote:farm-backup/database --backup-dir remote:farm-backup/database-old/$(date +%F)
rclone sync storage/app/private/photos remote:farm-backup/photos
rclone copy storage --include "oauth-*.key" remote:farm-backup/keys
```

Use `.backup` rather than `cp` so the copy stays consistent while the app is writing. `--backup-dir` keeps each day's previous database copy, so a bad day can be rolled back.

### Connect Claude

The owner adds each family member's account at **ตั้งค่า → สมาชิก**. Then each person logs in and opens **ผู้ช่วย AI** (`/assistant`). The owner sees a setup section there with the MCP URL (`https://farm.example.com/mcp/farm`) to add as a custom connector in Claude. Claude asks that person to log in and approve access once.
