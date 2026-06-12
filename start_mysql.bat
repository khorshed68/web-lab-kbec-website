@echo off
REM ============================================================
REM KBEC - Start MySQL for XAMPP (run as Administrator or double-click)
REM ============================================================
echo Starting KBEC MySQL (MariaDB)...
start "" /B "c:\xampp\mysql\bin\mysqld.exe" --defaults-file="c:\xampp\mysql\bin\my.ini" --standalone
timeout /t 5 /nobreak >nul
echo MySQL started. Visit: http://localhost/kbec/
echo Press any key to stop MySQL when done.
pause
"c:\xampp\mysql\bin\mysqladmin.exe" -u root -h 127.0.0.1 shutdown
echo MySQL stopped.
