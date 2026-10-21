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

### Resetting the owner password

There is no password reset flow. Reset it from the command line:

```bash
php artisan tinker
>>> App\Models\User::where('email', 'owner@example.com')->first()->update(['password' => 'new-password']);
```

The password is hashed automatically by the model.

## Dev Mode

```bash
composer run dev
```

Starts the Laravel server, Vite dev server, and Pail log viewer together.

## Running Tests

```bash
php artisan test --compact
```
