CASTRO'S READY CMS - PREMIUM UPDATE

CURRENT INSTALLATION
1. Back up the current MySQL database.
2. Execute Castros-Ready-ALTER-Premium-Auth-Email.sql once.
3. Replace the project files with this package, preserving config/database.php and config/app.key.
4. Refresh the browser with Ctrl+F5.

NEW INSTALLATION
- Open /install/ and complete the database wizard.
- The installer uses database.sql and then redirects to /admin/setup.php.

ADMIN ACCESS
- /admin/
- Login includes Remember me.
- Forgot password sends a one-time 60-minute reset link to the email stored in the administrator profile.
- Password reset requires an active Admin Security email sender. If unavailable, Website Alerts is used as fallback.

EMAIL PURPOSES
1. Website Alerts
2. Admin Security
3. Estimate Requests
4. Auto Replies

GALLERY
- Saved gallery uploads are displayed from uploads/.
- When a gallery record has no uploaded image, the admin displays the same website preview image currently used by the public landing page.
- Gallery upload supports chooser, drag/drop and clipboard paste, plus large modal preview.

IMPORTANT PRIVATE FILES
- config/database.php
- config/app.key
Both remain ignored by Git.


UPDATE 2026-08-27
- Maintenance mode now applies even while an admin is logged in. Use Settings > Preview real site for a private preview.
- Optional maintenance image supports drag/drop, paste and file chooser.
- API integrations now include API Type and Authentication Type. Run database-update.sql once on an existing installation.
- First-time installer can optionally try to CREATE DATABASE if the MySQL account has permission; on typical cPanel hosting, create the DB/user first and leave that option off.
- Email configurations include a saved-config test tool.
