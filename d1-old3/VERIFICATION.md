# Current verification — 14 November 2026

## Evidence

- PHP 8.3.32 and an isolated local MariaDB 10.4.32 database named aanshu_test on port 3308. The client's real database was inspected read-only for table names only; no client database migration or content mutation was performed.
- 71 existing real HTTP/database regression checks passed. See tests/last-run.txt.
- 48 additional checks passed at /aes/d1/public/ for nested paths, every admin tab, SEO include/save, canonical/sitemap URLs, forms/redirects, installation cookie scope, social profile validation, encrypted token storage, publishing queue states, duplicate prevention and recovery. See tests/social-last-run.txt.
- Real HTTP worker requests to a loopback provider simulator exercised Facebook text posts, Instagram create-container/status/publish, expired-token rejection, uncertain provider writes, explicit retries, deferred processing, cancelled/unpublished/deleted website posts and rejection of localhost Instagram media.
- Request receipts recorded method/path/field names and confirmed that sent/uncertain jobs were not repeated and deferred Instagram jobs reused the saved container.
- The database upgrade ran twice successfully on the disposable database.
- Browser checks passed: nested admin login/navigation, repaired SEO screen, social account controls, Help search with matching-topic count, and logout. Help and Social screenshots were saved separately.

## Diagnosed source issues

1. Root-relative browser URLs escaped /aes/d1/public/. Central path handling now covers HTML links/assets/forms, redirects, media and Help/SEO links.
2. The supplied SEO include used ROOT.'./app/seo-admin.php', producing a nonexistent filesystem path. The include now uses __DIR__ independently from browser URL handling.
3. Session cookies were shared across the whole host. Names/path are now isolated per installation.

## Verification limits

No real Meta token/account was supplied or contacted. The provider simulator proves local serialization, HTTP client behavior and modeled recovery only; it does not establish live authorization, app approval, account eligibility, remote image retrieval or actual publication. Live Meta account verification is required before release. Automatic publishing is implemented for Facebook Pages and single-photo Instagram professional-account posts only. LinkedIn/YouTube/X have profile links only.

Official Meta documentation was consulted. Orbit discovery was attempted but blocked by network permissions. The installed Postman executable is the desktop-app shim, not a usable CLI; Postman-hosted collection discovery/simulation was not used. A local HTTP simulator and its contract/evidence are included instead.

An isolated Apache launch could not validate workspace paths because of filesystem restrictions in this execution environment. Apache .htaccess access-control and rewrite rules therefore need verification on the running XAMPP/production host. No running XAMPP configuration was changed. Root/nested path behavior was verified with a PHP server using the bundled router.

Real email delivery, production HTTPS/proxy behavior, real mobile hardware, production backup restoration, external penetration testing and public deployment remain unverified. The package has no private config, tokens, sessions or client photos. This is not a zero-vulnerability guarantee.
