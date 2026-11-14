# AWS EC2 deployment with Docker Compose

This is a prepared deployment setup, not an already deployed EC2 website. Docker Engine is unavailable in the development environment, so the image build, Compose startup, TLS provisioning and AWS runtime have **not** been executed. Run the verification steps below before putting it in service.

## Architecture

- **proxy:** Caddy terminates HTTPS and forwards to private Apache.
- **app:** PHP 8.4/Apache serves only public/. Configuration, commands, storage and secrets sit outside the document root.
- **db:** MySQL 8.4 with a persistent named volume, no published database port and an internal database network.
- **init:** one-shot schema/default-content migration, using a separate root secret. The web app/worker use a CRUD-only MySQL account.
- **worker:** unprivileged process for social publishing every minute and maintenance daily. No second cron is required for these in Docker.
- Persistent volumes retain MySQL, uploaded images/sessions and Caddy certificate state. Source code is baked into the application image; do not edit running containers.

This is a single-host setup, not high availability. Keep off-instance backups and monitor disk, memory, logs and container state.

## 1. Prepare EC2 and DNS

Use an Ubuntu 24.04 LTS EC2 host and a domain you control. Point the domain's A record at the instance's stable public address (for example an Elastic IP). If an AAAA record exists, it must route correctly too.

In the instance security group allow:

| Port | Source | Purpose |
| --- | --- | --- |
| TCP 22 | Your trusted public IP /32 only, or use Session Manager instead | Administration |
| TCP 80 | Internet | HTTP redirect and certificate validation |
| TCP 443 | Internet | Website HTTPS |

Do not open MySQL/3306, PHP/Apache container ports or development port 8080 to the Internet. No AWS credentials are needed inside the app containers. Use appropriate EBS encryption and backup controls for your environment.

Reference: [AWS security-group examples](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/security-group-rules-reference.html).

## 2. Install Docker Engine and Compose plugin

