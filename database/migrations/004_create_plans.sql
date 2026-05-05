-- 004_create_plans.sql
CREATE TABLE IF NOT EXISTS plans (
    id               UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name             VARCHAR(255)  NOT NULL,
    slug             VARCHAR(255)  NOT NULL UNIQUE,
    description      TEXT,
    price_monthly    NUMERIC(12,2) NOT NULL DEFAULT 0,
    price_annual     NUMERIC(12,2),
    credits_monthly  INTEGER       NOT NULL DEFAULT 0,
    max_users        INTEGER       NOT NULL DEFAULT 5,
    max_api_keys     INTEGER       NOT NULL DEFAULT 3,
    features         JSONB         DEFAULT '[]'::jsonb,
    status           VARCHAR(20)   NOT NULL DEFAULT 'active',
    created_at       TIMESTAMP     NOT NULL DEFAULT NOW(),
    updated_at       TIMESTAMP     NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_plans_slug   ON plans(slug);
CREATE INDEX IF NOT EXISTS idx_plans_status ON plans(status);
