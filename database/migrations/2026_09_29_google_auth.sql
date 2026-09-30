ALTER TABLE users
    ADD COLUMN IF NOT EXISTS google_subject VARCHAR(255) NULL AFTER phone,
    ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(500) NULL AFTER google_subject,
    ADD COLUMN IF NOT EXISTS auth_provider ENUM('password','google','both') NOT NULL DEFAULT 'password' AFTER avatar_url;

ALTER TABLE users ADD UNIQUE INDEX idx_users_google_subject (google_subject);

