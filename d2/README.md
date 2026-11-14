# Aanshu Engineering Solution

PHP 8.2+ / MySQL 8 or MariaDB website. No build or npm installation required.

## This workstation

A separate local MariaDB development database has already been initialized in ignored `storage/testdb`, using port 3307. Local-only database configuration is in ignored `config.local.php`. Run `./start-local.ps1` from PowerShell to start the local database and PHP preview, then run `C:\xampp\php\php.exe create-admin.php` in a second terminal to create your administrator. Temporary test accounts were removed. Set a business email in `config.local.php` to enable the email contact link. Do not upload this development database/configuration to production. See `VERIFICATION.md` for checks and limitations.

## Set up

1. Use PHP with PDO MySQL, GD, Fileinfo, OpenSSL and sessions enabled.
2. Import `schema.sql` into MySQL. Create a dedicated database user with SELECT, INSERT, UPDATE and DELETE permissions on `aanshu` only. Do not run the website using MySQL root.
3. Copy `config.example.php` to `config.local.php` and set database credentials and the real business email. The email address appears on the contact page once configured.
4. Run `C:\xampp\php\php.exe create-admin.php` in a terminal. There are no default admin credentials. Use a unique password of at least 14 characters. Terminal password input is visible.
5. Ensure `storage` and `uploads` are writable by PHP. Deny access to storage, configuration, SQL and CLI files at the web server. Apache `.htaccess` rules are included; enable AllowOverride. Configure equivalent restrictions when using Nginx.
6. For a local preview only, set `secure_cookies` to false and run `C:\xampp\php\php.exe -S 127.0.0.1:8080 router.php`. Visit http://127.0.0.1:8080. Never expose the PHP development server publicly.
7. Production requires HTTPS and `secure_cookies => true`. The application uses secure/HttpOnly/SameSite cookies, CSRF tokens, prepared queries, escaped output, content security policy, upload re-encoding and login/enquiry rate limits.

Admin panel: `/admin.php`. Update homepage/about/service copy, publish installation photos, add consented reviews, publish website posts, and view/delete enquiries. A session expires after 30 minutes without activity. Project and review content is empty until the administrator publishes real records.

## Contact and social scope

- WhatsApp and click-to-call use +91 91149 59111, supplied in the brief. Confirm it is the public business number before launch.
- Contact email is configurable; email opens the visitor's mail application. Form submissions are saved to the admin inbox. Automatic email notifications are not implemented; SMTP credentials/provider selection are required for that addition.
- Posts publish to the website. Admins can copy captions or share the updates page to Facebook. Automatic Instagram/Facebook posting is not implemented or claimed. It requires client-owned eligible Meta accounts, an approved application, permissions, token lifecycle handling, media hosting and delivery/retry jobs. Do not put tokens into browser code.
- Government scheme wording is based on the brief. Verify any empanelment/authorization and scheme terms before publishing. No affiliation, subsidy amount or guaranteed approval is asserted.
- Homepage solar image is an external illustrative Unsplash photograph, not a client project. Replace it with licensed client photography when available.

## Deployment and ongoing security

Deploy this PHP/MySQL application on PHP hosting, a VPS or a managed PHP service. Sites hosting supports static files/Cloudflare Workers rather than PHP/MySQL, so this project is not deployed there. Keep `config.local.php`, database dumps, logs and backups out of source control and web-accessible folders. Ensure the web server actually enforces the access restrictions, not merely that the files exist.

Before launch: verify HTTPS, contact details, all admin and enquiry flows against the production database, restore a backup, review legal/privacy wording and agree a retention policy. Add CAPTCHA/WAF controls if abuse warrants it. Keep PHP, MySQL and the web server patched, monitor logs, rotate credentials, restrict administrative access at the network/proxy where possible, and arrange an independent security review. No software can honestly be guaranteed free of vulnerabilities.

The file-based rate limiter is intended for one server. Configure trusted client IPs and shared limiting if deploying behind proxies or across instances. Delete old limiter records through a controlled retention job. Dashboard listings are bounded to the latest 100 records. Company identity and contact details in templates require a code/config change; editable page copy is deliberately plain text, not arbitrary HTML.
