-- Run once on an existing installation. NULL font sizes preserve the site's
-- responsive legacy title size and the existing 16px body size.
ALTER TABLE news_posts
    ADD COLUMN title_font_size TINYINT UNSIGNED NULL DEFAULT NULL AFTER content,
    ADD COLUMN title_bold BOOLEAN NOT NULL DEFAULT FALSE AFTER title_font_size,
    ADD COLUMN title_italic BOOLEAN NOT NULL DEFAULT FALSE AFTER title_bold,
    ADD COLUMN title_underline BOOLEAN NOT NULL DEFAULT FALSE AFTER title_italic,
    ADD COLUMN title_alignment ENUM('left', 'center', 'right', 'justify') NOT NULL DEFAULT 'left' AFTER title_underline,
    ADD COLUMN text_font_size TINYINT UNSIGNED NULL DEFAULT NULL AFTER title_alignment,
    ADD COLUMN text_bold BOOLEAN NOT NULL DEFAULT FALSE AFTER text_font_size,
    ADD COLUMN text_italic BOOLEAN NOT NULL DEFAULT FALSE AFTER text_bold,
    ADD COLUMN text_underline BOOLEAN NOT NULL DEFAULT FALSE AFTER text_italic,
    ADD COLUMN text_alignment ENUM('left', 'center', 'right', 'justify') NOT NULL DEFAULT 'left' AFTER text_underline;
