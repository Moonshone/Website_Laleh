-- Migration for an existing CMS installation. Back up the database first.
-- MySQL cannot invent valid, unique addresses for existing administrators, so:
-- 1. Run the first ALTER statement.
-- 2. Run one UPDATE per existing account with its real address, for example:
--      UPDATE admins SET email = 'owner@example.com' WHERE username = 'owner';
-- 3. Confirm there are no NULL or duplicate addresses, then run the second ALTER
--    and the CREATE TABLE statement.

ALTER TABLE admins ADD COLUMN email VARCHAR(255) NULL AFTER username;

-- Do not run this until every existing administrator has a valid, unique email.
ALTER TABLE admins MODIFY email VARCHAR(255) NOT NULL, ADD UNIQUE KEY admins_email_unique (email);

CREATE TABLE password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    used_at DATETIME NULL,
    CONSTRAINT password_resets_admin_fk FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    INDEX password_resets_lookup (token_hash, expires_at, used_at),
    INDEX password_resets_admin (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
