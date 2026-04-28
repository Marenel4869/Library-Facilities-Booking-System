@echo off
REM PHP Syntax Check Script for requested files
REM This batch file runs: php -l <filepath> on each requested file

setlocal enabledelayedexpansion

echo.
echo ================================================================================
echo PHP SYNTAX CHECK - Using: php -l filepath
echo ================================================================================
echo.

set "passed=0"
set "failed=0"

REM Check file 1: student\dashboard.php
echo [1/4] Checking: student\dashboard.php
php -l "C:\xampp\htdocs\Library-Facilities-Booking-System\student\dashboard.php"
if !errorlevel! equ 0 (
    set /a passed+=1
    echo.
) else (
    set /a failed+=1
    echo.
)

REM Check file 2: faculty\dashboard.php
echo [2/4] Checking: faculty\dashboard.php
php -l "C:\xampp\htdocs\Library-Facilities-Booking-System\faculty\dashboard.php"
if !errorlevel! equ 0 (
    set /a passed+=1
    echo.
) else (
    set /a failed+=1
    echo.
)

REM Check file 3: student\ajax_book.php
echo [3/4] Checking: student\ajax_book.php
php -l "C:\xampp\htdocs\Library-Facilities-Booking-System\student\ajax_book.php"
if !errorlevel! equ 0 (
    set /a passed+=1
    echo.
) else (
    set /a failed+=1
    echo.
)

REM Check file 4: faculty\ajax_book.php
echo [4/4] Checking: faculty\ajax_book.php
php -l "C:\xampp\htdocs\Library-Facilities-Booking-System\faculty\ajax_book.php"
if !errorlevel! equ 0 (
    set /a passed+=1
    echo.
) else (
    set /a failed+=1
    echo.
)

echo ================================================================================
echo SUMMARY
echo ================================================================================
echo Passed: !passed!/4
echo Failed: !failed!/4
echo ================================================================================
echo.

if !failed! equ 0 (
    echo ✅ ALL FILES PASSED SYNTAX CHECK
) else (
    echo ❌ !failed! file(s) failed syntax check
)
echo.

endlocal
