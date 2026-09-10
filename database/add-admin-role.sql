-- Run this only when an existing `admins` table does not yet have a `role` column.
-- It preserves every existing account. Promote one trusted account immediately
-- after running it, before deploying the role-aware CMS.
ALTER TABLE admins
    ADD COLUMN role ENUM('superadmin', 'admin') NOT NULL DEFAULT 'admin' AFTER password_hash;

-- Preserve a usable system by making the oldest existing account the first superadmin.
UPDATE admins SET role = 'superadmin' ORDER BY id ASC LIMIT 1;
