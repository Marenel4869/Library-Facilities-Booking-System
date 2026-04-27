#!/usr/bin/env python3
"""
PHP Syntax Checker using subprocess module
Runs: php -l <file> for each PHP file
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

php_files = [
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\includes\header.php",
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\includes\navbar.php",
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\student\view_booking.php",
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\faculty\view_booking.php",
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\admin\view_booking.php",
]

css_file = r"C:\xampp\htdocs\Library-Facilities-Booking-System\assets\css\style.css"

print("\n" + "=" * 80)
print("PHP SYNTAX CHECK - Using subprocess to execute: php -l <file>")
print("=" * 80 + "\n")

passed = []
failed = []

for filepath in php_files:
    fname = os.path.basename(filepath)
    print(f"Checking: {fname}")
    
    if not os.path.exists(filepath):
        print(f"  ❌ FAIL - File not found at: {filepath}\n")
        failed.append((fname, "File not found"))
        continue
    
    success, output = check_php_syntax(filepath)
    
    if success:
        print(f"  ✅ PASS - No syntax errors")
        print(f"  Output: {output.strip()}\n")
        passed.append(fname)
    else:
        print(f"  ❌ FAIL - Syntax error detected")
        print(f"  Output: {output.strip()}\n")
        failed.append((fname, output.strip()))

print("-" * 80)
print("CSS FILE CHECK")
print("-" * 80 + "\n")

print(f"Checking: {os.path.basename(css_file)}")
if os.path.exists(css_file):
    size = os.path.getsize(css_file)
    print(f"  ✅ PASS - File exists with {size} bytes of content\n")
else:
    print(f"  ❌ FAIL - File not found\n")
    failed.append(("style.css", "File not found"))

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
            print(f"     {error[:100]}")

print("\n" + "=" * 80 + "\n")

if not failed:
    print("✅ ALL CHECKS PASSED!")
else:
    print(f"❌ {len(failed)} checks failed")

sys.exit(0 if not failed else 1)
