#!/usr/bin/env python3
import subprocess
import os
import sys

# List of PHP files to check
php_files = [
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\includes\header.php",
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\includes\navbar.php",
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\student\view_booking.php",
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\faculty\view_booking.php",
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\admin\view_booking.php",
]

# CSS file to check
css_file = r"C:\xampp\htdocs\Library-Facilities-Booking-System\assets\css\style.css"

print("=" * 80)
print("PHP SYNTAX CHECK REPORT")
print("=" * 80)

# Check PHP files
php_results = {"passed": 0, "failed": 0, "errors": []}

for php_file in php_files:
    print(f"\nChecking: {php_file}")
    
    if not os.path.exists(php_file):
        print(f"  ❌ FAIL - File not found")
        php_results["failed"] += 1
        php_results["errors"].append(f"{php_file}: File not found")
        continue
    
    try:
        # Run php -l on the file
        result = subprocess.run(
            ["php", "-l", php_file],
            capture_output=True,
            text=True,
            timeout=10
        )
        
        if result.returncode == 0:
            print(f"  ✅ PASS - No syntax errors")
            php_results["passed"] += 1
        else:
            print(f"  ❌ FAIL - Syntax error found")
            print(f"  Error output:\n{result.stderr if result.stderr else result.stdout}")
            php_results["failed"] += 1
            php_results["errors"].append(f"{php_file}: {result.stderr if result.stderr else result.stdout}")
    
    except FileNotFoundError:
        print(f"  ❌ ERROR - PHP command not found (php -l unavailable)")
        php_results["failed"] += 1
        php_results["errors"].append(f"{php_file}: PHP command not found")
    except subprocess.TimeoutExpired:
        print(f"  ❌ TIMEOUT - File check exceeded timeout")
        php_results["failed"] += 1
        php_results["errors"].append(f"{php_file}: Timeout")
    except Exception as e:
        print(f"  ❌ ERROR - {str(e)}")
        php_results["failed"] += 1
        php_results["errors"].append(f"{php_file}: {str(e)}")

# Check CSS file
print("\n" + "=" * 80)
print("CSS FILE CHECK")
print("=" * 80)
print(f"\nChecking: {css_file}")

if os.path.exists(css_file):
    file_size = os.path.getsize(css_file)
    if file_size > 0:
        print(f"  ✅ PASS - File exists and has content ({file_size} bytes)")
    else:
        print(f"  ⚠️  WARNING - File exists but is empty")
else:
    print(f"  ❌ FAIL - File not found")

# Summary
print("\n" + "=" * 80)
print("SUMMARY")
print("=" * 80)
print(f"PHP Files Checked: {len(php_files)}")
print(f"  ✅ Passed: {php_results['passed']}")
print(f"  ❌ Failed: {php_results['failed']}")

if php_results["errors"]:
    print("\nErrors Found:")
    for error in php_results["errors"]:
        print(f"  - {error}")

sys.exit(0 if php_results["failed"] == 0 else 1)
