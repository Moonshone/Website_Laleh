# NEWS CMS setup on DreamHost

The CMS uses PHP, PDO, MySQL, PHP sessions, and the existing site styles. It does not require Node.js or ongoing programming. The public page is `news.php`; administration starts at `/admin/`.

## Files created and changed

- `config.php` loads `config/database.local.php` and creates the shared PDO connection used by setup and admin login.
- `config/database.local.example.php` is the safe database template containing no real password.
- `config.local.example.php` remains the separate template for application URL and mail settings.
- `database/schema.sql` creates the complete `admins`, `password_resets`, and `news_posts` tables on a new installation.
- `database/add-password-recovery.sql` adds administrator email addresses and reset tokens to an existing installation.
- `database/add-admin-role.sql` safely adds roles to a pre-existing role-less `admins` table and makes its oldest account a superadmin. Run this migration **only if** that table already exists without `role`; do not run it after `schema.sql`.
- `database/add-news-formatting.sql` adds the independent title and text formatting settings to an existing `news_posts` table. Run it once before deploying the updated NEWS editor; new installations already receive these columns from `schema.sql`.
- `setup/create-admin.php` creates only the first administrator; `setup/.gitignore` keeps its generated lock private.
- `admin/` contains login, logout, password recovery, account, dashboard, NEWS editing, administrator management, authentication, authorization, CSRF, and upload code.
- `api/news.php` is the read-only published NEWS JSON endpoint.
- `news.php` is the public NEWS page in the existing design.
- `uploads/news/.htaccess` blocks script execution; `.gitignore` excludes uploaded media.
- `styles/style.css` contains the related public NEWS and administration styles.

No unrelated page, image filename, menu behavior, gallery, or contact code is changed by this setup.

## 1. Import the database SQL

The existing database is **`neweshtaniha`** and its MySQL username is **`laleh`**.

For a new CMS installation, open DreamHost phpMyAdmin for `neweshtaniha`, select **Import**, choose `database/schema.sql`, and run it. This creates:

- `admins`: unique username and email address, one-way `password_hash`, `superadmin`/`admin` role, and creation time.
- `password_resets`: hashed, expiring, one-time reset records linked to administrators.
- `news_posts`: title, long article content, optional public image path, draft/published status, publication date, and timestamps.

`CREATE TABLE IF NOT EXISTS` does not alter existing tables. For an existing CMS, follow `database/add-password-recovery.sql`: add the nullable email column, assign a real and unique email to every existing administrator, and only then apply its `NOT NULL`/unique constraint and create `password_resets`. If the older `admins` table also lacks `role`, run `database/add-admin-role.sql` first. Back up the database before migrations and never rerun an `ALTER` migration after it succeeds.

## 2. Create the private DreamHost database configuration

On the DreamHost server, create **`/config/database.local.php` inside the deployed website root** (for example, if the site root is `/home/USERNAME/example.com`, the full path is `/home/USERNAME/example.com/config/database.local.php`). The committed `config/database.local.example.php` is the template.

Enter exactly this PHP, replacing only the password placeholder with the real MySQL password for the DreamHost user `laleh`:

```php
<?php

return [
    'host' => 'mysql.lalehbarzegar.com',
    'dbname' => 'neweshtaniha',
    'user' => 'laleh',
    'password' => 'THE_REAL_MYSQL_PASSWORD',
];
```

The connection therefore targets the DreamHost host **`mysql.lalehbarzegar.com`**, database **`neweshtaniha`**, and user **`laleh`**. Do not put the MySQL password in `config.php`, an HTML or JavaScript file, a shell command, a support ticket, or this documentation.

`config/database.local.php` is excluded by the repository's root `.gitignore` and **must never be committed or pushed to GitHub**. Keep its `.php` extension and use file permission `600` if DreamHost supports that permission for the PHP process. The application reports only `DB_PASSWORD is not configured.` when the file or password is missing and never displays the password.

For password-reset email, separately copy the root `config.local.example.php` to root `config.local.php` and enter `APP_URL` and `MAIL_FROM`; that file does not contain database credentials.

## 3. Prepare uploads

Upload `uploads/news/` with its `.htaccess`. Ensure the PHP process can write there (normally directory permission `755`; use the least permissive working setting). Confirm DreamHost honors `.htaccess` and has PHP Fileinfo enabled. The CMS accepts actual JPEG, PNG, and WEBP data up to 8 MB, generates random filenames, and rejects other MIME types.

## 4. Create the first superadmin

1. After importing the SQL and configuring the database, open **`https://YOUR-DOMAIN/setup/create-admin.php`**.
2. Choose the first NEWS admin username, enter the superadmin's unique email address, choose a password of at least 12 characters, confirm it, and click **Create Admin**.
3. The server hashes the password with `password_hash()` and automatically assigns `superadmin`. This password is separate from the MySQL password.
4. A successful submission creates `setup/.setup-complete` when filesystem permissions allow. The page also checks the database, so it refuses creation whenever any admin exists even without the lock.
5. Immediately **delete the complete `/setup/` directory from the production server** after success.

Empty values and mismatched or short passwords are rejected. Neither the password nor its hash is displayed.

## 5. Log in and manage NEWS

