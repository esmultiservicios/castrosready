CASTRO'S READY — LANDING PAGE + CUSTOM MYSQL CMS

INSTALLATION IN CPANEL
1. Create a MySQL database and database user in cPanel > MySQL Databases.
2. Assign the user to the database with ALL PRIVILEGES.
3. Open phpMyAdmin, select the new database and import database.sql.
4. Copy config/database.example.php as config/database.php.
5. Edit config/database.php with the database name, user and password created in cPanel.
6. Upload the CONTENTS of this folder directly to the Document Root of castroready.esmultiservicios.com.
7. Make sure uploads/, uploads/gallery/ and uploads/estimates/ are writable by PHP (normally 755 works in cPanel).
8. Open https://castroready.esmultiservicios.com/admin/
9. The first visit redirects to setup.php. Create the first administrator username/password.
10. From then on, use /admin/ to manage the site.

ADMIN MODULES
- Dashboard
- Page content
- Services
- Gallery images
- Service areas
- Home improvement tips
- Free estimate requests
- Contact/social settings

FREE ESTIMATE
The form saves requests in MySQL table estimate_requests. It also attempts to send a simple notification using PHP mail() to the company email configured in Settings. If your hosting disables mail(), requests are still safely stored in the admin panel. SMTP can be added later if desired.

SECURITY
- Admin passwords use password_hash/password_verify.
- PDO prepared statements are used.
- Admin forms use CSRF tokens.
- Uploaded images are MIME checked and executable script extensions are denied in uploads.
- database.sql and config files are protected with .htaccess.

IMPORTANT
This package does not include a default admin password. The first administrator is created from /admin/setup.php after database installation.
