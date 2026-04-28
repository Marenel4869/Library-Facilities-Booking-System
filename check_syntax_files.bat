@echo off
setlocal enabledelayedexpansion

cd /d C:\xampp\htdocs\Library-Facilities-Booking-System

echo.
echo ================================================================================
echo PHP SYNTAX CHECK - Requested Files
echo ================================================================================
echo.

set PASSED=0
set FAILED=0

REM Check student\dashboard.php
echo Checking: student\dashboard.php
C:\xampp\php\php.exe -l student\dashboard.php >nul 2>&1
if !ERRORLEVEL! equ 0 (
    echo   [PASS] No syntax errors
    C:\xampp\php\php.exe -l student\dashboard.php
    set /a PASSED+=1
) else (
    echo   [FAIL] Syntax error detected
    C:\xampp\php\php.exe -l student\dashboard.php
    set /a FAILED+=1
)
echo.

REM Check faculty\dashboard.php
echo Checking: faculty\dashboard.php
C:\xampp\php\php.exe -l faculty\dashboard.php >nul 2>&1
if !ERRORLEVEL! equ 0 (
    echo   [PASS] No syntax errors
    C:\xampp\php\php.exe -l faculty\dashboard.php
    set /a PASSED+=1
) else (
    echo   [FAIL] Syntax error detected
    C:\xampp\php\php.exe -l faculty\dashboard.php
    set /a FAILED+=1
)
echo.

REM Check student\ajax_book.php
echo Checking: student\ajax_book.php
C:\xampp\php\php.exe -l student\ajax_book.php >nul 2>&1
if !ERRORLEVEL! equ 0 (
    echo   [PASS] No syntax errors
    C:\xampp\php\php.exe -l student\ajax_book.php
    set /a PASSED+=1
) else (
    echo   [FAIL] Syntax error detected
    C:\xampp\php\php.exe -l student\ajax_book.php
    set /a FAILED+=1
)
echo.

REM Check faculty\ajax_book.php
echo Checking: faculty\ajax_book.php
C:\xampp\php\php.exe -l faculty\ajax_book.php >nul 2>&1
if !ERRORLEVEL! equ 0 (
    echo   [PASS] No syntax errors
    C:\xampp\php\php.exe -l faculty\ajax_book.php
    set /a PASSED+=1
) else (
    echo   [FAIL] Syntax error detected
    C:\xampp\php\php.exe -l faculty\ajax_book.php
    set /a FAILED+=1
)
echo.

echo ================================================================================
echo RESULTS SUMMARY
echo ================================================================================
echo Passed: !PASSED!/4
echo Failed: !FAILED!/4
echo ================================================================================
echo.

endlocal
