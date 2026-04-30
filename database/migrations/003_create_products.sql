-- 003_create_products.sql
CREATE TABLE IF NOT EXISTS products (
    id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name        VARCHAR(255) NOT NULL,
    slug        VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    icon        VARCHAR(20)  DEFAULT '📊',
    credit_cost NUMERIC(10,4) NOT NULL DEFAULT 1.0,
    status      VARCHAR(20)  NOT NULL DEFAULT 'active',
    created_at  TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_products_slug   ON products(slug);
CREATE INDEX IF NOT EXISTS idx_products_status ON products(status);
