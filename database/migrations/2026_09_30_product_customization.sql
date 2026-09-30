ALTER TABLE products
    ADD COLUMN customization_price DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER is_customizable;

CREATE TABLE IF NOT EXISTS product_customization_fields (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(150) NOT NULL,
    field_type ENUM('text','date') NOT NULL DEFAULT 'text',
    placeholder VARCHAR(190) NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customization_product (product_id, sort_order, id),
    CONSTRAINT fk_customization_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE order_items
    ADD COLUMN customization_json TEXT NULL AFTER variant_name,
    ADD COLUMN customization_price DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER customization_json;

INSERT INTO product_customization_fields
    (product_id, label, field_type, placeholder, is_required, sort_order)
SELECT
    p.id,
    'Numele copilului',
    'text',
    'Ex.: Maria',
    1,
    COALESCE((SELECT MAX(existing.sort_order) + 1 FROM product_customization_fields existing WHERE existing.product_id=p.id), 0)
FROM products p
WHERE p.is_customizable=1
  AND NOT EXISTS (
      SELECT 1 FROM product_customization_fields duplicate
      WHERE duplicate.product_id=p.id AND LOWER(TRIM(duplicate.label))=LOWER('Numele copilului')
  );

INSERT INTO product_customization_fields
    (product_id, label, field_type, placeholder, is_required, sort_order)
SELECT
    p.id,
    'Data botezului/nașterii',
    'date',
    NULL,
    1,
    COALESCE((SELECT MAX(existing.sort_order) + 1 FROM product_customization_fields existing WHERE existing.product_id=p.id), 0)
FROM products p
WHERE p.is_customizable=1
  AND NOT EXISTS (
      SELECT 1 FROM product_customization_fields duplicate
      WHERE duplicate.product_id=p.id AND LOWER(TRIM(duplicate.label))=LOWER('Data botezului/nașterii')
  );

INSERT INTO settings (`key`, value, type, group_name)
VALUES ('product_customization_defaults_v1', '1', 'boolean', 'system')
ON DUPLICATE KEY UPDATE value='1', type='boolean', group_name='system';

INSERT INTO product_customization_fields
    (product_id, label, field_type, placeholder, is_required, sort_order)
SELECT
    p.id,
    'Mesaj personalizat',
    'text',
    'Scrie mesajul dorit',
    0,
    COALESCE((SELECT MAX(existing.sort_order) + 1 FROM product_customization_fields existing WHERE existing.product_id=p.id), 0)
FROM products p
WHERE p.is_customizable=1
  AND NOT EXISTS (
      SELECT 1 FROM product_customization_fields duplicate
      WHERE duplicate.product_id=p.id AND LOWER(TRIM(duplicate.label))=LOWER('Mesaj personalizat')
  );

INSERT INTO settings (`key`, value, type, group_name)
VALUES ('product_customization_message_default_v1', '1', 'boolean', 'system')
ON DUPLICATE KEY UPDATE value='1', type='boolean', group_name='system';
