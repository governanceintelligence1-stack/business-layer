-- 002_create_users.sql
CREATE TABLE IF NOT EXISTS users (
    id               UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    organisation_id  UUID REFERENCES organisations(id) ON DELETE SET NULL,
    keycloak_id      VARCHAR(255) UNIQUE,
    email            VARCHAR(255) NOT NULL UNIQUE,
    first_name       VARCHAR(100),
    last_name        VARCHAR(100),
    role             VARCHAR(50)  NOT NULL DEFAULT 'member',  -- admin | member | viewer
    status           VARCHAR(20)  NOT NULL DEFAULT 'active',  -- active | pending | suspended
    created_at       TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at       TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_users_keycloak_id      ON users(keycloak_id);
CREATE INDEX IF NOT EXISTS idx_users_email             ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_organisation_id   ON users(organisation_id);
