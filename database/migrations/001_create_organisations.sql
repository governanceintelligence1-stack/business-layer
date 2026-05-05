-- 001_create_organisations.sql
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

CREATE TABLE IF NOT EXISTS organisations (
    id           UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name         VARCHAR(255)        NOT NULL,
    slug         VARCHAR(255)        NOT NULL UNIQUE,
    account_type VARCHAR(50)         NOT NULL DEFAULT 'company',
    phone        VARCHAR(50),
    country      VARCHAR(10)         DEFAULT 'ZA',
    status       VARCHAR(20)         NOT NULL DEFAULT 'active',
    created_at   TIMESTAMP           NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMP           NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_organisations_slug   ON organisations(slug);
CREATE INDEX IF NOT EXISTS idx_organisations_status ON organisations(status);
