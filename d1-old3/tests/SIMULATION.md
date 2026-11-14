# Local Meta provider simulation

The application under test is the real PHP/MySQL website and CLI publisher. Only Meta's external HTTP responses are simulated.

## Contract sources and scope

- https://developers.facebook.com/documentation/pages-api/posts
- https://developers.facebook.com/documentation/instagram-platform/content-publishing

The fixture models POST /v27.0/<page>/feed, POST /v27.0/<page>/photos, POST /v27.0/<ig>/media, GET /v27.0/<container>?fields=status_code, and POST /v27.0/<ig>/media_publish. Unmodeled routes return 501. It is a narrow implementation-owned stand-in, not Meta's official mock and not a complete emulator.

Scenario-only tokens are test_success, test_denied (400/code 190), test_uncertain (503 on a write) and test_processing (Instagram IN_PROGRESS). They are disposable fixtures, not real credentials. Receipts record method/path/posted field names and never Authorization headers or tokens. No provider traffic reaches real Meta: config explicitly uses a guarded loopback override.

## Running

Use only a disposable database named aanshu_test with production=false. The existing integration suite expects the test-only administrator test@example.com / LocalVerificationOnly!2026, which is not seeded by production setup. Start the web server and provider simulator in separate terminals from the project folder:

```powershell
php -d upload_max_filesize=5M -d post_max_size=64M -S 127.0.0.1:8086 tests/dev-router.php
php -S 127.0.0.1:8091 tests/meta-provider.php
```

In private test config only, add:

```php
'social_test_base' => 'http://127.0.0.1:8091',
'meta_api_version' => 'v27.0',
```

Then run:

```powershell
php tests/integration.php
php tests/subfolder-social.php
```

The custom development router serves the same source at / and /aes/d1/public/ so the prefix is exercised end to end. It is a development fixture; do not deploy it as a production router.

The subfolder suite saves/restores disposable SEO/profile/account rows and removes its own test posts, jobs, images and enquiries. Never run either suite against client data. The loopback override is refused in production or outside the explicitly named disposable database. Do not copy test configuration to the real installation.

## Established in the run

71 baseline checks and 48 subfolder/social checks passed. The latter demonstrated actual HTTP serialization, provider response handling, queue state changes, encrypted-token persistence, per-installation URL/cookie behavior, no resending of sent/uncertain jobs, container reuse after deferred processing and explicit recovery after rejection/uncertainty.

A simulated 503 tests the conservative uncertain-write path. It does not prove whether a real Meta write would commit before failing. A sent result from this fixture proves local behavior against the fixture only, not live publishing, remote image fetch, app approval or permission correctness. Real Meta account verification remains required.

Postman CLI/owner-mock discovery was not available in this session; the executable installed under WindowsApps is the desktop shim. Orbit discovery was attempted and blocked by network permissions. Official primary documentation supplied the request contract.
