-- Migration for an existing CMS installation. Back up the database first.
-- Run this statement once before deploying the corresponding PHP changes.

ALTER TABLE admins
    ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER password_hash;
