ALTER TABLE products
    ADD COLUMN IF NOT EXISTS addons_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER badge_text;

CREATE TABLE IF NOT EXISTS product_addons (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    addon_product_id BIGINT UNSIGNED NOT NULL,
    custom_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_product_addon (product_id,addon_product_id),
    INDEX idx_product_addons (product_id,sort_order,id),
    CONSTRAINT fk_addon_parent FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_addon_product FOREIGN KEY (addon_product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE order_items
    ADD COLUMN IF NOT EXISTS addons_json MEDIUMTEXT NULL AFTER customization_price,
    ADD COLUMN IF NOT EXISTS addons_total DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER addons_json;
