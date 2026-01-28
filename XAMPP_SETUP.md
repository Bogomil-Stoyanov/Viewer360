# XAMPP Setup Guide for Viewer360

## ✅ Application Updated for XAMPP!

The application has been modified to automatically detect its installation path, so it will work correctly whether you install it in XAMPP, Docker, or run it standalone with PHP's built-in server.

## Quick Setup Instructions

### 1. Install XAMPP
Download and install XAMPP for macOS from https://www.apachefriends.org/

### 2. Move Project to XAMPP
Open Terminal and run ONE of these commands:

**Option A - Symbolic Link (Recommended for development):**
```bash
ln -s "/Users/mihaildobroslavski/Documents/FMI/7 sem/Viewer360" /Applications/XAMPP/htdocs/Viewer360
```

**Option B - Copy Project:**
```bash
cp -r "/Users/mihaildobroslavski/Documents/FMI/7 sem/Viewer360" /Applications/XAMPP/htdocs/
```

### 3. Start XAMPP Services
1. Open XAMPP Control Panel
2. Click "Start" for **Apache**
3. Click "Start" for **MySQL**

### 4. Create Database

**Using phpMyAdmin (Easiest):**
1. Open: http://localhost/phpmyadmin
2. Click "New" in the left sidebar
3. Database name: `viewer360`
4. Collation: `utf8mb4_unicode_ci`
5. Click "Create"
6. Click on `viewer360` database
7. Go to "SQL" tab
8. Copy and paste all content from `docker/init.sql`
9. Click "Go"
10. Go to "User accounts" → "Add user account"
    - Username: `viewer360_user`
    - Host: `localhost`
    - Password: `viewer360_pass`
    - Check "Grant all privileges on database viewer360"
    - Click "Go"

**Using Terminal (Alternative):**
```bash
# Connect to MySQL
/Applications/XAMPP/bin/mysql -u root

# In MySQL prompt, run:
CREATE DATABASE viewer360 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'viewer360_user'@'localhost' IDENTIFIED BY 'viewer360_pass';
GRANT ALL PRIVILEGES ON viewer360.* TO 'viewer360_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Import schema
/Applications/XAMPP/bin/mysql -u viewer360_user -pviewer360_pass viewer360 < "/Users/mihaildobroslavski/Documents/FMI/7 sem/Viewer360/docker/init.sql"
```

### 5. Set Permissions
```bash
chmod -R 755 /Applications/XAMPP/htdocs/Viewer360/public/uploads
```

### 6. Access Application
Open your browser and go to:
```
http://localhost/Viewer360/public/
```

### 7. Test Your Setup (Recommended)
Visit the test page to verify everything is working:
```
http://localhost/Viewer360/public/test_setup.php
```

This page will check:
- ✅ PHP version and extensions
- ✅ Database connection
- ✅ File permissions
- ✅ URL configuration

If all tests pass, you're ready to use the app!

## Troubleshooting

### "Can't connect to database"
- Verify MySQL is running in XAMPP Control Panel (green status)
- Check database credentials in phpMyAdmin
- Ensure database `viewer360` exists

### "Permission denied" when uploading
Run in Terminal:
```bash
chmod -R 777 /Applications/XAMPP/htdocs/Viewer360/public/uploads
```

### "File too large" error
Edit `/Applications/XAMPP/etc/php.ini`:
```ini
upload_max_filesize = 55M
post_max_size = 55M
max_execution_time = 300
memory_limit = 256M
```
Restart Apache from XAMPP Control Panel.

### Blank page / PHP errors
Check Apache error log:
```bash
tail -f /Applications/XAMPP/logs/error_log
```

## Creating Your First User

1. Go to http://localhost/Viewer360/public/register.php
2. Create an account
3. Login at http://localhost/Viewer360/public/login.php
4. Start uploading panoramas!

## Optional: Clean URLs with Virtual Host

If you want to access the app at `http://viewer360.local` instead of `http://localhost/Viewer360/public/`:

1. Edit `/Applications/XAMPP/etc/extra/httpd-vhosts.conf`:
```apache
<VirtualHost *:80>
    DocumentRoot "/Applications/XAMPP/htdocs/Viewer360/public"
    ServerName viewer360.local
    <Directory "/Applications/XAMPP/htdocs/Viewer360/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

2. Edit `/etc/hosts` (requires sudo):
```bash
sudo nano /etc/hosts
```
Add this line:
```
127.0.0.1 viewer360.local
```

3. Restart Apache in XAMPP Control Panel

4. Access at: http://viewer360.local

## What Was Changed for XAMPP

The following files have been modified to work with XAMPP:
- `src/Config.php` - Added auto-detection of base URL
- `views/header.php` - Updated all URLs to use Config::url()
- `public/index.php` - Updated URLs and redirects
- `public/explore.php` - Updated URLs for panorama links

All URLs now automatically adjust based on where the application is installed!
