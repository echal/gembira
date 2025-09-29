@echo off
echo ========================================
echo   GEMBIRA - Restart Apache Service
echo ========================================
echo.

echo Stopping Apache service...
net stop Apache2.4 2>nul
if %errorlevel% neq 0 (
    echo Apache service is not running or failed to stop
) else (
    echo Apache service stopped successfully
)

echo.
echo Starting Apache service...
net start Apache2.4
if %errorlevel% neq 0 (
    echo Failed to start Apache service
    echo Please check XAMPP Control Panel
    pause
    exit /b 1
) else (
    echo Apache service started successfully
)

echo.
echo ========================================
echo   Configuration Applied Successfully!
echo ========================================
echo.
echo GEMBIRA is now only accessible from:
echo - http://127.0.0.1/gembira/public
echo - http://localhost/gembira/public
echo.
echo Other devices on WiFi network cannot access the application.
echo.
pause