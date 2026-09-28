@echo off
setlocal
cd /d "%~dp0"

if not exist storage\framework\views mkdir storage\framework\views
if not exist storage\framework\sessions mkdir storage\framework\sessions
if not exist storage\framework\cache\data mkdir storage\framework\cache\data
if not exist storage\logs mkdir storage\logs

if not exist .env (
    copy .env.example .env >nul
)

echo Preparing Mobile Arena Laravel runtime folders...
php artisan config:clear
php artisan cache:clear
php artisan view:clear

echo.
echo Starting Mobile Arena at http://127.0.0.1:8000
php artisan serve
endlocal
