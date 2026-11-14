# Aanshu Engineering website

PHP + MySQL website for Aanshu Engineering Solution Private Limited. No Composer or Node dependencies. Uses the supplied AES logo and an original SVG illustration; no stock photo is presented as completed client work.

## Included

- Responsive public website: solar, 11/33 kV distribution works, company profile, scheme information, project gallery with filters, approved reviews, updates, contact and privacy notice.
- Admin login; editable main page copy and contact/company details; project photo upload, editing, drafts, publication and deletion; customer review management; website posts and caption copying; enquiry inbox and follow-up status; password change; recent activity log.
- WhatsApp deep links and phone links. Email links appear after a real business email is entered in admin.
- Optional hosting `mail()` notification. Every valid enquiry is saved to MySQL before notification is attempted. A successful mail-server acceptance does **not** prove inbox delivery.
- Prepared database statements, output escaping, CSRF tokens, session regeneration, Argon2id password hashes, protected draft media, image re-encoding, rate limiting and security headers.

This is a working initial release, not a claim of zero vulnerabilities or a completed production security audit. The site has not been deployed publicly.

## Requirements

Use a currently supported PHP release (tested locally with PHP 8.3.32), MySQL 8 / compatible MariaDB (tested with MariaDB 10.4.32), HTTPS and PHP extensions `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `session`; `curl` for integration tests. PHP must support `PASSWORD_ARGON2ID`. Choose maintained production runtime versions; local test versions are evidence, not hosting recommendations.

## Setup

1. Extract the entire `aanshu-website` folder outside the public web root.
2. Create a database and a dedicated database account. Use a migration account to install the schema. Afterwards use an application account limited to `SELECT, INSERT, UPDATE, DELETE` on this database; do not run the website as MySQL root.
3. Copy `config.example.php` to `config.php`. Set the DSN, database username/password, real `base_url`, and confirmed notification email. Generate `app_key` with:

   ```powershell
   php -r "echo bin2hex(random_bytes(32));"
   ```

4. Run from the project folder:

   ```powershell
   php bin/install.php
   php bin/admin.php owner@your-business-domain.in
   ```

   The second command reads a new password from terminal input. Use a unique password of at least 14 characters. There are no bundled/default production credentials. Running it for an existing email rotates that password and invalidates existing sessions.

5. Point the hosting document root **only** at `aanshu-website/public`. Do not upload `config.php`, `app`, `bin`, `storage`, `tests` or this README into a publicly served directory. If shared hosting cannot change its document root, keep these outside `public_html`, copy the contents of `public` into `public_html`, and update the `require` path at the top of each public PHP entry point to the private `app/bootstrap.php` absolute path.
6. Give only the PHP service account write access to `storage`. Restrict config read access to the deployment owner and PHP service account. Apply equivalent ACLs on Windows. PHP creates private session and photo directories there.
7. Visit `/admin.php`, sign in, enter the confirmed contact email and review page content. Upload real approved project photos and customer reviews. Tick “Publish on website” to make a record visible.

### Local development

For local development only, set `production` to `false` in your private config and use a local database:

```powershell
cd C:\path\to\aanshu-website
php -d upload_max_filesize=5M -d post_max_size=8M -S 127.0.0.1:8086 -t public
```

Open `http://127.0.0.1:8086/` and `http://127.0.0.1:8086/admin.php`. On this computer PHP is available at `C:\xampp\php\php.exe`. Local development credentials and database files are deliberately excluded from the ZIP. The PHP built-in server is for development only.

### Production hosting

Use PHP hosting or a VPS with Apache/Nginx + PHP-FPM + MySQL. This PHP/MySQL backend cannot run directly on static hosts or the Sites Cloudflare Worker runtime.

