# Verification performed

Verified locally on 14 November 2026 with PHP 8.3.32 and isolated MariaDB 10.4.32 on loopback port 3307. Production hosting has not been tested or deployed.

- All application PHP files passed `php -l`.
- Both JavaScript files passed Node syntax checks.
- All seven public page routes and the admin sign-in route returned HTTP 200. Unknown pages returned 404.
- Configuration, SQL and storage URLs returned 404 through the development router.
- Database integration checks passed: sign-in, page editing, project upload, review publication, website post publication and editing, enquiry submission/private inbox, project deletion and logout.
- Negative checks passed: unauthenticated modification was rejected, invalid CSRF returned 403, stored script markup was escaped in public output, PHP disguised as an image was rejected, and enquiries were inaccessible after logout.
- Valid uploads received random filenames and were re-encoded as JPEG.
- Browser review confirmed the homepage image and layout, expandable navigation, contact page and a 390-pixel viewport without horizontal document overflow. This does not establish behavior on a physical phone.

The temporary integration records and test administrator were removed. There are no default administrator credentials. The local database is retained under ignored storage/testdb for development, with a dedicated application database user and generated password in ignored config.local.php. Its configuration is local-only and must be replaced for production.

Not verified: Apache/Nginx production rules, a real domain or TLS, email delivery, social API publishing, real client photographs/reviews, production backup/restore or independent penetration testing. Email delivery and automatic social publishing are not implemented. See README.md for exact scope and launch requirements.
