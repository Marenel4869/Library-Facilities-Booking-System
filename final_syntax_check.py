#!/usr/bin/env python3
"""
PHP Syntax Checker - Demonstrates subprocess.run() with php -l
This script executes: php -l <filepath> for each PHP file using subprocess module
"""
import subprocess
import os
import sys
from pathlib import Path

def run_php_lint(filepath):
    """
    Execute subprocess: php -l <filepath>
    Returns: (success: bool, output: str)
    """
    try:
        # Method 1: Try with C:\xampp\php\php.exe (direct XAMPP path)
        result = subprocess.run(
            [r"C:\xampp\php\php.exe", "-l", filepath],
            capture_output=True,
            text=True,
            timeout=10
        )
        return result.returncode == 0, result.stdout.strip() + result.stderr.strip()
    except FileNotFoundError:
        try:
            # Method 2: Try with 'php' in PATH
            result = subprocess.run(
                ["php", "-l", filepath],
                capture_output=True,
                text=True,
                timeout=10
            )
            return result.returncode == 0, result.stdout.strip() + result.stderr.strip()
        except FileNotFoundError:
            return False, "PHP executable not found in PATH or at C:\\xampp\\php\\php.exe"

# Configuration
BASE_PATH = Path(r"C:\xampp\htdocs\Library-Facilities-Booking-System")

PHP_FILES = [
    BASE_PATH / "includes" / "header.php",
    BASE_PATH / "includes" / "navbar.php",
    BASE_PATH / "student" / "view_booking.php",
    BASE_PATH / "faculty" / "view_booking.php",
    BASE_PATH / "admin" / "view_booking.php",
]

CSS_FILE = BASE_PATH / "assets" / "css" / "style.css"

def main():
    print("\n" + "=" * 85)
    print("PHP SYNTAX CHECK - Using subprocess.run() to execute: php -l <file>")
    print("=" * 85 + "\n")
    
    results = {"passed": [], "failed": []}
    
    # Check each PHP file
    for i, filepath in enumerate(PHP_FILES, 1):
        fname = filepath.name
        print(f"[{i}/{len(PHP_FILES)}] {fname}")
        print(f"        Path: {filepath}")
        
        # Verify file exists
        if not filepath.exists():
            print(f"        ❌ FAIL - File does not exist\n")
            results["failed"].append((fname, "File not found"))
            continue
        
        # Execute: php -l <file>
        success, output = run_php_lint(str(filepath))
        
        if success:
            print(f"        ✅ PASS - Valid PHP syntax")
            if output:
                print(f"        Output: {output}")
            print()
            results["passed"].append(fname)
        else:
            print(f"        ❌ FAIL - Syntax error detected")
            print(f"        Error: {output}\n")
            results["failed"].append((fname, output))
    
    # Check CSS file
    print("-" * 85)
    print("CSS FILE CHECK")
    print("-" * 85 + "\n")
    
    print(f"Checking: {CSS_FILE.name}")
    print(f"  Path: {CSS_FILE}")
    
    if CSS_FILE.exists():
        size = CSS_FILE.stat().st_size
        print(f"  ✅ PASS - File exists ({size:,} bytes)\n")
    else:
        print(f"  ❌ FAIL - File not found\n")
        results["failed"].append(("style.css", "File not found"))
    
    # Print summary
    print("=" * 85)
    print("SUMMARY - PHP SYNTAX CHECK RESULTS")
    print("=" * 85 + "\n")
    
    passed_count = len(results["passed"])
    failed_count = len(results["failed"])
    total = len(PHP_FILES)
    
    print(f"Total PHP files checked: {total}")
    print(f"  ✅ PASSED: {passed_count}")
    for name in results["passed"]:
        print(f"     • {name}")
    
    if failed_count > 0:
        print(f"\n  ❌ FAILED: {failed_count}")
        for name, error in results["failed"]:
            print(f"     • {name}")
            if "File not found" not in error and error:
                print(f"       Error: {error[:100]}")
    
    print("\n" + "=" * 85)
    
    if failed_count == 0:
        print("✅ ALL SYNTAX CHECKS PASSED - No PHP syntax errors detected!")
    else:
        print(f"❌ {failed_count} file(s) failed syntax check")
    
    print("=" * 85 + "\n")
    
    return 0 if failed_count == 0 else 1

if __name__ == "__main__":
    sys.exit(main())
