# Production Operations

This document defines the minimum runtime required before GrowShopHigh accepts public merchants.

## Required services

- MySQL for application data.
- Redis plus Horizon is preferred for queues and cache. A supervised database queue worker is acceptable for an initial controlled pilot.
- One continuously supervised queue worker consuming `shopify,ai,default`.
- Laravel's scheduler invoked once per minute.
- A real transactional email provider.
- External uptime monitoring for `/up` and `/api/health/ready`.
- Automated encrypted MySQL backups stored outside the web root and on a second system.

Do not advertise the service as production-ready while workers or the scheduler depend on someone keeping an SSH session open.

## Production environment

Use these values as a guide; credentials remain only in the production `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.growshophigh.com
LOG_CHANNEL=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=14

QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=300
QUEUE_FAILED_DRIVER=database-uuids

MAIL_MAILER=smtp
MAIL_HOST=provider-host
MAIL_PORT=587
MAIL_USERNAME=provider-user
MAIL_PASSWORD=provider-secret
MAIL_SCHEME=tls
MAIL_FROM_ADDRESS=support@growshophigh.com
MAIL_FROM_NAME=GrowShopHigh

OPERATIONS_ALERT_EMAIL=admin@growshophigh.com
OPERATIONS_HEALTH_TOKEN=a-long-random-secret
OPERATIONS_REQUIRE_SCHEDULER=true
OPERATIONS_REQUIRE_QUEUE_WORKER=true
OPERATIONS_REQUIRE_REAL_MAIL=true
OPERATIONS_HEARTBEAT_TTL_SECONDS=180
OPERATIONS_MAX_QUEUE_AGE_SECONDS=600
OPERATIONS_MAX_FAILED_JOBS=0
```

Keep `SHOPIFY_BILLING_TEST_MODE=true` until public distribution is enabled and all test-mode billing scenarios pass. Change it only as a deliberate launch action.

## Worker

Redis/Horizon is preferred:

```bash
php artisan horizon
```

For the database driver, supervise this command continuously:

```bash
php artisan queue:work database --queue=shopify,ai,default --sleep=3 --timeout=300 --tries=3 --max-jobs=500
```

The supervisor must restart the process when it exits. After deployment run `php artisan queue:restart`; for Horizon run `php artisan horizon:terminate`.

AI jobs intentionally declare one attempt because replaying them can duplicate AI cost or content. Shopify sync and publishing jobs declare three attempts with backoff.

## Scheduler

Install one cron entry:

```cron
* * * * * cd /home/u160003797/domains/growshophigh.com/app && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler records a heartbeat, dispatches due scheduled blogs without overlap, recovers stale publishing claims, and prunes operational logs.

## Health and alerts

- `/up` verifies the Laravel process responds.
- `/api/health/ready` verifies database access, scheduler and worker heartbeats, queue age, failed jobs, and real mail configuration.
- Send `X-Health-Token: <OPERATIONS_HEALTH_TOKEN>` to receive check details. Public requests receive only overall status.
- A final queue failure is logged at `critical` and emailed to `OPERATIONS_ALERT_EMAIL`.

Configure an external monitor to alert on any non-200 response. Also configure storage, database capacity, TLS expiry, and host-level CPU/RAM alerts in the hosting dashboard.

## Mail verification

Before launch, verify delivery to real inboxes for:

- password reset;
- new Shopify installation notification;
- support ticket submission;
- support reply;
- operational job failure.

Confirm SPF, DKIM, and DMARC for the sending domain. The `log` mailer is never acceptable in production.

## Old queue backlog

The live audit found 20 jobs created between June 29 and July 4. Do not start a worker until these are reviewed.

Run the read-only report:

```bash
php artisan app:queue-report --limit=100
```

For each job, confirm the store still exists and whether the action is still wanted. Record the decision to retain, recreate, or remove each job. Database deletion or `queue:clear` requires an approved backup and explicit owner authorization. Never retry the entire backlog blindly.
