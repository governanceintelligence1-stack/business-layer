-- plans_seed.sql
UPDATE plans
SET
  name = 'Starter',
  description = 'Perfect for small teams and experimental projects.',
  price_monthly = 450.00,
  price_annual = 4500.00,
  credits_monthly = 1000,
  max_users = 3,
  max_api_keys = 2,
  features = '["1,000 Monthly Credits","Up to 3 team members","2 API keys","Governance Analytics","ESG Scoring","Standard email support"]'::jsonb,
  status = 'active',
  updated_at = NOW()
WHERE slug = 'starter';

INSERT INTO plans (name, slug, description, price_monthly, price_annual, credits_monthly, max_users, max_api_keys, features, status)
SELECT
  'Starter',
  'starter',
  'Perfect for small teams and experimental projects.',
  450.00, 4500.00, 1000, 3, 2,
  '["1,000 Monthly Credits","Up to 3 team members","2 API keys","Governance Analytics","ESG Scoring","Standard email support"]'::jsonb,
  'active'
WHERE NOT EXISTS (SELECT 1 FROM plans WHERE slug = 'starter');

UPDATE plans
SET
  name = 'Business Pro',
  description = 'Advanced features and higher limits for growing businesses.',
  price_monthly = 1250.00,
  price_annual = 12500.00,
  credits_monthly = 5000,
  max_users = 15,
  max_api_keys = 8,
  features = '["5,000 Monthly Credits","Up to 15 team members","8 API keys","All 6 intelligence products","Priority support","Advanced reporting","Webhook integrations"]'::jsonb,
  status = 'active',
  updated_at = NOW()
WHERE slug = 'professional';

INSERT INTO plans (name, slug, description, price_monthly, price_annual, credits_monthly, max_users, max_api_keys, features, status)
SELECT
  'Business Pro',
  'professional',
  'Advanced features and higher limits for growing businesses.',
  1250.00, 12500.00, 5000, 15, 8,
  '["5,000 Monthly Credits","Up to 15 team members","8 API keys","All 6 intelligence products","Priority support","Advanced reporting","Webhook integrations"]'::jsonb,
  'active'
WHERE NOT EXISTS (SELECT 1 FROM plans WHERE slug = 'professional');

UPDATE plans
SET
  name = 'Enterprise',
  description = 'Maximum performance and dedicated support for large scale organisations.',
  price_monthly = 4500.00,
  price_annual = 45000.00,
  credits_monthly = 25000,
  max_users = 999,
  max_api_keys = 50,
  features = '["25,000 Monthly Credits","Unlimited users","50 API keys","All products + early access","Dedicated account manager","24/7 SLA support","Custom integrations"]'::jsonb,
  status = 'active',
  updated_at = NOW()
WHERE slug = 'enterprise';

INSERT INTO plans (name, slug, description, price_monthly, price_annual, credits_monthly, max_users, max_api_keys, features, status)
SELECT
  'Enterprise',
  'enterprise',
  'Maximum performance and dedicated support for large scale organisations.',
  4500.00, 45000.00, 25000, 999, 50,
  '["25,000 Monthly Credits","Unlimited users","50 API keys","All products + early access","Dedicated account manager","24/7 SLA support","Custom integrations"]'::jsonb,
  'active'
WHERE NOT EXISTS (SELECT 1 FROM plans WHERE slug = 'enterprise');

-- Link all products to Professional and Enterprise plans
INSERT INTO plan_products (plan_id, product_id)
SELECT p.id, pr.id
FROM plans p, products pr
WHERE p.slug IN ('professional', 'enterprise')
  AND NOT EXISTS (
    SELECT 1
    FROM plan_products pp
    WHERE pp.plan_id = p.id
      AND pp.product_id = pr.id
  );

-- Link starter products (governance-analytics + esg-scoring only)
INSERT INTO plan_products (plan_id, product_id)
SELECT p.id, pr.id
FROM plans p, products pr
WHERE p.slug = 'starter'
  AND pr.slug IN ('governance-analytics', 'esg-scoring')
  AND NOT EXISTS (
    SELECT 1
    FROM plan_products pp
    WHERE pp.plan_id = p.id
      AND pp.product_id = pr.id
  );
