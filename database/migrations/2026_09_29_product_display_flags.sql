ALTER TABLE products
    ADD COLUMN IF NOT EXISTS is_customizable TINYINT(1) NOT NULL DEFAULT 0 AFTER featured_order,
    ADD COLUMN IF NOT EXISTS badge_text VARCHAR(80) NULL AFTER is_customizable;

