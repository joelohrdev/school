# Kids Tracker

A personal, single-user app that pulls our kids' grades, assignments, missing work and
attendance from the district's PowerSchool on a weekday schedule, shows them on a
dashboard, and emails a short morning digest when something changed.

Laravel 13, Inertia, React, TypeScript and Tailwind (React starter kit with Fortify auth).

> **Security:** this app stores minors' school records: grades, attendance, teacher
> comments and, in encrypted raw payloads, their full PowerSchool record. Keep it
> behind login, on a private server, with HTTPS. Don't put it on public or shared
> hosting, and don't commit real data. Registration closes once the first account
> exists, so nobody else can sign up.

## How it works

- `App\Services\PowerSchool\PowerSchoolClient` talks to the district's legacy
  mobile-app endpoint (`PublicPortalServiceJSON`) over SOAP with HTTP Digest auth.
  It logs in as the parent, then fetches every kid's data in one call.
- `App\Services\PowerSchool\SyncService` upserts everything keyed on PowerSchool IDs,
  in one transaction per kid. Running it twice changes nothing the second time.
- `App\Services\PowerSchool\ChangeDetector` compares each run with the previous state
  and raises alerts:
    - work newly marked missing or late
    - missing work turned in or excused
    - newly posted scores
    - a class grade falling by at least `POWERSCHOOL_GRADE_DROP_THRESHOLD` points since
      the last snapshot

    A kid's first sync is a baseline, so it raises nothing.

- The 06:30 run emails a digest to `MAIL_TO_PARENT` covering every alert not yet
  emailed: newly missing work, still missing (count and oldest), grade drops and new
  scores. No change, no email.
- Each run is recorded in `sync_runs`. The raw PowerSchool payload is kept encrypted
  for 14 days for debugging, then pruned.

## Setup on Herd

```bash
git clone <this repo> school && cd school
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm run build
herd link school && herd secure school   # serves https://school.test
```

Then edit `.env`:

| Key                                             | What                                                                                 |
| ----------------------------------------------- | ------------------------------------------------------------------------------------ |
| `APP_URL`                                       | `https://school.test` locally, your real URL in production                           |
| `POWERSCHOOL_BASE_URL`                          | the district instance, e.g. `https://d118-powerschool.info`                          |
| `POWERSCHOOL_USERNAME` / `POWERSCHOOL_PASSWORD` | the parent portal login                                                              |
| `POWERSCHOOL_TIMEZONE`                          | `America/Chicago`                                                                    |
| `POWERSCHOOL_GRADE_DROP_THRESHOLD`              | points a class grade must fall to alert (default 3)                                  |
| `MAIL_TO_PARENT`                                | where the digest goes; leave blank to disable it                                     |
| `MAIL_MAILER` etc.                              | a real mailer in production; `log` locally writes mail to `storage/logs/laravel.log` |

The mobile-app digest credentials (`pearson` / `m0bApP5`) are public, static values
baked into the old app. They're defaults in `config/powerschool.php` and can be
overridden with `POWERSCHOOL_DIGEST_USERNAME` / `POWERSCHOOL_DIGEST_PASSWORD`.

Open the site and register. The first account is the only one allowed. Accounts must
verify their email: with `MAIL_MAILER=log` the verification link is in
`storage/logs/laravel.log`.

## Running a sync

```bash
php artisan powerschool:sync --dry-run   # fetch and compare, then roll back every write
php artisan powerschool:sync             # the real thing
php artisan powerschool:sync --digest    # and email the digest if anything changed
```

The command prints created/updated/unchanged/skipped counts per table and exits
non-zero if the sync failed. Run a dry run first on a new setup to check the login and
the field mapping.

The **Sync now** button queues a `SyncPowerSchoolJob`, so a queue worker must be
running. `composer run dev` (or `php artisan dev`) starts one along with the server and
Vite.

## Scheduler

`routes/console.php` schedules:

- `powerschool:sync --digest` at 06:30 on weekdays, Chicago time
- `powerschool:sync` at 15:45 on weekdays
- `model:prune` daily at 03:00, which removes raw payloads older than 14 days

Both syncs use `withoutOverlapping()`, and the service holds its own lock, so a manual
sync and a scheduled one never run at once. Scheduled syncs skip themselves on days in
PowerSchool's `notInSessionDays` when no kid has school.

**Locally:** run the scheduler in its own terminal:

```bash
php artisan schedule:work
```

**Forge / Ploi:** add the standard Laravel cron for the site user, and a queue worker
daemon (`php artisan queue:work --tries=1 --timeout=300`). Forge and Ploi both have a
scheduler toggle that adds the cron:

```
* * * * * cd /home/forge/school && php artisan schedule:run >> /dev/null 2>&1
```

## Previewing with fake data

`DemoSeeder` fills an empty database with made-up kids, grades and alerts. Point it at
a throwaway SQLite file, never your real database:

```bash
touch /tmp/kids-demo.sqlite
DB_DATABASE=/tmp/kids-demo.sqlite php artisan migrate --seed --seeder=DemoSeeder
```

Then serve with the same `DB_DATABASE` and sign in as `demo@example.test` /
`demo-password`.

## Tests

```bash
php artisan test
```

Pest tests cover:

- envelope building
- response unwrapping and nil/list normalization
- auth and transport failures
- idempotent sync
- every change-detection rule
- the digest
- the pages
- the registration lock

They use `Http::fake` with the sanitized fixtures in `tests/Fixtures/PowerSchool`.
**Never put real student data in fixtures.** The real response dump belongs in
`storage/app/private/fixtures/`, which is gitignored.

## Field mapping notes

Most field names come from a dump of the real response (key names only). These were
not in that dump, so the sync tries several likely spellings and skips, and counts,
rows it can't read:

- `notInSessionDays`: date from `calendarDay` / `date`, school from `schoolNumber`
- `attendance` / `attendanceCodes`: `attDate`, `attCodeid`, `periodid`; codes from `attCode` / `description`
- `terms`: `name` / `title`, `abbreviation`, `startDate`, `endDate`

Other assumptions to check against real data:

- `assignmentScores.assignmentId` matches `assignments.id`, not `assignments.assignmentid`.
- Date fields like `2026-11-03T00:00:00.000Z` mean that calendar day at the school, so they
  aren't shifted from UTC.
- PowerSchool sends no score timestamp. `scored_at` is the first sync that saw a score.
- Some sensitive student fields aren't stored as columns: date of birth, ethnicity,
  gender, meal balances and photo date. They remain only in the encrypted raw payload,
  which is pruned after 14 days.

If a dry run shows many `skipped` rows for a collection, its field names need mapping in
`SyncService`.
