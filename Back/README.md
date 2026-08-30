<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## Stripe Webhooks

The Stripe webhook endpoint is:

```text
POST /api/v1/stripe/webhook
```

Set the matching signing secret for each environment:

```env
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxx
```

For local testing, use the Stripe CLI:

```bash
stripe login
stripe listen --forward-to http://127.0.0.1:8000/api/v1/stripe/webhook
```

Copy the `whsec_...` signing secret printed by `stripe listen` into the local `.env`, then run:

```bash
php artisan optimize:clear
```

The Stripe CLI signing secret is different from a Dashboard webhook endpoint secret. Each environment must use the signing secret that belongs to that exact endpoint. Localhost cannot be registered directly as a public Dashboard webhook endpoint.

For production later, create a Stripe Dashboard webhook endpoint pointing to:

```text
https://api.narlit.com/api/v1/stripe/webhook
```

Select only the events the backend handles:

- `checkout.session.completed`
- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `invoice.payment_succeeded`
- `invoice.payment_failed`
- `charge.refunded`
- `account.updated`

Store the production endpoint signing secret in the production environment as `STRIPE_WEBHOOK_SECRET`; never commit a real `whsec_...` value. After changing production environment configuration, run `php artisan optimize:clear` and rebuild the config cache. NarLit payout execution remains admin-approved and transfer-based; do not add Stripe `payout.*` events unless that payout architecture changes.

## Admin Idempotency

Sensitive admin financial operations require an idempotency header:

```http
Idempotency-Key: <uuid>
```

The frontend should generate a new UUID with `crypto.randomUUID()` for each intentional refund, subscription cancellation, payout generation, or payout execution. If the same logical request is retried after a timeout or network failure, reuse the same UUID. A new intentional operation must use a new UUID.

Completed idempotency records expire after `IDEMPOTENCY_TTL_HOURS`, defaulting to 24 hours, and can be cleaned up with:

```bash
php artisan idempotency:cleanup
```

## Production Security Checklist

This project can remain in local development mode while production safeguards are implemented. Only use the following settings in a real production deployment environment:

```env
APP_ENV=production
APP_DEBUG=false
STRIPE_FAKE_CHECKOUT=false
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
```

Production must run behind HTTPS. HSTS is sent only when the Laravel app is running in production and the incoming request is HTTPS.

Configure real AWS, Stripe, database, mail, and webhook secrets through environment variables managed by the deployment platform. Never commit `.env`, production secrets, `APP_KEY`, AWS credentials, Stripe secrets, webhook secrets, or database passwords to repository files.

After production environment variables change, rebuild Laravel's configuration cache:

```bash
php artisan config:cache
```

Expired Sanctum tokens can be cleaned up with:

```bash
php artisan sanctum:cleanup-expired-tokens
```

## SSL and HTTPS Readiness

Local development may continue using HTTP. Production should terminate TLS at Nginx or a trusted load balancer and redirect all HTTP port 80 traffic to HTTPS port 443 before requests reach Laravel.

Future production environment values should include:

```env
APP_URL=https://api.narlit.com
CORS_ALLOWED_ORIGINS=https://narlit.com,https://www.narlit.com
SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=127.0.0.1
```

Set `TRUSTED_PROXIES` to the actual Nginx, load balancer, or private proxy IP/CIDR used in production. Do not use `*` unless the deployment network explicitly requires trusting the immediate caller and that risk has been reviewed. The proxy must pass `X-Forwarded-Proto: https` so Laravel can detect secure requests behind TLS termination.

The production web root must point to Laravel's `public/` directory only. Never expose `.env`, private storage files, `vendor/`, `database/`, `config/`, or `app/` through Nginx. Organization PDF documents must remain on private storage and must not be served through public storage links.

Future Certbot setup, performed on the Linux server only after DNS points at the server:

```bash
sudo apt update
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d api.narlit.com
```

Add `-d narlit.com -d www.narlit.com` only if the same Nginx server also terminates TLS for the frontend domains. Otherwise, configure the frontend certificates on the frontend hosting layer.

Future server validation commands:

```bash
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl status certbot.timer
sudo certbot renew --dry-run
```

Certbot normally installs automated renewal. Operators should verify renewal status and reload Nginx safely after certificate or site configuration changes.

## Queue Workers

Production should run Laravel queues with `QUEUE_CONNECTION=database` after the production database has been migrated. The application includes migrations for `jobs`, `job_batches`, and `failed_jobs`; local development settings do not need to change.

Current queue usage:

- OTP and password-reset email notifications implement `ShouldQueue` and use the `mail` queue.
- Payout execution dispatches `ExecutePayoutBatchJob` on the default queue after admin authorization, payout state validation, and idempotency handling.
- Phone MFA SMS delivery is synchronous.

Future Supervisor template:

```ini
[program:narlit-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/narlit-back/artisan queue:work database --queue=default,mail --sleep=3 --tries=3 --timeout=80 --max-time=3600 --max-jobs=1000
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/narlit/queue.log
stopwaitsecs=3600
```

