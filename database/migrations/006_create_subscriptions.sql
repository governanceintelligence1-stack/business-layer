-- 006_create_subscriptions.sql
CREATE TABLE IF NOT EXISTS subscriptions (
    id                   UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    organisation_id      UUID NOT NULL REFERENCES organisations(id) ON DELETE CASCADE,
    plan_id              UUID NOT NULL REFERENCES plans(id),
    billing_cycle        VARCHAR(20)  NOT NULL DEFAULT 'monthly', -- monthly | annual
    status               VARCHAR(20)  NOT NULL DEFAULT 'active',  -- active | cancelled | expired | past_due
    current_period_start TIMESTAMP    NOT NULL DEFAULT NOW(),
    current_period_end   TIMESTAMP    NOT NULL,
    cancelled_at         TIMESTAMP,
    created_at           TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at           TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_subscriptions_org_id ON subscriptions(organisation_id);
CREATE INDEX IF NOT EXISTS idx_subscriptions_status ON subscriptions(status);
