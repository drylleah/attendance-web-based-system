@echo off
cd /d "%~dp0"
echo Running composer install...
composer install --no-interaction
echo.
echo Exit code: %ERRORLEVEL%
if exist vendor\autoload.php (
    echo VENDOR_OK
) else (
    echo VENDOR_MISSING
)
