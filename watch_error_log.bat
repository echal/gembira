@echo off
echo === Monitoring PHP Error Log ===
echo Press Ctrl+C to stop
echo.

powershell -Command "Get-Content 'C:\xampp\php\logs\php_error_log' -Wait -Tail 50"
