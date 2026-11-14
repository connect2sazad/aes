# Aanshu Engineering — updated PHP/MySQL website

This update is based on the supplied d1.zip. It preserves the website layout, project galleries and SEO controls, fixes subfolder routing and the SEO include error, and adds social profiles, Facebook/Instagram publishing jobs and a searchable admin handbook.

## Start here

- Existing XAMPP installation: follow **UPGRADE.md**. Preserve your private config.php and uploaded images.
- Fresh installation: copy config.example.php to private config.php, configure MySQL, generate a unique 64-character app_key, run `php bin/install.php`, then `php bin/admin.php owner@example.com`. Set a unique password of at least 14 characters through terminal input.
- Point production hosting at public/ only. The app works at the domain root or in a subfolder such as /aes/d1/public/.
- Open the installation's public/admin.php. **Help & docs** contains the user handbook, search, print controls and setup checks. An offline copy is in ADMIN_GUIDE.md.

## URL behavior

All browser links, assets, forms, redirects and media links use a central application-path helper. The public directory is detected from SCRIPT_NAME. No per-file slash replacement is needed. PHP require/include paths stay filesystem paths based on __DIR__.

If an unusual reverse proxy changes the exposed path, configure `base_path` in private config.php, for example `/aes/d1/public` (no domain, no trailing slash). Leave it null or omit it for ordinary XAMPP and document-root hosting. Public canonical/social URLs are separate: set the complete public URL, including any subfolder, in Admin → SEO settings.

Cookies are scoped to the installation and its source directory; existing admins will need to sign in once after upgrading.

## Social features

- Admin-editable public profile links: Facebook, Instagram, LinkedIn, YouTube and X.
- Automatic publishing queue: Facebook Page text/photo posts and single-photo Instagram professional-account posts through Meta's Facebook Login flow.
- Private, encrypted token storage; no tokens rendered back to the browser.
- Caption/photo snapshots, per-network result tracking, remote IDs, duplicate-job prevention, cancellation and explicit checked retries.
- No direct LinkedIn/YouTube/X publishing, Reels/videos/carousels, automatic OAuth onboarding or automatic token refresh in this release. Those networks have profile links only.

A developer must configure the Meta app/login/permissions and obtain authorized tokens for the client's actual accounts. Saving credentials means configured, not verified. An Instagram professional account must be linked to the Page for the chosen login flow. Actual publication still needs a publicly reachable HTTPS photo URL, working credentials and a scheduled worker:

```powershell
php bin/publish-social.php
```

Run it every minute through the hosting scheduler or Windows Task Scheduler. It processes up to five due jobs per run. Do not schedule the disposable provider simulator. No actual Meta accounts were contacted during verification.

## Runtime and maintenance

Use a maintained PHP/MySQL stack with pdo_mysql, mbstring, gd, openssl and curl. PHP's Argon2id password hashing must be available. For uploads configure upload_max_filesize=5M, post_max_size=64M, max_file_uploads=20 and memory_limit=256M; match the web-server request-size limit. Normal database traffic needs CRUD privileges; installation/upgrade needs permission to create tables.

Production uses HTTPS enforcement. Keep credentials, app_key, PHP sessions, upload storage and backups private. Prefer a public/-only document root. Included Apache access rules deny the parent/private directories and allow public/; they require Apache AllowOverride support. On Nginx configure equivalent private-directory protection and robots/sitemap routing. Do not rely on .htaccess on a server that ignores it.

Run `php bin/maintenance.php` daily for expired sessions/rate limits, orphan photos and closed enquiries older than 180 days. Review old active enquiries separately. Back up MySQL, config/app_key and photos together and test restoration. Keep runtimes patched.

Enquiries are saved before notification email is attempted. Email notifications need a confirmed recipient and a working hosting mail transport in private config; actual inbox delivery is unverified. WhatsApp/email links open the visitor's messaging app; they do not send automatically.

## Verification

119 checks passed: 71 existing HTTP/database regression checks and 48 subfolder/social checks. PHP lint and browser checks of nested login, SEO, help search and social screens passed. The publishing worker used its real HTTP client against a local provider simulator; this proves those modeled flows, not live Meta compatibility or account approval. See VERIFICATION.md and tests/SIMULATION.md.

This is a tested implementation, not a zero-vulnerability guarantee. Live Meta publishing, real email delivery, independent penetration testing and production hosting remain to be verified.