Follow the current official [Ubuntu installation instructions](https://docs.docker.com/engine/install/ubuntu/), using Docker's apt repository and the docker-compose-plugin package. Verify:

```bash
sudo systemctl enable --now docker
sudo docker version
sudo docker compose version
```

Avoid publishing a container port just because a host firewall appears to block it; Docker's published-port behavior must also be considered. This stack publishes only Caddy's 80/443 in production.

## 3. Copy source and configure the domain

Extract the delivered ZIP into a private project directory owned by your deployment user, for example ~/aes. Do not copy the old XAMPP config.php, sessions or test configuration into the image. They are excluded by .dockerignore.

```bash
cd ~/aes
cp .env.example .env
nano .env
```

Set both values to the real domain:

```dotenv
SITE_DOMAIN=solar.your-domain.in
APP_URL=https://solar.your-domain.in
CONTACT_EMAIL=your-confirmed-business-mailbox@your-domain.in
META_API_VERSION=v27.0
```

The sample domain/mailbox above is illustrative. Use the client's confirmed values. SITE_DOMAIN is a hostname without https:// or a path. APP_URL is the full public origin. Docker serves the site at /; XAMPP subfolder support continues to work outside Docker.

## 4. Generate secrets once

With PHP available on the host:

```bash
php docker/make-secrets.php
```

Or generate using a temporary official PHP container without installing host PHP:

```bash
sudo docker run --rm --user "$(id -u):$(id -g)" \
  -v "$PWD:/workspace" -w /workspace php:8.4-cli \
  php docker/make-secrets.php
```

The helper generates independent database, root-database and application-key secrets, and prints no values. Existing files are preserved. Keep docker/secrets private and backed up; never regenerate/replace them when reusing an existing database volume. Changing app_key also invalidates encrypted social tokens.

On Linux the secret directory is owner-only (0700), while secret files are 0444 so the unprivileged container process can read the mounted files. The private parent directory prevents other host users from traversing to them. Compose's file-backed secrets are runtime bind mounts, not an encrypted secret vault. Protect the host/EBS/backups accordingly; consider a managed secret system for stricter operational requirements. Do not commit .env or docker/secrets, and do not put secrets in Docker build arguments.

## 5. Validate, build and start

```bash
sudo docker compose --profile production config --quiet
sudo docker compose --profile production up -d --build
sudo docker compose --profile production ps -a
sudo docker compose --profile production logs --tail=100 init app proxy worker
```

Expected state: db and app healthy, init exited with code 0, worker/proxy running. Startup waits on database readiness and successful schema initialization. Caddy needs correct DNS and inbound 80/443 to obtain a certificate.

Create the real administrator interactively:

```bash
sudo docker compose exec app php bin/admin.php owner@your-business-domain.in
```

Replace that email. The command reads a unique password from terminal input; it is not placed in the shell command. No default administrator password is bundled.

Open https://your-real-domain/admin.php. Verify that HTTP redirects to HTTPS and private URLs such as /config.php and /storage/ are inaccessible. View Help & docs → Installation checks. Set/verify the live canonical URL in SEO settings; a value imported from localhost overrides APP_URL until you change or clear it.

Email notification remains disabled in the Docker config. Enquiries save in MySQL and the confirmed public email link works once entered in Page content. Reliable outbound notifications need a separately configured/tested mail transport; changing CONTACT_EMAIL alone does not prove or enable delivery.

## 6. Optional demo content

For a private/staging preview:

```bash
sudo docker compose exec -u www-data app php bin/demo.php seed
sudo docker compose exec -u www-data app php bin/demo.php status
```

This creates three projects/nine illustrations, five posts, five fictional reviews and eight enquiries, with published and draft examples. All are marked DEMO; enquiries have reserved dummy contact details. No messages are sent. Demo posts cannot be queued to Meta.

Remove before client launch:

```bash
sudo docker compose exec -u www-data app php bin/demo.php purge
```

This also removes edited demo records. Genuine untagged records and images still referenced by genuine records are preserved. No social accounts, social profile URLs, real API credentials or fake administrator accounts are seeded.

## 7. Local Docker preview without a domain

This uses loopback-only HTTP and disables production HTTPS enforcement only in the local override. Do not enable the production profile or use this override on a public EC2 deployment.

```bash
docker compose -f compose.yaml -f compose.local.yaml config --quiet
docker compose -f compose.yaml -f compose.local.yaml up -d --build
docker compose exec app php bin/admin.php owner@example.com
docker compose exec -u www-data app php bin/demo.php seed
```

Open http://localhost:8080. The app and worker are configured for local HTTP; Instagram photo publishing remains blocked because Meta cannot fetch localhost images. Do not mix the local override and production profile in one running project.

## 8. Before launch

- Purge demo content and confirm the DEMONSTRATION CONTENT notice disappears.
- Confirm actual company details, contact information, approved photos and genuine review permissions.
- Confirm healthy containers, HTTPS and blocked private URLs.
- Submit a real test enquiry and verify the admin inbox. Test notification delivery only after mail transport is configured.
- Connect authorized Meta accounts if needed, inspect worker logs/results and verify the first real publication on each network. Local simulation is not proof of live Meta compatibility.
- Verify uploads persist after container recreation. Test a backup restoration on a separate staging deployment.
- Keep the host, image versions and dependencies patched. Tags are chosen release families, not immutable digests; record/pin tested image digests for reproducible production releases.

## Backups and updates

Store backups in a private directory and copy encrypted backups off the instance. For a consistent upload/database snapshot, pause app and worker while taking both. Keep the secret files with the corresponding backup; without app_key stored social tokens cannot be decrypted.

Example backup commands from the project directory on the Linux host:

```bash
mkdir -p backups
chmod 700 backups
sudo docker compose --profile production stop app worker
sudo docker compose exec -T db sh -c \
  'MYSQL_PWD="$(cat /run/secrets/db_root_password)" mysqldump -u root --single-transaction --no-tablespaces aanshu' \
  > backups/database.sql
sudo docker compose run --rm -T --no-deps app \
  tar -C /var/www/html/storage -czf - photos > backups/photos.tar.gz
cp -a docker/secrets backups/secrets
sudo docker compose --profile production up -d
```

Check backup sizes and command exit codes; a file existing is not proof that its contents restore successfully. Protect/encrypt the backup before transferring it. Source config and .env must also be retained privately. The backup container runs no social job.

For source upgrades, first back up, stop traffic/worker, replace the source, rebuild and run migrations explicitly before restarting:

```bash
sudo docker compose --profile production stop proxy app worker
sudo docker compose build
sudo docker compose run --rm init
sudo docker compose --profile production up -d
```

`docker compose down` preserves named volumes. `down -v` deletes database, uploads and certificate volumes: do not use it as a routine restart/update command. Importing existing XAMPP/MariaDB data into MySQL 8.4 is a separate migration that requires a reviewed dump, photo copy and restore test; this setup does not automatically import the local client's database.

## Troubleshooting

- init failure: inspect init/db logs; verify secret files match the database volume. Do not delete volumes or regenerate passwords to hide the error.
- app unhealthy: inspect app logs, storage ownership and database connectivity. Normal runtime does not need schema-creation privileges.
- proxy certificate failure: confirm DNS, A/AAAA records, security group 80/443 and that another process is not already using those ports.
- token files not readable: confirm docker/secrets is 0700 and its text files are 0444 on Linux; Compose file-bind mounts do not reliably remap uid/gid permissions.
- uploads failing: PHP and Apache allow a 64 MB total request, with 5 MB / 20 megapixels per image. Check proxy/body limits and available storage too.
- source changes not appearing: rebuild/recreate the image. Production OPcache does not poll edited files.

Reference: [Compose startup conditions](https://docs.docker.com/compose/how-tos/startup-order), [official PHP image](https://hub.docker.com/_/php), [official MySQL image](https://hub.docker.com/_/mysql), [Caddy reverse proxy](https://caddyserver.com/docs/caddyfile/directives/reverse_proxy).
