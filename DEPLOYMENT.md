# Mobile Arena deployment guide

This project runs on Laravel 12. The bundled catalog, stock, prices, product photos, payment choices, delivery fees, and warranty notes are demo data. Replace or approve them before a public launch.

## Hosting requirements

- PHP 8.2 or newer, Composer 2, and Laravel's required Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, Session, Tokenizer, and XML extensions. Enable `pdo_mysql` for MySQL/MariaDB or `pdo_sqlite` for local SQLite; ZIP helps Composer download packages. See the [Laravel 12 deployment requirements](https://laravel.com/docs/12.x/deployment).
- A web server with HTTPS and its document root set to this project's `public/` directory. The PHP CLI used by migrations and cron needs the same extensions as the web runtime.
- A dedicated MySQL/MariaDB database and account, backups, a cron service, and outbound HTTPS access to Storyblok's EU Content Delivery API at `api.storyblok.com`.
- The current layout uses the checked-in `public/assets/app.css` and `public/assets/app.js`, so Node is not needed by the running site. The optional Vite source workflow needs Node 20.19+ or 22.12+ at build time. This repository has no `package-lock.json`; `npm ci` is unavailable until a lockfile is added. See [Vite 7's Node requirements](https://vite.dev/blog/announcing-vite7).

## Local development

`.env.example` is a production-oriented template. Copy it to a private `.env`, then change these values before running locally:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=sqlite
SESSION_SECURE_COOKIE=false
MAIL_MAILER=log
STORYBLOK_ACCESS_TOKEN=
STORYBLOK_VERSION=draft
```

For SQLite, remove or comment out the copied `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` lines; Laravel then uses `database/database.sqlite`. Create that file if it does not exist. A preview token is optional; an empty Storyblok token uses the storefront fallback. For local MySQL/XAMPP, `.env.mysql.example` is a **local-only** starting point and its credentials must be replaced.

```powershell
Copy-Item .env.example .env
# Edit .env as described above, then:
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed  # Only on a new, empty local demo database
php artisan serve
```

The prebuilt public assets do not need an npm build for local preview. Run `php artisan schedule:work` in a separate terminal if testing reservation expiry. Never run `migrate:fresh` on a database whose records you need to keep.

## Environment variables

Copy `.env.example` into a **private** server-side `.env` or inject equivalent host secrets. Replace every `REPLACE_WITH_*` value and both `.example` domains. Do not commit the populated file or place it under `public/`.

| Group | Variables and production decision |
| --- | --- |
| Application | `APP_ENV=production`, `APP_DEBUG=false`, a stable generated `APP_KEY`, and the real HTTPS `APP_URL`. Set `APP_NAME`, locale, logging, and session settings for the host. Generate `APP_KEY` once for a new installation and preserve it across releases. |
| Database | `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` for a dedicated least-privilege account. Do not use the bundled SQLite demo database or SQL seed dump as production data. |
| Mail | `MAIL_MAILER` and the transport's `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, and `MAIL_FROM_ADDRESS`. The `log` mailer does not deliver password-reset email. |
| Runtime | `CACHE_STORE`, `SESSION_DRIVER`, `SESSION_SECURE_COOKIE`, `QUEUE_CONNECTION`, `FILESYSTEM_DISK`, `LOG_CHANNEL`, and `LOG_LEVEL`. The template uses file cache/sessions and a synchronous queue for a single host; change these with matching infrastructure if scaling out. |
| Storyblok | `STORYBLOK_ACCESS_TOKEN` must be a **public delivery token** for the EU space, `STORYBLOK_VERSION=published`, and `STORYBLOK_CACHE_TTL` is the cache time in seconds. The Laravel service uses the EU endpoint directly. |
| Store policy | Set every `MOBILE_ARENA_*` inventory, delivery-fee, and refund value to approved numbers. If omitted, `config/mobile-arena.php` supplies demo defaults. Set `MOBILE_ARENA_EMAIL_NOTIFICATIONS` after mail delivery is configured. |

`STORYBLOK_PERSONAL_ACCESS_TOKEN`, `STORYBLOK_SPACE_ID`, and `STORYBLOK_REGION` are for trusted Management API maintenance tools such as `scripts/populate-storyblok-home.php`; they are **not** needed by the production web runtime. Keep the personal access token off the web host. `MOBILE_ARENA_ADMIN_*` variables are only needed when deliberately running `AdminUserSeeder`; do not leave an admin password in a release artifact.

## Production deployment

1. Provision a separate MySQL/MariaDB database with `utf8mb4`, an application user limited to that database, and automated backups. Rehearse migrations against a staging copy. Moving from local SQLite does not transfer users, orders, products, or inventory; plan that data migration separately.
2. Install PHP and Composer dependencies. Keep `vendor/` available to the running application, whether built in CI or installed on the host. Deploy the current `public/assets/` files. Point the web server only at `public/` and enable HTTPS.
3. Supply the private production environment described above. For a new installation, run `php artisan key:generate` once against the private `.env`. Preserve `APP_KEY` on later deployments.
4. Ensure `storage/app/private`, `storage/app/public`, `storage/framework/cache/data`, `storage/framework/sessions`, `storage/framework/views`, `storage/logs`, and `bootstrap/cache` exist and are writable by the PHP web and CLI users. On a Debian-style host using `www-data`, `sudo chown -R www-data:www-data storage bootstrap/cache` and `sudo chmod -R ug+rwX storage bootstrap/cache` are examples; adapt the account for the host and do not make the entire project world-writable. Run `php artisan storage:link` only if public-disk files will be served; the current product-image flow does not upload files.
5. Back up an existing production database before each migration. Run the commands below from the project root with the production environment loaded:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan migrate:status
php artisan optimize
php artisan about
php artisan schedule:list
```

Do not run the demo seeder or `migrate:fresh` against production data. The existing test suite uses in-memory SQLite; it does not prove the production MySQL/MariaDB connection or migration outcome.

### Scheduler and queue

`routes/console.php` schedules `orders:expire-reservations` every minute with overlap prevention. Install one cron entry under an account that can read the app and write its cache:

```cron
* * * * * cd /var/www/mobile-arena && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Adjust paths for the host and verify the task with `php artisan schedule:list`. Without this cron job, stale order reservations will not expire automatically. The current `QUEUE_CONNECTION=sync` executes work in the request and needs no worker. If you choose database or Redis queues, provision that backend and a supervised `php artisan queue:work` process before switching the environment variable.

### Storyblok release

The `home` story currently has a draft `home_page` version. A published Content Delivery API request returned 404 during the readiness check. Keep production on `STORYBLOK_VERSION=published` with a public token; publish only approved content as a separate release action. The storefront has a fallback while published CMS content is unavailable. Draft/preview tokens and Management API credentials must not be exposed to the public runtime. With the default `STORYBLOK_CACHE_TTL=300`, published changes may take up to five minutes to appear in Laravel's cache.

### Final checks and release exclusions

Run `php artisan test` before release, check the real web and CLI PHP configuration, verify HTTPS and password-reset delivery, inspect backups, and confirm the scheduler fires. Confirm actual catalog/stock/prices, store contact details, approved payment and delivery policies, and owned or authorized product photos.

`/local-admin` routes are role-protected and are registered in production; there is currently no environment-based 404 gate. Decide whether production admin access is intended and apply a separate application or network policy before public launch if it is not.

Keep `.env` and credentials out of the public artifact. Do not deploy `database/database.sqlite`, the demo MySQL SQL dump, tests, PHPUnit caches, `node_modules/`, `public/hot`, or Storyblok Management API scripts to the web server. The runtime still needs its Composer `vendor/` dependencies and the checked-in `public/assets/` files.

## Fix for `InvalidArgumentException: Please provide a valid cache path`

Laravel requires `storage/framework/views` to exist before Blade can compile views. This package now includes that directory and also includes `start-local.bat`, which recreates all required runtime folders automatically.

For XAMPP/Windows, you can start the prototype with:

```bat
start-local.bat
```

If you are repairing an older extracted copy, run these commands from the project root:

```powershell
New-Item -ItemType Directory -Force storage\framework\views
New-Item -ItemType Directory -Force storage\framework\sessions
New-Item -ItemType Directory -Force storage\framework\cache\data
New-Item -ItemType Directory -Force storage\logs
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```
