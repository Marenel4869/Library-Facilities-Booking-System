@echo off
cd C:\xampp\htdocs\Library-Facilities-Booking-System
if not exist api mkdir api
if not exist logs mkdir logs
echo Directories created successfully
node create_notification_files.js
pause
