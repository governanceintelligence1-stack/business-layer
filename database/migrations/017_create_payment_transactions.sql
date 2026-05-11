-- 017_create_payment_transactions.sql
CREATE TABLE IF NOT EXISTS payment_transactions (
    id                 UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    organisation_id    UUID          NOT NULL REFERENCES organisations(id) ON DELETE CASCADE,
    user_id            UUID          REFERENCES users(id) ON DELETE SET NULL,
    plan_id            UUID          REFERENCES plans(id) ON DELETE SET NULL,
    payment_method_id  UUID          REFERENCES payment_methods(id) ON DELETE SET NULL,
    provider           VARCHAR(40)   NOT NULL DEFAULT 'payfast',
    provider_ref       VARCHAR(255)  NOT NULL UNIQUE,
    amount             NUMERIC(12,2) NOT NULL,
    currency           VARCHAR(5)    NOT NULL DEFAULT 'ZAR',
    status             VARCHAR(20)   NOT NULL DEFAULT 'pending', -- pending | paid | failed | cancelled
    raw_payload        JSONB         NOT NULL DEFAULT '{}'::jsonb,
    activated_at       TIMESTAMP,
    created_at         TIMESTAMP     NOT NULL DEFAULT NOW(),
    updated_at         TIMESTAMP     NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_payment_tx_org_id ON payment_transactions(organisation_id);
CREATE INDEX IF NOT EXISTS idx_payment_tx_plan_id ON payment_transactions(plan_id);
CREATE INDEX IF NOT EXISTS idx_payment_tx_status ON payment_transactions(status);
