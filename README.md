# Laleh Barzegar website

## NEWS setup

The NEWS feature requires PHP 8.1 or newer with PDO MySQL and Fileinfo, plus MySQL 8/MariaDB. Configure the database through `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` environment variables.

1. Import `database/schema.sql` into the configured database.
2. Make `uploads/news/` writable by the PHP web-server user.
3. Create an administrator from the command line (the script securely prompts for a password of at least 12 characters):

   ```bash
   php admin/create_admin.php username
   ```

4. Sign in at `admin/login.php`. No default or hard-coded web password is provided.

Serve the site with the document root set to this repository. In production, use HTTPS and keep database credentials in the server environment rather than in the repository.
