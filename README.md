# HaiMotion Dashboard

HaiMotion Dashboard is a PHP-based task, project, and reminder management system. This guide is intended for deploying the application on a new hosting environment for a client or reseller purchase.

## 1. Server Requirements

Make sure the hosting supports the following:

- PHP 8.1 or newer
- MySQL 8 / MariaDB
- Apache or Nginx
- Composer
- OpenSSL, cURL, and PDO/MySQL extensions enabled
- SSL certificate recommended for production

## 2. Upload the Project

Important: do not upload only the README or a single PHP file. You should upload the full application contents, including the root files and all folders such as assets, css, helpers, images, js, logs, uploads, vendor, and any other directories that exist in the project.

Recommended upload method:

1. Compress the whole project folder into a ZIP file.
2. Upload the ZIP file to the hosting account.
3. Extract it directly into the public web directory such as public_html or www.
4. Make sure the main entry file is [index.php](index.php) after extraction.

If your hosting uses a subdomain or subfolder, upload the contents into that target directory and make sure the domain points to that folder.

Do not upload only the parent folder name as a single folder unless your hosting requires it. In most cases, the contents of the project folder should be placed directly inside the web root.

If the project is installed in a subfolder, update the base URL and links accordingly.

## 3. Create the Database

1. Create a new MySQL database in cPanel / Plesk / direct DB manager.
2. Create a database user and grant it full access to that database.
3. Import your SQL backup file if one is available.
4. If no backup is available, create the database and add the application data manually from the admin panel later.

## 4. Configure Database Connection

Edit [db_connect.php](db_connect.php) and replace the default values with your hosting database details:

- Hostname
- Database username
- Database password
- Database name

Example:

```php
$host = "localhost";
$username = "your_db_user";
$password = "your_db_password";
$database = "your_db_name";
```

## 5. Configure Email / SMTP

Edit [phpmailer_config.php](phpmailer_config.php) and update these values:

- SMTP host
- SMTP username
- SMTP password
- SMTP port
- SMTP encryption type
- APP_BASE_URL

Important:

- Set APP_BASE_URL to the final public URL of your installation, for example:
  https://yourdomain.com/
- Use a valid SMTP provider such as Mailgun, SendGrid, Zoho Mail, or your hosting mail service.

## 6. Install PHP Dependencies

If Composer is available on the server, run:

```bash
composer install --no-dev
```

This will install the required packages used by the project.

## 7. Set File Permissions

On Linux-based hosting, set the writable folders to the correct permissions:

```bash
chmod -R 755 uploads
chmod -R 755 logs
chmod -R 755 assets
```

If the hosting requires writable permissions for uploads or logs, use 775 instead of 755 for those folders.

## 8. Run the Application

Open the domain in a browser.

If the database and configuration are correct, the system should load normally.

## 9. Create the First Admin Account

After installation:

1. Open the application in the browser.
2. Create a new user from the admin panel.
3. Assign the account as administrator.
4. Log in and start using the system.

If you are migrating from an existing installation, you can restore the previous database and use the existing user accounts.

## 10. Reminder / Cron Job Setup

The system includes reminder functionality that can be triggered by cron. This is important for automatic reminders and event notifications.

Example cron entry:

```bash
* * * * * /usr/bin/php /path/to/public_html/send_event_reminders.php?key=secret123 >/dev/null 2>&1
```

Notes:

- Replace the path with the real absolute path to your project.
- The secret key is defined in [send_event_reminders.php](send_event_reminders.php).
- For security, change the key from the default value.

## 11. Recommended Production Checklist

Before handing the system to the client, confirm the following:

- Database credentials are correct
- SMTP is working
- The public URL is correct
- SSL is enabled
- Error reporting is disabled on production
- Default passwords and secret keys were changed
- Backup strategy is in place

## 12. Important Security Note

This project currently contains hardcoded configuration values in [db_connect.php](db_connect.php) and [phpmailer_config.php](phpmailer_config.php). For a production deployment, it is strongly recommended to move these values to environment variables or a secure config file later.

If you want, I can also help you prepare a more formal version of this README in Bahasa Indonesia or a client-facing version for the company purchase package.

## 14. Run locally (development)

These instructions help you run the application on your local machine for development or testing.

Requirements (local):

- PHP 8.1+ with common extensions (mysqli, pdo_mysql, mbstring, curl)
- MySQL or MariaDB
- Composer (for dependencies)
- Optional: Docker (for an isolated environment)

Option A — Using XAMPP / Laragon / MAMP (recommended for quick start)

1. Install XAMPP, Laragon, or MAMP and start Apache + MySQL.
2. Copy the full project folder into the web root (e.g., `C:\xampp\htdocs\HaiMotiDashboard` or Laragon `www` folder).
3. Create a database with your chosen name (e.g., `haimoti_local`) and a DB user.
4. Import the SQL dump if available (or seed data via admin UI later).
5. Edit `db_connect.php` with your local DB credentials.
6. Edit `phpmailer_config.php` to configure SMTP (see MailHog / Mailtrap below) and set `APP_BASE_URL` to `http://localhost/HaiMotiDashboard/` (adjust path as needed).
7. From the project root, run:

```bash
composer install --no-dev
```

8. Open `http://localhost/HaiMotiDashboard/` in your browser.

Option B — Using PHP built-in server (simple dev server)

