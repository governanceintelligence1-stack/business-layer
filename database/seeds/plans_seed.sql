-- plans_seed.sql
INSERT INTO plans (name, slug, description, price_monthly, price_annual, credits_monthly, max_users, max_api_keys, features, status) VALUES
(
  'Starter',
  'starter',
  'Perfect for small teams getting started with governance intelligence.',
  1500.00, 15000.00, 5000, 5, 3,
  '["Up to 5 users","3 API keys","Governance Analytics access","ESG Scoring access","Email support","Standard API rate limits"]'::jsonb,
  'active'
),
(
  'Professional',
  'professional',
  'For growing organisations needing comprehensive governance coverage.',
  4500.00, 45000.00, 20000, 25, 10,
  '["Up to 25 users","10 API keys","All 6 products included","Priority support","Advanced reporting","Webhook integrations","Higher API rate limits"]'::jsonb,
  'active'
),
(
  'Enterprise',
  'enterprise',
  'Custom solutions and dedicated support for large organisations.',
  12000.00, 120000.00, 100000, 999, 50,
  '["Unlimited users","50 API keys","All products + early access","Dedicated account manager","SLA support","Custom integrations","Unlimited API rate limits","On-premise deployment option"]'::jsonb,
  'active'
)
ON CONFLICT (slug) DO NOTHING;

-- Link all products to Professional and Enterprise plans
INSERT INTO plan_products (plan_id, product_id)
SELECT p.id, pr.id
FROM plans p, products pr
WHERE p.slug IN ('professional', 'enterprise')
ON CONFLICT DO NOTHING;

-- Link starter products (governance-analytics + esg-scoring only)
INSERT INTO plan_products (plan_id, product_id)
SELECT p.id, pr.id
FROM plans p, products pr
WHERE p.slug = 'starter' AND pr.slug IN ('governance-analytics', 'esg-scoring')
ON CONFLICT DO NOTHING;
