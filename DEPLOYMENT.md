# 🛠 Logger View Deployment Guide

Follow these steps to deploy the Logger View application to your production environment.

## 1. Prerequisites
- **Web Server**: Nginx (Recommended) or Apache.
- **PHP**: 8.1 or higher.
- **PHP Extensions**: `pdo_sqlite`, `openssl`, `mbstring`, `curl`.
- **Cache**: Redis (Optional, for AI analysis caching).
- **Permissions**: The web server must have write access to the `.env` file and the `data/` directory.

## 2. Upload Files
Upload the project content to your server's web root. Ensure the `src/`, `assets/`, and `data/` folders are present.

## 3. Interactive Installation
Run the installer script via CLI from your project root. This is the **required** way to initialize your environment.

```bash
php install.php
```

**During Installation:**
1. **AUTH_SECRET**: You will be prompted to provide a **32-character** secret. This is used for password peppering and credential encryption. **Do not lose this.**
2. **Database**: The script will automatically create the `data/` folder and initialize `database.sqlite` with the correct schema and a peppered admin hash.

**Default Credentials:**
- **Username**: `admin`
- **Password**: `admin123`

> [!IMPORTANT]
> **Delete `install.php` and `schema.sql`** immediately after successful installation.

## 4. Administration & Utilities
Logger View provides CLI tools for secure management:

### Update User Password
If you need to reset a password from the terminal:
```bash
php src/scripts/update_password.php <username> <new_password>
```

### Rotate Security Secret
If your `AUTH_SECRET` is compromised, use this tool to rotate it securely:
```bash
php src/scripts/update_secret.php
```
*Note: This will re-encrypt your saved SMTP/AI keys but will invalidate existing user passwords. You will be prompted to set a new admin password.*

### Visual Analytics (REQUIRED CRON)
To enable the "Analytics" dashboard and keep it updated with real data, you MUST schedule the stats collector to run hourly. This is the **only** required cron job for the application.
```bash
# Example crontab entry (run every hour)
# Note: The system uses UTC internally for all statistics and charts.
0 * * * * /usr/bin/php /var/www/logger-view/src/scripts/collect_stats.php >> /var/log/logger-view-cron.log 2>&1
```

### Seed Historical Data (ONE-TIME UTILITY)
If you are deploying for the first time and want to see the charts populated with sample data immediately (without waiting for the first cron run), you can run this **once** manually. Do **NOT** add this to your crontab.
```bash
php src/scripts/seed_stats.php
```
*Note: This generates 24 hours of simulated historical data so your dashboard looks great on minute one.*

## 5. Web Server Setup

### Nginx (Recommended)
```nginx
server {
    listen 80;
    server_name logs.yourdomain.com;
    root /var/www/logger-view;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    }

    # Security: Deny access to sensitive files
    location ~ /\.env { deny all; }
    location ^~ /data/ { deny all; }
    location ~ \.sqlite$ { deny all; }
}
```

### Apache
Ensure `mod_rewrite` is enabled. You can use an `.htaccess` file in the root directory:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteRule ^index\.php$ - [L]
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . /index.php [L]
</IfModule>

# Security: Deny access to sensitive files
<FilesMatch "^\.env">
    Order allow,deny
    Deny from all
</FilesMatch>

<Directory /var/www/logger-view/data>
    Order allow,deny
    Deny from all
</Directory>

<FilesMatch "\.sqlite$">
    Order allow,deny
    Deny from all
</FilesMatch>
```

## 6. Security Checklist
- [x] Run `install.php` with a unique 32-character secret.
- [ ] Change the default `admin` password immediately.
- [ ] Setup SMTP for secure OTP (Multi-factor) authentication.
- [ ] Ensure `data/` and `.env` are restricted at the web server level.
- [ ] Schedule `collect_stats.php` in crontab for analytics.
- [ ] Enforce HTTPS for all traffic.
