# TEDA operations runbook

Operational notes for keeping the TEDA site correct and fresh. This file starts
with the content-automation pieces added in P14; P18 expands it into the full
pre-launch and maintenance runbook.

## Keeping content fresh (SPEC §10.2)

The site is designed so that **no user-visible countdown, date or list ever needs
manual intervention to stay correct**:

- **Events** transition to "Past" **at query time** — the archive and homepage
  compute this from `end_datetime`/`start_datetime` on every request, so an event
  becomes "past" the instant its time passes, with or without cron.
- **Opportunities** are reconciled daily: any published role past its `deadline`
  has `teda_is_open` flipped off and moves to "Recently closed". Display already
  treats a past-deadline role as closed at query time, so even a missed cron run
  never shows a stale "open" role to visitors — the cron only keeps the stored
  flag honest for the dashboard, exports and reporting.

### The daily job

`teda-core` schedules one daily WP-Cron event, `teda_core_daily`, which runs the
reconciliation. It is **idempotent** — safe to run twice in the same minute — and
also exposed as a WP-CLI command:

```sh
wp teda close-expired      # reconcile opportunities now; prints how many closed
wp teda staleness-report   # print the same rows as the dashboard widget
```

### ⚠️ WP-Cron reliability — read this before launch

WordPress's built-in cron (`WP-Cron`) is **not a real system cron**. It only fires
when someone visits the site. On a **low-traffic site** — which a new organisation's
site usually is — hours or days can pass with no visitor, so the daily job may not
run on time. The consequences here are mild (a closed role keeps its stored "open"
flag a little longer; the display is still correct), but reporting drifts.

**Recommended for production:** disable pseudo-cron and drive it from the host's
real cron instead.

1. In `wp-config.php`:

   ```php
   define( 'DISABLE_WP_CRON', true );
   ```

2. Add a system crontab entry (adjust the path and WP-CLI binary for the host).
   Once daily is enough for content freshness; hourly is fine too:

   ```cron
   # Run WordPress's due events every 15 minutes
   */15 * * * * cd /path/to/teda/public && wp cron event run --due-now >/dev/null 2>&1

   # Belt-and-braces: reconcile expired roles once a day at 02:00
   0 2 * * * cd /path/to/teda/public && wp teda close-expired >/dev/null 2>&1
   ```

If the host offers no system cron, leave WP-Cron enabled — the query-time
transitions mean the public site stays correct regardless; only the dashboard
counts may lag until the next visit.

### The staleness dashboard widget

Editors and admins see a **"TEDA — content to review"** widget on the dashboard.
It surfaces the quarterly-review checklist as live counts, each linking to the fix:

- newest news post older than 90 days,
- no upcoming events scheduled,
- open roles past their deadline (should be zero if cron is running),
- published-but-unverified news/events (D13),
- team members still awaiting confirmation.

`wp teda staleness-report` prints the same data for scheduling into an email.
