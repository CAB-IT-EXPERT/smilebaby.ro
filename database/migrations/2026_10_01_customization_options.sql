ALTER TABLE products ADD COLUMN IF NOT EXISTS customization_help_text VARCHAR(500) NULL AFTER customization_price;

CREATE TABLE IF NOT EXISTS product_customization_options (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(150) NOT NULL,
    price DECIMAL(12,2) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_customization_option_label (product_id, label),
    INDEX idx_customization_options_product (product_id, sort_order, id),
    CONSTRAINT fk_customization_options_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
