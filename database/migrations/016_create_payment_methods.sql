-- 016_create_payment_methods.sql
CREATE TABLE IF NOT EXISTS payment_methods (
    id               UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    organisation_id  UUID         NOT NULL REFERENCES organisations(id) ON DELETE CASCADE,
    user_id          UUID         REFERENCES users(id) ON DELETE SET NULL,
    brand            VARCHAR(40)  NOT NULL DEFAULT 'Card',
    last4            VARCHAR(4)   NOT NULL,
    expiry_month     VARCHAR(2),
    expiry_year      VARCHAR(4),
    cardholder_name  VARCHAR(255),
    token            VARCHAR(255),
    is_default       BOOLEAN      NOT NULL DEFAULT false,
    status           VARCHAR(20)  NOT NULL DEFAULT 'active',
    created_at       TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at       TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_payment_methods_org_id ON payment_methods(organisation_id);
CREATE INDEX IF NOT EXISTS idx_payment_methods_user_id ON payment_methods(user_id);
CREATE INDEX IF NOT EXISTS idx_payment_methods_default ON payment_methods(organisation_id, is_default);