1. Ensure MySQL is running and you have created the DB.
2. From the project root, install dependencies:

```bash
composer install --no-dev
```

3. Edit `db_connect.php` and `phpmailer_config.php` for local credentials.
4. Start PHP built-in server (project root contains `index.php`):

```bash
php -S localhost:8000
```

5. Visit `http://localhost:8000/` in your browser.

Note: the built-in server is for development only and does not replace Apache/Nginx for production.

Option C — Using Docker (recommended for reproducible local environment)

Use this minimal `docker-compose.yml` in the project root for a quick local stack (MySQL, Apache+PHP, phpMyAdmin, MailHog):

```yaml
version: '3.8'
services:
  db:
    image: mysql:8
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: haimoti_local
      MYSQL_USER: haimoti
      MYSQL_PASSWORD: haimoti
    volumes:
      - dbdata:/var/lib/mysql
    ports:
      - "3306:3306"

  web:
    image: php:8.1-apache
    volumes:
      - ./:/var/www/html
    ports:
      - "8000:80"
    depends_on:
      - db

  phpmyadmin:
    image: phpmyadmin/phpmyadmin
    environment:
      PMA_HOST: db
    ports:
      - "8080:80"

  mailhog:
    image: mailhog/mailhog
    ports:
      - "1025:1025"
      - "8025:8025"

volumes:
  dbdata:
```

After saving the file run:

```bash
docker compose up -d
```

Then:

- Visit `http://localhost:8000/` for the app.
- Visit `http://localhost:8080/` for phpMyAdmin (user: `haimoti`, password: `haimoti`).
- Visit `http://localhost:8025/` for MailHog UI to view captured emails.

To make `phpmailer_config.php` work with MailHog, set:

```php
define('SMTP_HOST', 'mailhog');
define('SMTP_PORT', 1025);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_SECURE', '');
define('APP_BASE_URL', 'http://localhost:8000/');
```

Local SMTP alternatives:

- MailHog (no auth) — see Docker example above.
- Mailtrap — set provider credentials in `phpmailer_config.php`.
- If you prefer Gmail for testing, be aware of security and app password requirements.

Running reminders manually (local testing):

```bash
# Run send_event_reminders.php from project root
php send_event_reminders.php
```

Or use the built-in URL with the secret key (for local browser testing):

```
http://localhost:8000/send_event_reminders.php?key=secret123
```

Tips & troubleshooting:

- If pages show blank or errors, enable `display_errors` temporarily in `php.ini` or check your server `error_log`.
- Ensure `vendor/` exists after `composer install` and `vendor/autoload.php` is readable.
- If assets (CSS/JS) are missing, ensure `assets/` was copied into the web root.
- For email debugging, open MailHog UI at `http://localhost:8025/` or use SMTP logs.

If you want, I can also add a `docker-compose.yml` file directly to the repository and a small `.env.example` to simplify local setup.

## 13. Branding / Logo files

The application uses several logo and brand images in different places. Replace the files below (or update the references) to apply a client's logo across the site and email templates.

- [assets/Logo.png](assets/Logo.png) — Sidebar full logo (primary brand image shown in the left sidebar). Recommended: PNG or SVG, safe width ~300px (scale as needed).
- [assets/Logo1.png](assets/Logo1.png) — Sidebar mini logo (used for compact view). Recommended: square PNG or SVG, ~40x40 px.
- [assets/logobw.png](assets/logobw.png) — Site favicon (set in `header.php`). Recommended: 32x32 PNG/ICO.
- [assets/logo3bw.png](assets/logo3bw.png) — Embedded image inside email templates (used in `phpmailer_config.php`). Recommended: PNG, max width ~600px, keep file size small for email.
- [assets/Logo2bw.png](assets/Logo2bw.png) — Alternative black/white variant (if present) used in some views.
- [assets/Asset4.png](assets/Asset4.png) — Footer background / decorative image (used in `footer.php`). Larger sizes are fine; use a compressed PNG or JPG.
- `assets/uploads/` — Uploaded logos and avatars (client-provided images are often stored here). Admins can upload brand assets via the UI or you can copy them into this folder.

Where these images are referenced in code:

- Favicon: [header.php](header.php) uses `<link rel="icon" href="assets/logobw.png">`.
- Sidebar full/mini logos: [sidebar.php](sidebar.php) uses `assets/Logo.png` and `assets/Logo1.png`.
- Email template: [phpmailer_config.php](phpmailer_config.php) embeds `assets/logo3bw.png` via `addEmbeddedImage()`.
- Footer background: [footer.php](footer.php) references `assets/Asset4.png` in an inline style background-image.

Replacement options:

- Quick (overwrite): upload the new image to the same path and filename. This is the simplest option — remember to clear server/browser caches after replacing files.
- Safer (new filename): upload new files with client-specific names and update the corresponding file reference in the PHP file(s) above (for example, change the `src` in `sidebar.php` or the `addEmbeddedImage()` path in `phpmailer_config.php`).

Recommended formats and notes:

- Use SVG for vector logos when possible (sharp at any size). Use PNG for raster logos with transparency.
- Keep email images under ~200 KB to avoid deliverability issues.
- For favicon, provide an ICO or 32x32 PNG.
- After replacing images, restart any caching layers (CDN, reverse proxy) and ask users to hard-refresh the browser to see changes.

