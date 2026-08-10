# TEDA cutover notes

The edits and checks that turn the local build into the live site. P16 establishes
the canonical host and the HTTPS/redirect posture; P18 adds the launch gate, the
export/DNS steps and the rollback plan below.

## Go / no-go: the launch gate

**Do not begin cutover until `bin/prelaunch.sh` passes.** It is the single go/no-go
check and it fails, listing exactly what is left, until every blocker is closed and
content is verified (SPEC §11, §14):

```sh
teda-child/bin/prelaunch.sh
```

It refuses to pass while any of these is true: unverified published content
(`wp teda verify-gate`), a broken redirect, Lighthouse below the C2 budget, an axe
violation, any served image over 150KB, an unresolved `TODO(B*)` in code, the
canonical domain not resolving (B1), no tested backup restore (SPEC §14), an unticked
content-checklist row, or the donation-support owner still blank (B6).

## Cutover sequence (do these in order)

1. **Green gate** — `bin/prelaunch.sh` passes.
2. **Fresh backup + verified restore** — see the RUNBOOK "Restore drill". Never cut
   over without a backup you have actually restored.
3. **Provision the host** (Blocker B3): PHP 8.1+, MySQL 5.7+, free TLS, region close to
   Uganda/Europe/South Africa.
4. **Export from Local** and import to the host (below).
5. **Point DNS** for `tedauganda.org` at the host (below).
6. **Set the canonical constant** and WordPress's own URLs to the live host (below).
7. **Force HTTPS** and confirm no mixed content (below).
8. **Re-save permalinks** (Settings → Permalinks → Save) so CPT archive routes flush.
9. **Submit the sitemap** to Search Console once the domain resolves (below).
10. **Smoke test** the live site: homepage, one event, one news post, the donate page,
    a 301 from an old URL, and the browser console (no errors, no mixed content).
11. Keep the Local copy and the pre-cutover backup untouched for the **rollback
    window** (below).

## Export & move (Local → host)

Local by Flywheel has no "Studio" export; use one of:

- **Local's "Export"** (right-click the site → *Export*) to get a full zip, then import
  on the host, **or**
- **All-in-One WP Migration** (already an accepted plugin, SPEC §6) — export on Local,
  import on the host. Simplest for a volunteer team.

After import, the two custom pieces travel as normal plugin/theme folders (`teda-core`,
`teda-child`) — nothing bundles into the database, so a theme or plugin change never
loses content (D2).

## DNS

Once the host is chosen (B3) and the domain registered (B1):

1. At the registrar, set the domain's **A / AAAA record** (or **CNAME** if the host
   gives a hostname) to the host's address.
2. Allow for **propagation** (up to 24–48h; usually much less).
3. Only once the domain resolves to the host, do the canonical + URL changes below —
   doing them earlier points visitors and search engines at a domain that 404s.

## The one canonical-host edit (Blocker B1)

Every canonical URL, Open Graph tag and Event schema URL is derived from a single
constant so cutover is one change, not a find-and-replace:

```php
// teda-core/teda-core.php
define( 'TEDA_CANONICAL_HOST', 'https://tedauganda.org' );
```

- **B1: `tedauganda.org` is not registered yet.** Until it is, search engines are
  still told the canonical host is `tedauganda.org` (never `localhost` or staging) —
  which is correct *once the domain resolves*, and harmless before, because the site
  is not public.
- To point at a different production domain, change this one line.
- On staging, set it to `''` to fall back to WordPress's own home URL, so staging
  never advertises the production domain.

At cutover you will also update WordPress's own **Site Address / WordPress Address**
(Settings → General, or `wp option update home/siteurl`) to the production URL, and
re-save permalinks.

## HTTPS (SPEC §8.2)

HTTPS is enforced at the **host** (Blocker B3 — hosting not chosen yet), not in
application code, so local development over `http://localhost` keeps working. At
cutover:

1. Provision the TLS certificate (free via the host / Let's Encrypt).
2. Force HTTPS at the server (redirect `:80` → `:443`), or as a fallback add to
   `wp-config.php`:
   ```php
   define( 'FORCE_SSL_ADMIN', true );
   ```
3. Set both Site Address and WordPress Address to `https://…`.
4. **No mixed content:** the theme and plugin emit no hardcoded `http://` asset
   URLs — styles, scripts and images resolve through WordPress helpers that follow
   the site scheme, and the canonical host constant is `https://`. After cutover,
   load the homepage and one single view and confirm the browser console shows no
   mixed-content warnings.

## Redirects (SPEC §4.1)

The old static URLs are 301-mapped in `teda-core` (`Redirects\Map`). Verify with:

```sh
wp teda check-redirects
```

This runs in `bin/verify.sh` too. It asserts every old `.html` URL 301s to its new
home. Focus-area `#fragment` links (e.g. `/focus-areas.html#education`) are
forwarded client-side to `/focus-areas/education/` by a small script on the
focus-areas archive.

## Sitemap

Spaces and Publications are kept out of the XML sitemap until they have published
content (SPEC §10.1); they reappear automatically once populated. Nothing to do at
cutover beyond submitting the sitemap to Search Console once the domain resolves.

The live sitemap is WordPress core's `wp-sitemap.xml` (Rank Math's own sitemap module
does not serve here — do not submit `sitemap_index.xml`, which 404s).

## Rollback plan

If the live site is broken after cutover, roll back — do not debug in production while
visitors and search engines see errors.

**Before you cut over**, you already have two safety nets:
- the **pre-cutover backup** you restored in the drill (RUNBOOK), and
- the untouched **Local copy** on the build machine.

Keep both for a **rollback window of at least 7 days** after go-live.

**To roll back:**

1. **DNS-level (fastest, no data loss):** if the problem is the new host, point the
   domain's DNS back to the previous host / holding page. Propagation applies again,
   so this is not instant — which is why the smoke test in step 10 matters.
2. **Restore the backup:** on the host, restore the pre-cutover UpdraftPlus set (the
   same procedure as the drill). This returns the database and files to the known-good
   state.
3. **Revert the canonical constant** only if you also revert the domain: set
   `TEDA_CANONICAL_HOST` back to `''` (or the staging host) so nothing advertises a
   URL that is not serving.
4. **Announce nothing until green:** re-run `bin/prelaunch.sh` and the step-10 smoke
   test before re-attempting cutover.

**What is safe by design:** content lives in the database and in `teda-core` /
`teda-child` as ordinary plugin/theme folders (D2), so a rollback never has to
reconstruct content from scratch — restore the backup and the site is whole. The
canonical host is one constant, so there is never a find-and-replace to undo.
