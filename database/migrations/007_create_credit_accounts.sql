-- 007_create_credit_accounts.sql
CREATE TABLE IF NOT EXISTS credit_accounts (
    id               UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    organisation_id  UUID NOT NULL UNIQUE REFERENCES organisations(id) ON DELETE CASCADE,
    balance          NUMERIC(18,4) NOT NULL DEFAULT 0,
    reserved         NUMERIC(18,4) NOT NULL DEFAULT 0,
    updated_at       TIMESTAMP     NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_credit_accounts_org ON credit_accounts(organisation_id);
