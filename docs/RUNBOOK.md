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

---

# Volunteer maintenance schedule

The recurring jobs that keep TEDA's site correct, legal and safe. Each says **who**,
**how often**, and **exactly what to do**. Print this page; tick the boxes.

## The one named owner you must fill in first

> **Donation support owner (Blocker B6):** `__________________________`
> (name) — `__________________________` (email/phone).
>
> **This must be a real, named person before the site handles any money.** They own
> every donation question, reconciliation discrepancy and refund. An unowned donation
> problem becomes a reputational problem for TEDA. **Until this line is filled in,
> `bin/prelaunch.sh` fails on purpose** (it greps this file for the placeholder).

## Weekly — donation reconciliation (SPEC §7)

*Owner: the donation-support owner named above. Every week, same day.*

The site is in **offline donation mode**: it shows TEDA's bank and mobile-money
details and asks givers to send directly. There is no automatic record, so a human
reconciles:

1. Open the TEDA **bank statement** and the **MTN/Airtel mobile-money** statements for
   the week.
2. For each incoming gift, record: date, amount, **currency (UGX or USD — never
   assume)**, sender reference, and channel.
3. Cross-check against any **event-registration** or **Join** form entries that
   mentioned a pledge.
4. Note anything unexplained and follow it up. Thank donors where you have contact
   details and consent.
5. File the week's tally in TEDA's finance record.

*When live giving is switched on (P20), this step also checks the gateway payout
report against donor receipts and watches for duplicate charges — the refund path
runs through the same named owner.*

## Weekly — backup verification

*Owner: an administrator.*

1. Open **UpdraftPlus** (left menu, admins only) and confirm the **most recent backup
   succeeded** and reached remote storage (not just the local server).
2. **Once a quarter, actually restore one backup into a scratch/staging site** — a
   backup you have never restored is a hope, not a backup. See "Restore drill" below.

## Quarterly — content review (SPEC §10.2)

*Owner: content volunteer + an administrator. Every three months.*

The **"TEDA — content to review"** dashboard widget does most of the thinking. Work
the list to zero:

1. Run `wp teda staleness-report` (or read the widget).
2. Refresh or retire the **oldest news** if the newest post is over 90 days old.
3. Make sure at least **one upcoming event** is scheduled, or the events section
   invites people to nothing.
4. Clear any **open roles past their deadline** (cron should have closed them).
5. Confirm or remove **unverified** news/events and **"Name to confirm"** team
   members — see `docs/CONTENT-CHECKLIST.md`.
6. Spot-check that the homepage statistics still match TEDA's records.

## Annual — form-entry pruning (SPEC §8.2)

*Owner: an administrator. Once a year (put it in the calendar).*

TEDA keeps personal data (names, emails, phone numbers from the Join and
registration forms) **only as long as it is needed**. Once a year:

1. Left menu → **Fluent Forms → Entries** (administrators only — this menu is hidden
   from Editors by design).
2. **Export** the entries you must keep for records (CSV), store them securely.
3. **Delete entries older than 12 months** that are no longer needed.
4. Honour any **removal request** immediately regardless of the annual cycle — the
   consent text promises removal on request to `tedayouthteso@gmail.com`.

## On request, within 7 days — photo takedown (SPEC §8.3)

*Owner: an administrator. Start the moment a request arrives.*

Anyone in a photo may ask to be removed. TEDA's promise is **removal within 7 days**:

1. Find the photo(s) — check the **Gallery** albums and any News post using the image.
2. **Remove or replace** every public use of it.
3. Delete the file from the **Media Library** so it is not reachable by direct link.
4. If the site is cached/CDN-fronted, **purge the cache** (W3 Total Cache → *Purge All
   Caches*) so the image stops being served.
5. Record the request and the date you completed it in the **photo consent log**
   (`teda-child/docs/PHOTO-CONSENT-LOG.md`).

---

## Restore drill (do this before launch, then quarterly)

A backup is only real once you have restored it. The pre-launch gate
(`bin/prelaunch.sh`) refuses to pass until this has been done at least once and the
result recorded.

1. In **UpdraftPlus → Settings**, confirm a **remote storage** destination is set
   (free options: Google Drive, Dropbox) and a **schedule** (weekly files + database
   is the minimum).
2. Take a **fresh backup** ("Backup Now", include database + files).
3. Spin up a **scratch site** (a second Local site, or a staging install — *never
   restore over production to "test"*).
4. Install UpdraftPlus there, upload the backup set, and **Restore**.
5. Confirm the scratch site loads: homepage, one event, one news post, the donate
   page.
6. **Record it below.**

### Restore-drill log

| Date | Who | Backup date restored | Result | Notes |
|------|-----|----------------------|--------|-------|
|      |     |                      |        |       |

*(`bin/prelaunch.sh` looks for at least one completed row here — a row whose Result
says the restore succeeded.)*

