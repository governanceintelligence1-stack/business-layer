-- 011_create_billing_invoices.sql
CREATE TABLE IF NOT EXISTS billing_invoices (
    id               UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    organisation_id  UUID          NOT NULL REFERENCES organisations(id) ON DELETE CASCADE,
    invoice_number   VARCHAR(50)   NOT NULL UNIQUE,
    amount_total     NUMERIC(12,2) NOT NULL DEFAULT 0,
    currency         VARCHAR(5)    NOT NULL DEFAULT 'ZAR',
    status           VARCHAR(20)   NOT NULL DEFAULT 'pending',  -- pending | paid | overdue | voided
    due_date         DATE,
    paid_at          TIMESTAMP,
    notes            TEXT,
    created_at       TIMESTAMP     NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_invoices_org_id ON billing_invoices(organisation_id);
CREATE INDEX IF NOT EXISTS idx_invoices_status ON billing_invoices(status);
