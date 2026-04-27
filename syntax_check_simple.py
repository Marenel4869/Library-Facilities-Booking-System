import subprocess
import os

php_files = [
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\includes\header.php",
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\includes\navbar.php",
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\student\view_booking.php",
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\faculty\view_booking.php",
    r"C:\xampp\htdocs\Library-Facilities-Booking-System\admin\view_booking.php",
]

css_file = r"C:\xampp\htdocs\Library-Facilities-Booking-System\assets\css\style.css"

print("\n" + "=" * 80)
print("PHP SYNTAX CHECK REPORT (via subprocess)")
print("=" * 80 + "\n")

passed = 0
failed = 0

for i, php_file in enumerate(php_files, 1):
    fname = os.path.basename(php_file)
    print(f"[{i}] {fname}")
    
    if not os.path.exists(php_file):
        print(f"    ❌ File not found\n")
        failed += 1
        continue
    
    try:
        result = subprocess.run(
            ["php", "-l", php_file],
            capture_output=True,
            text=True,
            timeout=10
        )
        
        if result.returncode == 0:
            print(f"    ✅ Syntax OK\n")
            passed += 1
        else:
            print(f"    ❌ Syntax Error")
            print(f"    {result.stdout}{result.stderr}\n")
            failed += 1
    except FileNotFoundError:
        print(f"    ❌ PHP not found in PATH\n")
        failed += 1
    except Exception as e:
        print(f"    ❌ Error: {e}\n")
        failed += 1

print("-" * 80)
print("CSS FILE CHECK")
print("-" * 80 + "\n")
print(f"Checking: {os.path.basename(css_file)}")
if os.path.exists(css_file):
    size = os.path.getsize(css_file)
    print(f"    ✅ Exists ({size} bytes)\n")
else:
    print(f"    ❌ Not found\n")

print("=" * 80)
print(f"RESULTS: {passed} passed, {failed} failed out of {len(php_files)} files")
print("=" * 80)
