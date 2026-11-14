# Verification — demo content, footer and Docker/EC2 update

## Checks completed in this update

- 71 existing real HTTP/MariaDB regression checks passed.
- 48 subfolder/social checks passed, with actual worker HTTP calls directed to a local simulated Meta provider. These do not prove live Meta authorization or publication.
- 30 new demo/credit checks passed: expected counts, company-content preservation, repeated seeding without duplicate IDs/images, nine valid generated JPEGs, visible fictional-review labels, reserved dummy contacts, social-queue rejection, public demo notice, footer credit/link, tagged cleanup, preservation of genuine controls and protection of still-referenced images.
- Total: **149 PHP/HTTP/database checks passed**. Result files are in tests/.
- PHP syntax checks passed for all PHP files, including Docker config helpers and demo commands.
- Shell syntax checks passed for docker/entrypoint.sh and docker/worker.sh using bash -n.
- Docker runtime config was exercised locally with temporary test-only secret files: file-based credential reading and production/local boolean selection worked; values were not printed.
- Browser checks confirmed labelled demo projects/reviews/posts, the desktop footer credit and mobile content layout. Screenshots were saved separately.

An existing regression assertion expected an older wording for an invalid canonical URL. The implementation still rejected the URL; the assertion was updated to the current message and the complete 71-check suite passed.

## Docker/EC2 verification boundary

Docker Engine/Compose is not installed in this environment. No Docker image was built or pulled, docker compose config/up was not run, and no EC2 instance, live DNS, TLS certificate, container networking/volume permission path or MySQL 8.4 container was exercised. Compose/Docker configuration was reviewed statically; PHP config and shell syntax were checked separately. This is not a container-startup or AWS deployment success claim.

The prepared stack uses an official PHP 8.4 Apache image, MySQL 8.4, Caddy, persistent volumes, separate migration credentials, a CRUD-only runtime database user and a background worker. Follow DEPLOY_EC2.md and complete its runtime checks before launch.

## Existing limitations

The tests used an isolated local MariaDB 10.4.32 database named aanshu_test and PHP 8.3.32. No existing client database or XAMPP installation was overwritten. Real Meta credentials/accounts, email delivery, physical phones, production backup restoration and independent penetration testing remain unverified. The earlier standalone Apache validation was blocked by filesystem restrictions; existing .htaccess rules need checking on the real host. No zero-vulnerability guarantee is made.

The package excludes private config.php, .env, secret files, sessions, client photos, generated demo photos and runtime receipt logs. Demo illustrations are generated when the explicit seed command is run. No messages are sent by seeding. Purge deletes only tagged demo records (including edited samples) and unused demo-owned images.
