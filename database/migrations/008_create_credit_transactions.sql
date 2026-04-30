-- 008_create_credit_transactions.sql
CREATE TABLE IF NOT EXISTS credit_transactions (
    id               UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    organisation_id  UUID NOT NULL REFERENCES organisations(id) ON DELETE CASCADE,
    type             VARCHAR(20)   NOT NULL,  -- credit | debit
    amount           NUMERIC(18,4) NOT NULL,
    balance_after    NUMERIC(18,4) NOT NULL,
    description      TEXT          NOT NULL,
    ref_type         VARCHAR(50),  -- payment | job | topup | subscription
    ref_id           VARCHAR(255),
    created_at       TIMESTAMP     NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_credit_tx_org_id     ON credit_transactions(organisation_id);
CREATE INDEX IF NOT EXISTS idx_credit_tx_created_at ON credit_transactions(created_at DESC);
