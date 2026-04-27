import subprocess
import os

files_to_check = [
    r'C:\xampp\htdocs\Library-Facilities-Booking-System\includes\header.php',
    r'C:\xampp\htdocs\Library-Facilities-Booking-System\includes\navbar.php',
    r'C:\xampp\htdocs\Library-Facilities-Booking-System\student\view_booking.php',
    r'C:\xampp\htdocs\Library-Facilities-Booking-System\faculty\view_booking.php',
    r'C:\xampp\htdocs\Library-Facilities-Booking-System\admin\view_booking.php',
    r'C:\xampp\htdocs\Library-Facilities-Booking-System\assets\css\style.css'
]

print("=" * 70)
print("PHP SYNTAX CHECK RESULTS")
print("=" * 70)

for file_path in files_to_check:
    file_name = os.path.basename(file_path)
    
    # For CSS file, just check existence and content
    if file_path.endswith('.css'):
        if os.path.exists(file_path):
            size = os.path.getsize(file_path)
            if size > 0:
                print(f"✓ {file_name}: PASS (exists, {size} bytes)")
            else:
                print(f"✗ {file_name}: FAIL (file is empty)")
        else:
            print(f"✗ {file_name}: FAIL (file not found)")
    else:
        # For PHP files, run syntax check
        if not os.path.exists(file_path):
            print(f"✗ {file_name}: FAIL (file not found)")
        else:
            try:
                result = subprocess.run(
                    ['php', '-l', file_path],
                    capture_output=True,
                    text=True,
                    timeout=10
                )
                
                if result.returncode == 0:
                    print(f"✓ {file_name}: PASS")
                else:
                    error_msg = result.stdout.strip() if result.stdout else result.stderr.strip()
                    print(f"✗ {file_name}: FAIL")
                    print(f"  Error: {error_msg}")
            except FileNotFoundError:
                print(f"✗ {file_name}: ERROR (php executable not found in PATH)")
            except subprocess.TimeoutExpired:
                print(f"✗ {file_name}: ERROR (timeout)")
            except Exception as e:
                print(f"✗ {file_name}: ERROR ({str(e)})")

print("=" * 70)
