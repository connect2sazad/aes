# Verification record — 14 November 2026

## Executed

- PHP syntax validation: application, CLI and test PHP files passed.
- 40 integration checks passed using PHP 8.3.32, a real isolated MariaDB 10.4.32 database on loopback port 3308, and HTTP on port 8086. No SQLite replacement or mocked persistence.
- Browser inspection: public page at desktop and 390 px mobile sizes; mobile menu opening and anchor navigation; real admin login, dashboard, content editor and logout. Screenshots delivered separately.
- Test fixtures removed; local test credentials and sessions excluded from the deliverable ZIP. Temporary local services stopped after verification.

## Security behavior exercised

Security headers and HttpOnly/SameSite cookies; private document-root boundaries; login and unauthorized writes; CSRF enforcement and rotation; bound-query injection attempt; main content updates and unsafe contact URL rejection; draft post and media isolation; stored script escaping in public posts and private enquiries; real multipart image decoding/re-encoding and random filenames; disguised executable and path traversal rejection; review publication; persistent enquiries; server-side consent/email/honeypot validation; enquiry and login rate limits; enquiry status/deletion; post deletion and logout.

## Limits

No public deployment, independent penetration test, production HTTPS/proxy/cookie test, live email delivery, WhatsApp message delivery, actual Meta publishing, real phone-device test, hosting backup restoration or verification of client company/vendor registration. Browser responsive emulation is not a physical-phone test. Form email notifications remain disabled without a configured business mailbox and mail transport. Social publishing is intentionally not connected. No zero-vulnerability guarantee is made.

## Issue found and fixed

The local PHP default session directory was not writable in this execution environment. Sessions now use the private project storage directory and fail closed if they cannot start. Integration checks passed after this fix.

The application has no third-party runtime packages. This does not remove the need to patch PHP, MySQL, the web server and operating system.
