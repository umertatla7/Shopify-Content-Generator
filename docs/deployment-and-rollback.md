# Deployment And Rollback

The local repository and GitHub are the only sources of application code. Never edit PHP, Vue, routes, migrations, or configuration files directly on the live server. Production-only secrets remain in the live `.env` and are never committed.

## Before deployment

1. Complete the auditor review and obtain a go decision.
2. Run locally:

```bash
php artisan test
npm run build
composer audit
npm audit --audit-level=low
git diff --check
```

3. Commit and push the reviewed code to `main`.
4. Record the current live commit: `git rev-parse HEAD`.
5. Create and verify a current MySQL backup.
6. Review `php artisan app:queue-report`; do not deploy into an unknown backlog.
7. Confirm the live working tree is clean. Stop if `git status --short` returns anything.

## Database backup

Use a MySQL option file outside the web root with mode `0600`, then create a timestamped dump:

```bash
mysqldump --defaults-extra-file=/home/u160003797/.my.cnf --single-transaction --routines --triggers DATABASE_NAME | gzip > /home/u160003797/backups/growshophigh-$(date +%Y%m%d-%H%M%S).sql.gz
```

Copy the encrypted backup to another system. Test restoration into a separate database before launch. A backup that has never been restored is not verified.

## Deploy from GitHub

```bash
cd /home/u160003797/domains/growshophigh.com/app
php artisan down --retry=60
git fetch origin main
git pull --ff-only origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
rsync -av --delete --exclude=storage public/ /home/u160003797/domains/growshophigh.com/public_html/app/
php artisan queue:restart
php artisan up
```

For Horizon, replace `queue:restart` with `horizon:terminate`. Never use `git stash` as a normal deployment step; a dirty live tree means code drift that must be investigated.

## Post-deployment checks

1. `curl -fsS https://app.growshophigh.com/up`
2. Check `/api/health/ready` with the health token.
3. Open the embedded app from two development stores in one browser and confirm each displays its own store.
4. Verify onboarding, catalog sync, one AI draft, one Shopify publish, support email, and billing test flow.
5. Send test webhook deliveries for all compliance topics and `app/uninstalled`.
6. Watch application, worker, scheduler, and webhook logs for at least 15 minutes.

## Rollback

Code rollback:

```bash
cd /home/u160003797/domains/growshophigh.com/app
php artisan down --retry=60
git fetch origin
git switch --detach PREVIOUS_VERIFIED_COMMIT
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
rsync -av --delete --exclude=storage public/ /home/u160003797/domains/growshophigh.com/public_html/app/
php artisan queue:restart
php artisan up
```

Do not automatically run `migrate:rollback`. If a migration changed or removed production data, restore the verified database backup or deploy a forward repair migration. Record the incident and reconcile queued jobs before restarting workers.

After recovery, return the server to `main` only through a reviewed fix. A detached rollback is temporary and must not become a second source of code.
