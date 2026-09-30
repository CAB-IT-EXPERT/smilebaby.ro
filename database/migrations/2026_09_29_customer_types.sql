ALTER TABLE users
    ADD COLUMN IF NOT EXISTS customer_type ENUM('individual','company') NOT NULL DEFAULT 'individual' AFTER phone,
    ADD COLUMN IF NOT EXISTS company_name VARCHAR(190) NULL AFTER customer_type,
    ADD COLUMN IF NOT EXISTS company_vat_id VARCHAR(50) NULL AFTER company_name,
    ADD COLUMN IF NOT EXISTS company_registration_number VARCHAR(80) NULL AFTER company_vat_id,
    ADD COLUMN IF NOT EXISTS company_address VARCHAR(255) NULL AFTER company_registration_number;

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS customer_type ENUM('individual','company') NOT NULL DEFAULT 'individual' AFTER last_name,
    ADD COLUMN IF NOT EXISTS company_name VARCHAR(190) NULL AFTER customer_type,
    ADD COLUMN IF NOT EXISTS company_vat_id VARCHAR(50) NULL AFTER company_name,
    ADD COLUMN IF NOT EXISTS company_registration_number VARCHAR(80) NULL AFTER company_vat_id,
    ADD COLUMN IF NOT EXISTS company_address VARCHAR(255) NULL AFTER company_registration_number;