1. Open **`https://YOUR-DOMAIN/admin/`** and enter the NEWS administrator credentials. Login regenerates the session ID. Incorrect credentials receive one generic error.
2. Open **NEWS Posts**. Enter a title, normal multi-paragraph article text, publication date/time, and optionally an image. The separate compact toolbars set title and text size, bold, italic, underline, and alignment; text also supports justified alignment. The title and text fields preview these settings while editing, and reopening a post restores both the toolbar state and preview. **Default** size retains the original responsive site typography.
3. **Save Draft** keeps the post private. **Publish** makes it public at its intended publication time. Future-dated posts remain hidden until that time.
4. Existing-post **Edit** reloads its content. **Publish/Unpublish** changes visibility. **Delete** asks for confirmation and permanently removes the post and its CMS-owned image.
5. Public posts appear newest-first on `/news.php`. Drafts never appear. `/api/news.php` returns only required public fields.
6. Use **Log Out** to destroy the authenticated session.

All state changes use CSRF-protected POST requests. Database queries use native prepared statements, public/admin output is escaped, and protected pages check authentication server-side.

## 6. Create and manage additional administrators

Only a `superadmin` sees and may open **Manage Admins** (`/admin/manage-admins.php`):

1. Enter a unique username, a valid unique email address, password and confirmation, select `admin` or `superadmin`, then click **Create Admin**.
2. Use **Change password** to set and confirm a new password for an account; the old password is never shown or required.
3. Select a role and click **Change Role** to switch between `admin` and `superadmin`.
4. Click **Delete Admin** and confirm to remove another account.

A normal `admin` can fully manage NEWS but is denied administrator management server-side. A `superadmin` can do both. The signed-in account cannot delete itself, and transactional checks prevent deleting or demoting the last superadmin, ensuring the system always retains one.

## 7. Change your own password

Every signed-in `admin` and `superadmin` has an **Account** navigation link. Open it, enter the current password, a new password of at least 12 characters, and the same new password again. The server verifies the current password, rejects mismatched confirmation, and stores only a new `password_hash()` result. A successful change displays **Password changed successfully.** Superadmins use exactly the same Account page; no database access is needed.

All password-change submissions are CSRF protected. Passwords are handled only for the current request and are never logged, displayed, emailed, or stored as plain text.

## 8. Forgot password and reset email

The admin login page links to **Forgot password?**. Enter the administrator email address there. The page always gives the same neutral response after a validly formatted submission, whether or not that address belongs to an account, so it does not disclose administrator identities.

For a matching account, PHP generates a cryptographically random token. Only its SHA-256 hash is stored in `password_resets`; the raw token exists only in the emailed URL. The application uses DreamHost-compatible PHP `mail()` on the server with the private `MAIL_FROM` setting—no mail credential or reset data is sent to browser code. The plain-text message explains the request, includes the HTTPS reset URL, says the link expires in **30 minutes**, and says to ignore it if the request was unexpected. Confirm that DreamHost permits PHP mail for `MAIL_FROM`, and test delivery (including spam folders) after deployment.

The link opens `/admin/reset-password.php`, where the administrator enters and confirms a new password. The server accepts only a valid, unexpired, unused token belonging to an existing administrator, updates the password with `password_hash()`, and marks every outstanding token for that account used in the same database transaction. After success it displays **Your password has been reset successfully.** and links to login. The used link—and any other outstanding link for that account—cannot be reused.

## 9. Emergency recovery when email is unavailable

This is an **emergency fallback only**. First fix or check `APP_URL`, `MAIL_FROM`, DreamHost mail availability, and spam filtering. If a superadmin still cannot receive mail, use DreamHost SSH and phpMyAdmin as follows:

1. Over SSH, outside the public website directory, create a one-time PHP file that prompts for a new password at runtime and prints only its hash:

   ```php
   <?php
   // emergency-password-hash.php — one-time CLI use only; never place in the web root.
   if (PHP_SAPI !== 'cli') {
       http_response_code(404);
       exit;
   }
   fwrite(STDOUT, "New password (input is not stored): ");
   $password = rtrim((string) fgets(STDIN), "\r\n");
   if (strlen($password) < 12) {
       fwrite(STDERR, "Password must contain at least 12 characters.\n");
       exit(1);
   }
   fwrite(STDOUT, password_hash($password, PASSWORD_DEFAULT) . PHP_EOL);
   unset($password);
   ```

2. Run it once with `php emergency-password-hash.php`, enter a strong temporary password, and copy the resulting `$2y$...` hash. Terminal input may be visible while typing, so perform this privately and never put the plain password in a shell command, SQL statement, file, ticket, or chat.
3. In phpMyAdmin, update only the intended account: `UPDATE admins SET password_hash = 'THE_COPIED_HASH' WHERE username = 'THE_EXACT_SUPERADMIN_USERNAME';`. Verify that exactly one row changed. The database receives only the one-way hash—not the plain password.
4. Immediately delete the script with `rm emergency-password-hash.php`, clear the copied hash from the clipboard, log in, and change the temporary password again through **Account**.

Never type a plain-text password directly into the database. Login uses `password_verify()` against a `password_hash()` value; storing plain text both exposes the credential to anyone with database access and prevents normal verification from working. Never deploy this emergency script to a web-accessible directory or leave it behind for reuse.

## Remaining DreamHost configuration

- Import the appropriate SQL described above.
- Create `/config/database.local.php` on DreamHost and supply the real `password` privately; it was intentionally not provided, so the live connection cannot be tested from this repository. The host is already fixed as `mysql.lalehbarzegar.com`.
- Supply private `APP_URL` and `MAIL_FROM` values and test DreamHost PHP email delivery.
- Use a supported PHP release with PDO MySQL and Fileinfo.
- Ensure `uploads/news/` is writable and `.htaccess` rules are permitted.
- Serve the admin area over HTTPS so its session cookie receives the Secure flag.
- Delete `/setup/` immediately after creating the first superadmin.

No recurring database or source-code edits are needed to publish articles after installation.
