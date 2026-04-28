#!/usr/bin/env python3
"""
PHP Syntax Checker for specific files
Checks the following files using: php -l <filepath>
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
        # Try with 'php' in PATH first
        result = subprocess.run(
            ["php", "-l", filepath],
            capture_output=True,
            text=True,
            timeout=10
        )
        return result.returncode == 0, result.stdout.strip() + result.stderr.strip()
    except FileNotFoundError:
        try:
            # Try with C:\xampp\php\php.exe
            result = subprocess.run(
                [r"C:\xampp\php\php.exe", "-l", filepath],
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
    BASE_PATH / "student" / "dashboard.php",
    BASE_PATH / "faculty" / "dashboard.php",
    BASE_PATH / "student" / "ajax_book.php",
    BASE_PATH / "faculty" / "ajax_book.php",
]

def main():
    print("\n" + "=" * 90)
    print("PHP SYNTAX CHECK - Using php -l <file>")
    print("=" * 90 + "\n")
    
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
    
    # Print summary
    print("=" * 90)
    print("SUMMARY - PHP SYNTAX CHECK RESULTS")
    print("=" * 90 + "\n")
    
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
                print(f"       Error: {error}")
    
    print("\n" + "=" * 90)
    
    if failed_count == 0:
        print("✅ ALL SYNTAX CHECKS PASSED - No PHP syntax errors detected!")
    else:
        print(f"❌ {failed_count} file(s) failed syntax check")
    
    print("=" * 90 + "\n")
    
    return 0 if failed_count == 0 else 1

if __name__ == "__main__":
    sys.exit(main())
