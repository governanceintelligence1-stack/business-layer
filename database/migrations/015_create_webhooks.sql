-- 015_create_webhooks.sql
CREATE TABLE IF NOT EXISTS webhooks (
    id               UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    organisation_id  UUID         REFERENCES organisations(id) ON DELETE CASCADE,
    event_type       VARCHAR(100) NOT NULL,
    payload          JSONB        NOT NULL DEFAULT '{}'::jsonb,
    status           VARCHAR(20)  NOT NULL DEFAULT 'pending',  -- pending | processed | failed
    attempts         INTEGER      NOT NULL DEFAULT 0,
    last_error       TEXT,
    processed_at     TIMESTAMP,
    created_at       TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_webhooks_org_id     ON webhooks(organisation_id);
CREATE INDEX IF NOT EXISTS idx_webhooks_event_type ON webhooks(event_type);
CREATE INDEX IF NOT EXISTS idx_webhooks_status     ON webhooks(status);
CREATE INDEX IF NOT EXISTS idx_webhooks_created_at ON webhooks(created_at DESC);
