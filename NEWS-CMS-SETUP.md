# NEWS CMS setup on DreamHost

This CMS uses PHP, PDO, MySQL, and the existing website styles. The public NEWS page remains `news.php`; the administration starts at `/admin/`.

## Files

The CMS consists of:

- `config.php`: server-only PDO connection loader.
- `config.local.example.php`: safe configuration template with no password.
- `database/schema.sql`: creates `admins` and `news_posts`.
- `admin/index.php`, `admin/login.php`, `admin/logout.php`, `admin/news.php`, and `admin/bootstrap.php`: login, session protection, editor, post management, CSRF checks, and upload handling.
- `admin/create_admin.php`: command-line-only first-admin tool (web requests receive 404).
- `api/news.php`: JSON endpoint containing published posts only.
- `uploads/news/.htaccess`: prevents uploaded files from being executed as scripts on Apache-compatible hosting.
- `news.php`: the public NEWS page, using the site's existing design.

## 1. Create the database tables

The database name is **`neweshtaniha`** and the MySQL username is **`laleh`**. In the DreamHost panel, open phpMyAdmin for `neweshtaniha`, select the **Import** tab, select `database/schema.sql`, and run the import. It creates:

- `admins`: administrator usernames and one-way hashes created with `password_hash()`.
- `news_posts`: titles, long article text, optional image paths, draft/published status, publication dates, and timestamps.

No MySQL password or NEWS administrator password is included in the SQL file.

## 2. Add the private database settings

The preferred method is DreamHost server environment variables named `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`. Set:

```text
DB_HOST=the exact MySQL hostname shown in the DreamHost panel
DB_PORT=3306
DB_NAME=neweshtaniha
DB_USER=laleh
DB_PASSWORD=your real MySQL password
```

If environment variables are not available on the hosting plan, copy `config.local.example.php` to **`config.local.php` in the website root, beside `config.php`**. Enter the DreamHost MySQL hostname at `DB_HOST` and the MySQL password at `DB_PASSWORD` in that private copy. `config.local.php` is ignored by Git. Leave the committed example password blank. Since the private file sits in the served tree, keep its `.php` extension, use permissions `600` if DreamHost permits, and never rename it to `.txt`.

Do not put either value in HTML, browser JavaScript, `api/news.php`, or GitHub. `config.php` deliberately has no `localhost` fallback. It uses `neweshtaniha` and `laleh` as safe defaults, while host and password must be supplied privately.

## 3. Make the upload directory writable

Upload `uploads/news/`, including its `.htaccess`. Through DreamHost's file manager or SSH, give the directory permissions that allow the site PHP process to write to it (normally `755`; use the least permissive setting that works). Do not grant executable permission to uploaded files.

## 4. Create the first NEWS administrator

The NEWS administrator login is separate from the MySQL login. Choose a unique username and a password of at least 12 characters. From SSH, change to the website root and run:

```bash
php admin/create_admin.php 'your-admin-username'
```

The command securely prompts for the password without displaying it or putting it in shell history. The password is converted with `password_hash()` before insertion; plain text is not stored. The script only runs from the command line and returns 404 through the web, so there is no temporary setup page to disable. You may delete `admin/create_admin.php` from the production server after creating the account for additional defense; keep or delete it locally as desired.

## 5. Use the CMS

1. Open **`https://YOUR-DOMAIN/admin/`**. Logged-out visitors are sent to the login screen.
2. Enter the NEWS administrator username and password created above. A successful login regenerates the PHP session ID.
3. Enter a title, multi-paragraph text, and a publication date/time. An image is optional.
4. Upload only JPG/JPEG, PNG, or WEBP images, up to 8 MB. The server checks actual MIME type and creates a random filename; it never trusts the original name.
5. Click **SAVE DRAFT** to keep the article private. Drafts never appear publicly.
6. Click **PUBLISH** to publish it. Published posts appear newest-first on `news.php` (future-dated posts remain hidden until their date).
7. Under **Existing posts**, click **EDIT** to load an article into the editor. Saving it as a draft unpublishes it; publishing saves it as published.
8. Use **PUBLISH** or **UNPUBLISH** beside an existing post for a quick status change.
9. Click **DELETE** and accept the browser confirmation to permanently delete a post and its uploaded image. Deletion is a CSRF-protected POST request.
10. Click **LOG OUT** to destroy the authenticated session.

The read-only endpoint is `/api/news.php`. It returns only the public post fields for currently published posts, never drafts, admin records, hashes, or database settings.

## DreamHost checklist

- Confirm the domain is running a currently supported PHP version with PDO MySQL and Fileinfo enabled.
- Import `database/schema.sql` into `neweshtaniha`.
- Supply the exact DreamHost MySQL hostname and password using environment variables or private `config.local.php` as described above.
- Ensure PHP can write to `uploads/news/` and that DreamHost honors its `.htaccess` rules.
- Use HTTPS so the admin session cookie is marked Secure.
- No Node.js, Docker, cron job, or change to the existing DreamHost MySQL user is required.

The live database connection cannot be verified until the private `DB_HOST` and `DB_PASSWORD` are supplied.
