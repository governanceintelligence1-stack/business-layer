-- 010_create_api_usage_logs.sql
CREATE TABLE IF NOT EXISTS api_usage_logs (
    id            UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    api_key_id    UUID          NOT NULL REFERENCES api_keys(id) ON DELETE CASCADE,
    endpoint      VARCHAR(255)  NOT NULL,
    credits_used  NUMERIC(10,4) NOT NULL DEFAULT 0,
    response_code INTEGER       NOT NULL DEFAULT 200,
    created_at    TIMESTAMP     NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_api_usage_key_id     ON api_usage_logs(api_key_id);
CREATE INDEX IF NOT EXISTS idx_api_usage_created_at ON api_usage_logs(created_at DESC);
