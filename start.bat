@echo off
cd /d "C:\Users\dhary\Herd\attendance-web-based-system"
echo Starting Attendance System...
echo Open your browser and go to: http://localhost:8080/attendance
echo.
echo Press Ctrl+C to stop the server.
"C:\xampp\php\php.exe" artisan serve --port=8080
pause
