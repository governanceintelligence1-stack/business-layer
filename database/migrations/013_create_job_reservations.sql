-- 013_create_job_reservations.sql
CREATE TABLE IF NOT EXISTS job_reservations (
    id                UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    organisation_id   UUID          NOT NULL REFERENCES organisations(id) ON DELETE CASCADE,
    job_id            VARCHAR(255)  NOT NULL UNIQUE,
    reserved_credits  NUMERIC(18,4) NOT NULL DEFAULT 0,
    actual_credits    NUMERIC(18,4),
    status            VARCHAR(20)   NOT NULL DEFAULT 'reserved',  -- reserved | finalized | released
    finalized_at      TIMESTAMP,
    created_at        TIMESTAMP     NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_job_res_org_id ON job_reservations(organisation_id);
CREATE INDEX IF NOT EXISTS idx_job_res_job_id ON job_reservations(job_id);
CREATE INDEX IF NOT EXISTS idx_job_res_status ON job_reservations(status);