Server setup later:

```bash
sudo mkdir -p /var/log/narlit
sudo chown www-data:www-data /var/log/narlit
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start narlit-queue:*
sudo supervisorctl status narlit-queue:*
php artisan queue:restart
php artisan queue:failed
```

Workers should run as `www-data` or the actual application service user, not root. Restart workers with `php artisan queue:restart` after every deployment so they reload code and cached configuration. Monitor failed jobs and queue backlog; use `php artisan queue:retry <id>` or `php artisan queue:forget <id>` only after reviewing the failure. Keep the worker timeout lower than `DB_QUEUE_RETRY_AFTER`; with the template above, configure production `DB_QUEUE_RETRY_AFTER` greater than 80 seconds if overriding defaults.

## Scheduler

Laravel 12 scheduling is defined in `routes/console.php`. The current scheduled tasks are maintenance-only:

- `idempotency:cleanup` hourly, with overlap protection.
- `sanctum:cleanup-expired-tokens` daily, with overlap protection.

Payout batch generation and payout execution are not scheduled. Payout execution remains admin-triggered and approval-gated through the admin API, then processed by the queue worker prepared above. Do not add automatic payout execution unless the business process explicitly approves it.

Future production cron entry, installed for the application service user after deployment:

```cron
* * * * * cd /var/www/narlit-back && php artisan schedule:run >> /dev/null 2>&1
```

Use the real deployment path and service user for the server. The cron user must be able to read the app, execute PHP, and write to Laravel `storage` and `bootstrap/cache`. Do not run scheduler cron as root unless the deployment environment explicitly requires it. Scheduler failures should be monitored through Laravel logs and normal server log monitoring.

## Database Backups and Restore Readiness

NarLit production is expected to use PostgreSQL. Database backups should run at the server/database layer so recovery remains possible even if the Laravel application is unhealthy. Do not schedule database dumps inside Laravel unless the operations architecture deliberately changes.

Application-code readiness:

- Laravel has a `pgsql` database connection configured.
- Local development examples may still use SQLite.
- No backup job is registered in Laravel Scheduler.
- S3-compatible filesystem configuration exists for application storage, but database backup credentials should be separate least-privilege credentials and must not reuse SES credentials automatically.
- Generated backup artifacts are ignored by Git.

Future PostgreSQL backup template:

```bash
deployment/backups/narlit-postgres-backup.sh
```

The template uses `pg_dump --format=custom`, writes to a hidden temporary file first, verifies readability with `pg_restore --list`, applies restrictive permissions, then renames to the final timestamped `.dump` file. It uses `flock` to prevent overlapping runs and deletes only files matching `narlit-postgres-*.dump` inside the configured backup directory after the retention period.

Do not place database passwords on command lines or in Git. In production, use a secure server-side mechanism such as a dedicated `.pgpass` file owned by the backup user with mode `0600`, or an approved secret manager / service environment. Avoid connection URLs containing passwords because they can leak through process lists, shell history, or logs.

Future local server setup concept:

```bash
sudo mkdir -p /backups/narlit
sudo chown narlit-backup:narlit-backup /backups/narlit
sudo chmod 700 /backups/narlit
```

The backup directory must not be under Laravel `public/` and must not be served by Nginx. Backups contain user, subscription, payment, impact, payout, Stripe webhook/idempotency, admin audit, and organization data.

Initial local retention can be 30 days. Off-server backup storage is also required; same-server backups alone are not enough. Use a private S3-compatible bucket such as Amazon S3, Cloudflare R2, or Backblaze B2 with least-privilege write/list/read permissions only for the required backup path. Enable encrypted transport and server-side encryption. If client-side encryption is adopted, store encryption keys only in the approved production secret manager, never in the repository.

Future daily cron concept, after confirming production timezone and backup window:

```cron
0 3 * * * BACKUP_DIR=/backups/narlit DB_HOST=127.0.0.1 DB_PORT=5432 DB_NAME=narlit_production DB_USERNAME=narlit_backup /var/www/narlit-back/deployment/backups/narlit-postgres-backup.sh >> /var/log/narlit/db-backup.log 2>&1
```

An OS cron or systemd timer is preferred over Laravel Scheduler for database backups. Production monitoring should alert on backup command failure, remote upload failure, missing expected backup, unexpectedly small backup size, or stale last successful restore drill.

Restore drills must use an isolated staging/restore database, never the active development or production database:

```bash
createdb narlit_restore_drill
pg_restore --clean --if-exists --no-owner --no-acl --dbname=narlit_restore_drill /backups/narlit/narlit-postgres-YYYYMMDDTHHMMSSZ.dump
php artisan migrate:status
```

After restoring, verify required tables, migration state, representative table counts, login/read-only flows, and admin audit/payment/payout records. Keep queues, scheduler, Stripe webhooks, SES, payout execution, and external integrations disabled or isolated during restore testing. Do not point real Stripe webhooks at restore or staging environments unless they are deliberately configured with separate test credentials.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
