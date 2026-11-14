# Update your existing C:\xampp\htdocs\aes\d1 installation

## 1. Back up and merge the update

Back up your database, private config.php and the complete storage/photos directory outside htdocs. The supplied old d1.zip included private config and session files; keep that archive outside publicly served folders.

Extract the **contents** of AES-D1-Updated.zip directly into:

`C:\xampp\htdocs\aes\d1`

Merge and overwrite application files, including the hidden .htaccess files. Do not delete the existing storage directory and do not replace your private config.php. The new ZIP deliberately contains no private config, sessions, access tokens or installation photos. Its storage directory contains only protection files/placeholders.

## 2. Apply the database update

Run in PowerShell:

```powershell
cd C:\xampp\htdocs\aes\d1
& C:\xampp\php\php.exe bin\upgrade.php
```

The upgrade is additive and repeatable. It preserves existing content, photos and cover selections and creates social_profiles, social_accounts and social_jobs. It also creates missing gallery/SEO tables for older versions. Use migration-capable database credentials for this step, then return to restricted CRUD credentials for web traffic. Existing data is not seeded or replaced.

## 3. Open the correct local URL

Continue using your current HTTPS setup:

`https://localhost/aes/d1/public/admin.php`

Sign in again. The old session cookie is replaced by one scoped to this installation. Links, assets, login/logout, enquiry redirects and project images automatically keep /aes/d1/public/. You do not need to edit each URL or replace slashes in PHP include paths.

If you choose HTTP for local development instead, set production=false only in the local private config. Keep production HTTPS enforcement enabled on the real site. This update does not change your XAMPP TLS configuration.

In **SEO settings**, set the canonical website URL to the full local public URL while testing, or the full public live URL when deploying, including any subfolder. Your supplied base_url ended at /aes/d1; the correct public entry in this layout includes /public. Setting the canonical URL in admin overrides that fallback without editing individual links. Also update private base_url to the final live URL when deploying.

If automatic detection is unsuitable behind a proxy, add `'base_path' => '/aes/d1/public'` to private config.php; ordinary XAMPP does not need it.

## 4. Add the company's public social profiles

Open **Social accounts**. Enter the confirmed HTTPS Facebook, Instagram, LinkedIn, YouTube and X URLs. Leave unused fields blank and save. The footer displays only configured networks. No profile URL is invented or seeded.

## 5. Configure automatic Facebook and Instagram publishing

A developer must prepare an authorized Meta app and access tokens. This release uses Facebook Login for the Instagram account; the professional Instagram account must be linked to the relevant Facebook Page. Use the official requirements for the client account's login/access level and permissions:

- [Facebook Page posts](https://developers.facebook.com/documentation/pages-api/posts)
- [Instagram publishing](https://developers.facebook.com/documentation/instagram-platform/content-publishing)

Enter the numeric account IDs and authorized tokens in **Social accounts**, then enable each target. The token field never displays a saved token; leave it blank to keep the existing one. Changing the numeric account needs a new token. Encrypted tokens depend on your existing app_key; preserve that key and rotate/reconfigure tokens through the authorized Meta workflow when needed.

Set a real, publicly reachable HTTPS URL in SEO settings. Meta cannot fetch localhost photos. For Instagram, use an image ratio between 4:5 and 1.91:1; title plus body must fit within 2,200 characters. This implementation supports a single photo per social post, and Facebook text-only posts. Other networks are public profile links, not publishing integrations.

Ask the host to run the worker every minute. On this XAMPP computer, Windows Task Scheduler can run:

- Program: `C:\xampp\php\php.exe`
- Arguments: `"C:\xampp\htdocs\aes\d1\bin\publish-social.php"`
- Start in: `C:\xampp\htdocs\aes\d1`

This is setup guidance; no scheduled task has been installed by this update. You can run the same command once manually to process queued jobs after credentials are configured. The worker has a database lock, so overlapping executions do not process the queue concurrently.

Save a published post in **Posts & social**. Open **Social accounts**, choose that post and Facebook, and queue it. Repeat for Instagram if wanted. The scheduled worker sends it; check each result in Publishing history and verify the first real posts on the actual accounts.

If a result says needs review, check the account before retrying. Never assume a timeout means nothing was published. Do not automatically retry uncertain jobs. Already sent social posts must be edited/deleted on the network; website edits do not modify them. Website deletion preserves publishing history, and cancels jobs that have not started.

## 6. Use the built-in guide and verify the installation

Open **Help & docs**. Search for a topic, open its steps, inspect Installation checks, or use Print / save this guide as PDF. ADMIN_GUIDE.md is an offline handbook.

Set PHP upload_max_filesize=5M and post_max_size=64M for multi-photo batches; adjust the web-server size limit accordingly. Test login/logout, one gallery, one enquiry, SEO save and social links. Before production, verify real email delivery, both first social publications and backup restoration.

The parent .htaccess denies private files/archives while public/.htaccess grants the public application. Apache must honor these files; otherwise use a document root pointing only to public/. Test /aes/d1/config.php and /aes/d1/storage/ directly: they must be denied, while /aes/d1/public/ must load. For Nginx or proxies, configure equivalent rules. The isolated Apache validation was blocked by filesystem restrictions here; PHP routing and form tests passed on both root and nested paths.

Apache's public/.htaccess also maps robots.txt and sitemap.xml to their PHP endpoints. The development PHP server ignores .htaccess; use robots.php/sitemap.php for local inspection or the bundled test router. Confirm aliases on your real host.
