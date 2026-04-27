#!/usr/bin/env python3
"""
PHP Syntax Checker - Uses subprocess to run php -l
"""
import subprocess
import os
import sys

# Define file paths
php_files = [
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\includes\header.php",
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\includes\navbar.php",
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\student\view_booking.php",
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\faculty\view_booking.php",
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\admin\view_booking.php",
]

css_file = r"C:\xampp\htdocs\Library-Facilities-Booking-System\assets\css\style.css"

def main():
    print("\n" + "=" * 80)
    print("PHP SYNTAX CHECK REPORT")
    print("=" * 80 + "\n")
    
    passed = 0
    failed = 0
    errors = []
    
    # Check each PHP file
    for i, php_file in enumerate(php_files, 1):
        print(f"[{i}/{len(php_files)}] {os.path.basename(php_file)}")
        
        # First check if file exists
        if not os.path.exists(php_file):
            print(f"      ❌ FAIL - File not found")
            print(f"      Path: {php_file}")
            failed += 1
            errors.append(f"{php_file}: File not found")
            continue
        
        try:
            # Execute: php -l <file>
            result = subprocess.run(
                ["php", "-l", php_file],
                capture_output=True,
                text=True,
                timeout=10
            )
            
            if result.returncode == 0:
                print(f"      ✅ PASS - No syntax errors")
                passed += 1
            else:
                print(f"      ❌ FAIL - Syntax error detected")
                error_msg = result.stderr if result.stderr else result.stdout
                print(f"      Error: {error_msg.strip()}")
                failed += 1
                errors.append(f"{os.path.basename(php_file)}: {error_msg.strip()}")
        
        except FileNotFoundError:
            print(f"      ❌ ERROR - PHP not found (php.exe not in PATH)")
            failed += 1
            errors.append(f"{php_file}: PHP executable not found")
        except subprocess.TimeoutExpired:
            print(f"      ❌ TIMEOUT - Syntax check timed out")
            failed += 1
            errors.append(f"{php_file}: Timeout during check")
        except Exception as e:
            print(f"      ❌ ERROR - {type(e).__name__}: {str(e)}")
            failed += 1
            errors.append(f"{php_file}: {str(e)}")
    
    # Check CSS file
    print("\n" + "-" * 80)
    print("CSS FILE CHECK")
    print("-" * 80 + "\n")
    
    print(f"Checking: {os.path.basename(css_file)}")
    if os.path.exists(css_file):
        file_size = os.path.getsize(css_file)
        if file_size > 0:
            print(f"      ✅ EXISTS - {file_size} bytes")
        else:
            print(f"      ⚠️  WARNING - Empty file (0 bytes)")
    else:
        print(f"      ❌ NOT FOUND - {css_file}")
    
    # Print summary
    print("\n" + "=" * 80)
    print("SUMMARY")
    print("=" * 80)
    print(f"PHP Files:  {passed} passed, {failed} failed (out of {len(php_files)})")
    
    if errors:
        print("\nERRORS FOUND:")
        for error in errors:
            print(f"  • {error}")
    else:
        print("\n✅ All checks completed successfully!")
    
    print("=" * 80 + "\n")
    
    return 0 if failed == 0 else 1

if __name__ == "__main__":
    sys.exit(main())
