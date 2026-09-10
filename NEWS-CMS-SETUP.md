# NEWS CMS setup on DreamHost

The CMS uses PHP, PDO, MySQL, PHP sessions, and the existing site styles. It does not require Node.js or ongoing programming. The public page is `news.php`; administration starts at `/admin/`.

## Files created and changed

- `config.php` loads private environment variables or `config.local.php` and creates the PDO connection.
- `config.local.example.php` is a safe template containing no password.
- `database/schema.sql` creates the complete `admins` and `news_posts` tables on a new installation.
- `database/add-admin-role.sql` safely adds roles to a pre-existing role-less `admins` table and makes its oldest account a superadmin. Run this migration **only if** that table already exists without `role`; do not run it after `schema.sql`.
- `setup/create-admin.php` creates only the first administrator; `setup/.gitignore` keeps its generated lock private.
- `admin/` contains login, logout, dashboard, NEWS editing, administrator management, authentication, authorization, CSRF, and upload code.
- `api/news.php` is the read-only published NEWS JSON endpoint.
- `news.php` is the public NEWS page in the existing design.
- `uploads/news/.htaccess` blocks script execution; `.gitignore` excludes uploaded media.
- `styles/style.css` contains the related public NEWS and administration styles.

No unrelated page, image filename, menu behavior, gallery, or contact code is changed by this setup.

## 1. Import the database SQL

The existing database is **`neweshtaniha`** and its MySQL username is **`laleh`**.

For a new CMS installation, open DreamHost phpMyAdmin for `neweshtaniha`, select **Import**, choose `database/schema.sql`, and run it. This creates:

- `admins`: unique username, one-way `password_hash`, `superadmin`/`admin` role, and creation time.
- `news_posts`: title, long article content, optional public image path, draft/published status, publication date, and timestamps.

`CREATE TABLE IF NOT EXISTS` does not erase existing data. If an older `admins` table already exists but lacks `role`, import `database/add-admin-role.sql` instead. It preserves accounts and promotes the oldest one. Never run that migration if `role` already exists.

## 2. Enter the two private DreamHost values

The preferred method is to define server-side environment variables in DreamHost/PHP:

```text
DB_HOST=the exact MySQL hostname shown in the DreamHost panel
DB_PORT=3306
DB_NAME=neweshtaniha
DB_USER=laleh
DB_PASSWORD=your real MySQL password
```

If the hosting configuration cannot provide environment variables, copy `config.local.example.php` to **`config.local.php` in the website root, directly beside `config.php`**. In that private copy only:

- enter the exact DreamHost MySQL hostname as the value of `DB_HOST`;
- enter the MySQL user's real password as the value of `DB_PASSWORD`.

`config.local.php` is excluded by the root `.gitignore`. Keep its `.php` extension and use file permission `600` if supported. Never commit it or put either value in HTML, JavaScript, the API, or a public text file. A leaked database password permits unauthorized data access. The committed application deliberately does not guess a hostname or password.

## 3. Prepare uploads

Upload `uploads/news/` with its `.htaccess`. Ensure the PHP process can write there (normally directory permission `755`; use the least permissive working setting). Confirm DreamHost honors `.htaccess` and has PHP Fileinfo enabled. The CMS accepts actual JPEG, PNG, and WEBP data up to 8 MB, generates random filenames, and rejects other MIME types.

## 4. Create the first superadmin

1. After importing the SQL and configuring the database, open **`https://YOUR-DOMAIN/setup/create-admin.php`**.
2. Choose the first NEWS admin username and a password of at least 12 characters, confirm it, and click **Create Admin**.
3. The server hashes the password with `password_hash()` and automatically assigns `superadmin`. This password is separate from the MySQL password.
4. A successful submission creates `setup/.setup-complete` when filesystem permissions allow. The page also checks the database, so it refuses creation whenever any admin exists even without the lock.
5. Immediately **delete the complete `/setup/` directory from the production server** after success.

Empty values and mismatched or short passwords are rejected. Neither the password nor its hash is displayed.

## 5. Log in and manage NEWS

1. Open **`https://YOUR-DOMAIN/admin/`** and enter the NEWS administrator credentials. Login regenerates the session ID. Incorrect credentials receive one generic error.
2. Open **NEWS Posts**. Enter a title, normal multi-paragraph article text, publication date/time, and optionally an image.
3. **Save Draft** keeps the post private. **Publish** makes it public at its intended publication time. Future-dated posts remain hidden until that time.
4. Existing-post **Edit** reloads its content. **Publish/Unpublish** changes visibility. **Delete** asks for confirmation and permanently removes the post and its CMS-owned image.
5. Public posts appear newest-first on `/news.php`. Drafts never appear. `/api/news.php` returns only required public fields.
6. Use **Log Out** to destroy the authenticated session.

All state changes use CSRF-protected POST requests. Database queries use native prepared statements, public/admin output is escaped, and protected pages check authentication server-side.

## 6. Create and manage additional administrators

Only a `superadmin` sees and may open **Manage Admins** (`/admin/manage-admins.php`):

1. Enter a unique username, password and confirmation, select `admin` or `superadmin`, then click **Create Admin**.
2. Use **Change password** to set and confirm a new password for an account; the old password is never shown or required.
3. Select a role and click **Change Role** to switch between `admin` and `superadmin`.
4. Click **Delete Admin** and confirm to remove another account.

A normal `admin` can fully manage NEWS but is denied administrator management server-side. A `superadmin` can do both. The signed-in account cannot delete itself, and transactional checks prevent deleting or demoting the last superadmin, ensuring the system always retains one.

## Remaining DreamHost configuration

- Import the appropriate SQL described above.
- Supply the real `DB_HOST` and `DB_PASSWORD` privately; these were intentionally not provided and therefore the live connection cannot be tested from this repository.
- Use a supported PHP release with PDO MySQL and Fileinfo.
- Ensure `uploads/news/` is writable and `.htaccess` rules are permitted.
- Serve the admin area over HTTPS so its session cookie receives the Secure flag.
- Delete `/setup/` immediately after creating the first superadmin.

No recurring database or source-code edits are needed to publish articles after installation.
