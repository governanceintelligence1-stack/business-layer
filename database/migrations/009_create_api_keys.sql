-- 009_create_api_keys.sql
CREATE TABLE IF NOT EXISTS api_keys (
    id               UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    organisation_id  UUID         NOT NULL REFERENCES organisations(id) ON DELETE CASCADE,
    user_id          UUID         REFERENCES users(id) ON DELETE SET NULL,
    product_id       UUID         REFERENCES products(id) ON DELETE SET NULL,
    name             VARCHAR(255) NOT NULL,
    key_hash         VARCHAR(64)  NOT NULL UNIQUE,
    key_prefix       VARCHAR(20)  NOT NULL,
    status           VARCHAR(20)  NOT NULL DEFAULT 'active',  -- active | revoked
    last_used_at     TIMESTAMP,
    created_at       TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_api_keys_org_id   ON api_keys(organisation_id);
CREATE INDEX IF NOT EXISTS idx_api_keys_key_hash ON api_keys(key_hash);
CREATE INDEX IF NOT EXISTS idx_api_keys_status   ON api_keys(status);
