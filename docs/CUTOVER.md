# TEDA cutover notes

The edits and checks that turn the local build into the live site. P18 expands this
into the full pre-launch runbook; P16 establishes the canonical host and the
HTTPS/redirect posture.

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
