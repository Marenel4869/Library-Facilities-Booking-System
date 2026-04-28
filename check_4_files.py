#!/usr/bin/env python3
"""
Quick PHP Syntax Checker for 4 specific files
"""
import subprocess
import os

php_files = [
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\student\dashboard.php",
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\faculty\dashboard.php",
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\student\ajax_book.php",
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\faculty\ajax_book.php",
]

print("\n" + "=" * 80)
print("PHP SYNTAX CHECK - 4 FILES")
print("=" * 80 + "\n")

passed = 0
failed = 0

for i, php_file in enumerate(php_files, 1):
    fname = os.path.basename(php_file)
    print(f"[{i}/4] {fname}")
    
    if not os.path.exists(php_file):
        print(f"      ❌ FAIL - File not found")
        failed += 1
        continue
    
    try:
        # Try direct XAMPP path first
        result = subprocess.run(
            [r"C:\xampp\php\php.exe", "-l", php_file],
            capture_output=True,
            text=True,
            timeout=10
        )
    except FileNotFoundError:
        try:
            # Fallback to PATH
            result = subprocess.run(
                ["php", "-l", php_file],
                capture_output=True,
                text=True,
                timeout=10
            )
        except FileNotFoundError:
            print(f"      ❌ ERROR - PHP executable not found")
            failed += 1
            continue
    
    if result.returncode == 0:
        print(f"      ✅ PASS - Syntax OK")
        passed += 1
    else:
        print(f"      ❌ FAIL - Syntax Error")
        error = result.stderr if result.stderr else result.stdout
        print(f"      {error.strip()}")
        failed += 1

print("\n" + "=" * 80)
print(f"RESULTS: {passed} passed, {failed} failed")
print("=" * 80 + "\n")
