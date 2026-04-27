import os

# Create directory
logs_dir = r'C:\xampp\htdocs\Library-Facilities-Booking-System\logs'
try:
    os.makedirs(logs_dir, exist_ok=True)
    print(f'Directory created: {logs_dir}')
except Exception as e:
    print(f'Failed to create directory: {e}')
    exit(1)

# Create .htaccess file
htaccess_path = os.path.join(logs_dir, '.htaccess')
try:
    with open(htaccess_path, 'w') as f:
        f.write('Require all denied')
    print(f'File created: {htaccess_path}')
    print(f'Content written: "Require all denied"')
    print('SUCCESS')
except Exception as e:
    print(f'Failed to create file: {e}')
    exit(1)
