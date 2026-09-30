ALTER TABLE products
    ADD COLUMN IF NOT EXISTS stripe_product_id VARCHAR(100) NULL AFTER indexable,
    ADD COLUMN IF NOT EXISTS stripe_price_id VARCHAR(100) NULL AFTER stripe_product_id,
    ADD COLUMN IF NOT EXISTS stripe_price_amount INT NULL AFTER stripe_price_id,
    ADD COLUMN IF NOT EXISTS stripe_synced_at DATETIME NULL AFTER stripe_price_amount,
    ADD COLUMN IF NOT EXISTS stripe_sync_error VARCHAR(500) NULL AFTER stripe_synced_at;

SET @stripe_product_index_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND INDEX_NAME='uq_products_stripe_product'
);
SET @stripe_product_index_sql = IF(@stripe_product_index_exists=0,
    'ALTER TABLE products ADD UNIQUE KEY uq_products_stripe_product (stripe_product_id)',
    'SELECT 1');
PREPARE stripe_product_index_statement FROM @stripe_product_index_sql;
EXECUTE stripe_product_index_statement;
DEALLOCATE PREPARE stripe_product_index_statement;

INSERT INTO payment_methods (`key`,name,description,enabled,sort_order,fee_type,fee_value,instructions,settings_json)
VALUES ('online_card','Plată online cu cardul','Card, Apple Pay, Google Pay sau Revolut Pay, procesate securizat prin Stripe.',1,2,'none',0,'','{"provider":"stripe","test_mode":true}')
ON DUPLICATE KEY UPDATE
    name=VALUES(name),description=VALUES(description),enabled=1,settings_json=VALUES(settings_json);