- HTTPS is mandatory when `production=true`; serve HTTP redirects at the web server. Behind a TLS proxy, configure the trusted web server to set PHP's `HTTPS=on` only for verified HTTPS traffic. The application deliberately does not trust visitor-supplied forwarding headers.
- Configure PHP: `display_errors=Off`, `log_errors=On`, `expose_php=Off`, `upload_max_filesize=5M`, `post_max_size=8M`, `memory_limit=256M`. Place upload temp files in an appropriate private writable directory. Configure server request size limits, timeouts, access logs and protected error logs.
- Store config and runtime data outside version control and archives. Back up the database and uploaded images together, encrypt backups, restrict access, and test restoring them.
- For email notifications, configure your hosting mail transport (including the correct sender, SPF/DKIM/DMARC) before enabling `notify_email`. `contact_email` in config is the fixed notification recipient; admin page `email` is the visitor-visible address. Update both if the mailbox changes. If hosting has no reliable mail transport, leave notifications disabled and use the admin inbox until SMTP is integrated.
- Run `php bin/maintenance.php` daily using your hosting scheduler. It removes expired rate buckets/sessions, orphaned images older than a day, and **closed** enquiries older than 180 days. Review old active enquiries manually. Align the privacy notice with the client's actual retention policy and provider arrangements.
- Limit `/admin.php` at the reverse proxy to a trusted VPN/IP range where practical; enable host-level monitoring and backups. MFA, roles, automated password reset, malware scanning, a WAF and independent penetration testing are not part of this release.
- Test valid/invalid forms, a fresh admin login, image uploads, HTTPS cookie flags, email delivery to the real inbox, backup restoration and mobile behavior on the deployed domain before launch.

## Social publishing: current boundary

Posts publish to this website only. The admin provides caption copying, an approved image link, and access to Meta Business Suite. Automatic Instagram/Facebook publishing is **not implemented or connected**; the UI states this explicitly.

To deliver it as the next phase, obtain the client's Facebook Page and Instagram professional account access, select the Meta login flow, configure an app and required permissions, store tokens server-side with encryption/rotation, and implement per-channel job status, token-expiry handling and duplicate prevention. Instagram media must be publicly retrievable for Meta's publishing flow. App review/access requirements depend on the chosen flow and accounts. Do not promise support for every social network as one generic checkbox.

Official references checked during implementation:
- [Instagram content publishing](https://developers.facebook.com/documentation/instagram-platform/content-publishing)
- [Facebook Page posts](https://developers.facebook.com/documentation/pages-api/posts)
- [PM Surya Ghar national portal](https://pmsuryaghar.gov.in/)

No client Meta credentials were provided; no social post, email or WhatsApp message was sent during testing.

## Content boundaries

The supplied company name, address, GSTIN, CIN and phone have been used as supplied, not independently authenticated. Verify phone ownership and company/registration details before launch. Scheme participation text is based on the client's statement; official vendor registration has not been verified. The site does not invent scheme endorsement, guaranteed subsidies, installation counts, testimonials or project photos.

Main business copy and contact details are editable. Navigation, supporting labels, process steps, privacy text and layout remain source-managed; this is a structured CMS, not a drag-and-drop page builder. The public gallery shows up to 24 newest published projects, 12 reviews and 12 posts. Admin currently shows the 200 newest records in each list. For larger volumes, add pagination/search before relying on it as a long-term CRM.

## Verification

`tests/integration.php` runs real HTTP requests against a disposable database named `aanshu_test` with `production=false`. It creates and deletes test records. **Never point it at client data.** It expects the local test server on port 8086 and the test-only account `test@example.com` with password `LocalVerificationOnly!2026`; this account is not seeded by setup and must never exist in production.

```powershell
php tests/integration.php
```

See `VERIFICATION.md` for the actual checks and limitations. PHP lint and desktop/mobile browser inspection were also performed. No external delivery or production deployment is implied by local results.

## Gallery and SEO update

See `UPGRADE.md` for safe migration of an existing installation. Projects now support 1–12 photos, cover selection, individual removal, per-photo alt text and a public project gallery page. New SEO administration manages homepage/privacy metadata, canonical URL, social previews, Google verification and indexing. The XML sitemap includes published project pages only. Project SEO derives from the project's editable content.

For multiple uploads, use `post_max_size=64M` instead of the original 8M setting above, and set the web-server body limit accordingly. The per-image maximum remains 5 MB. Migration requires CREATE privileges; normal application credentials only need CRUD privileges afterwards.
