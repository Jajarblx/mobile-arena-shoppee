# Deployment Notes

## Local XAMPP

1. Put the project in `C:\xampp\htdocs\mobile-arena`.
2. Ensure PHP 8.2+ has `mbstring`, `openssl`, `pdo_mysql` (or `pdo_sqlite`) and `zip` enabled.
3. Start Apache and MySQL in XAMPP.
4. Either use the included SQLite demo database, or import `database/mobile_arena_mysql.sql` in phpMyAdmin and copy `.env.mysql.example` to `.env`.
5. Run `composer install` if the `vendor` directory is not present.
6. Run `php artisan key:generate` after creating a new `.env`.
7. Run `php artisan serve` and open `http://127.0.0.1:8000`.

## Production checklist

- Set `APP_ENV=production`, `APP_DEBUG=false`, and a real HTTPS `APP_URL`.
- Use a production database and backups.
- Replace every demo product/price/stock/policy field with store-approved data.
- Replace web-hosted reference product photos with Mobile Arena-owned/authorized product photos before commercial deployment; pre-owned/refurbished listings should show the exact physical unit.
- Confirm Mobile Arena contact details and exact store location with management.
- Add authentication and authorization before exposing staff/admin features.
- Keep `/local-admin` unavailable in production (the code already returns 404 unless `APP_ENV=local`).
- Use a real payment gateway only after merchant onboarding and approval.
- Point the web server document root to Laravel's `public/` directory.
- Run `php artisan optimize` after production configuration is finalized.

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
