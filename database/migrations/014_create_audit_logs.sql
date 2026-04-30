-- 014_create_audit_logs.sql
CREATE TABLE IF NOT EXISTS audit_logs (
    id               UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    organisation_id  UUID,
    user_id          UUID,
    action           VARCHAR(100) NOT NULL,
    resource_type    VARCHAR(100),
    resource_id      VARCHAR(255),
    meta             JSONB        DEFAULT '{}'::jsonb,
    ip_address       INET,
    user_agent       TEXT,
    created_at       TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_audit_org_id     ON audit_logs(organisation_id);
CREATE INDEX IF NOT EXISTS idx_audit_user_id    ON audit_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_action     ON audit_logs(action);
CREATE INDEX IF NOT EXISTS idx_audit_created_at ON audit_logs(created_at DESC);
