#!/usr/bin/env python3
"""
PHP Syntax Checker for requested files
"""
import subprocess
import os
import sys

def check_php_syntax(filepath):
    """Execute: php -l <filepath>"""
    try:
        result = subprocess.run(
            [r"C:\xampp\php\php.exe", "-l", filepath],
            capture_output=True,
            text=True,
            timeout=10
        )
        return result.returncode == 0, result.stdout + result.stderr
    except Exception as e:
        return False, str(e)

# Files to check
php_files = [
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\student\dashboard.php",
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\faculty\dashboard.php",
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\student\ajax_book.php",
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\faculty\ajax_book.php",
]

print("\n" + "=" * 80)
print("PHP SYNTAX CHECK - Requested Files")
print("=" * 80 + "\n")

passed = []
failed = []

for filepath in php_files:
    fname = os.path.basename(filepath)
    folder = os.path.basename(os.path.dirname(filepath))
    display_name = f"{folder}/{fname}"
    
    print(f"Checking: {display_name}")
    
    if not os.path.exists(filepath):
        print(f"  ❌ FAIL - File not found at: {filepath}\n")
        failed.append((display_name, "File not found"))
        continue
    
    success, output = check_php_syntax(filepath)
    
    if success:
        print(f"  ✅ PASS - No syntax errors")
        print(f"  Output: {output.strip()}\n")
        passed.append(display_name)
    else:
        print(f"  ❌ FAIL - Syntax error detected")
        print(f"  Output: {output.strip()}\n")
        failed.append((display_name, output.strip()))

print("=" * 80)
print("RESULTS SUMMARY")
print("=" * 80)
print(f"\nPassed: {len(passed)}/{len(php_files)}")
for name in passed:
    print(f"  ✅ {name}")

if failed:
    print(f"\nFailed: {len(failed)}")
    for name, error in failed:
        print(f"  ❌ {name}")
        if error != "File not found":
            print(f"     {error}")

print("\n" + "=" * 80 + "\n")

if not failed:
    print("✅ ALL CHECKS PASSED!")
else:
    print(f"❌ {len(failed)} checks failed")

sys.exit(0 if not failed else 1)
