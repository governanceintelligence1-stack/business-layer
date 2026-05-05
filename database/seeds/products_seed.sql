-- products_seed.sql
INSERT INTO products (id, name, slug, description, icon, credit_cost, status) VALUES
  (uuid_generate_v4(), 'Governance Analytics',    'governance-analytics',    'Board composition analysis, governance scoring, and compliance benchmarking.',                '🏛', 10.00, 'active'),
  (uuid_generate_v4(), 'Regulatory Intelligence', 'regulatory-intelligence', 'Real-time regulatory monitoring, impact assessments, and compliance gap analysis.',         '📋', 15.00, 'active'),
  (uuid_generate_v4(), 'Due Diligence Engine',    'due-diligence',           'Deep-dive corporate intelligence reports covering ownership, directorships, and risks.',    '🔍', 25.00, 'active'),
  (uuid_generate_v4(), 'ESG Scoring',             'esg-scoring',             'Environmental, Social and Governance scoring with peer benchmarking and trend analysis.',   '📈',  8.00, 'active'),
  (uuid_generate_v4(), 'Risk Intelligence',       'risk-intelligence',       'Predictive risk modelling, early warning indicators, and risk register management.',       '⚠',  12.00, 'active'),
  (uuid_generate_v4(), 'Document Intelligence',   'document-intelligence',   'AI-powered document analysis, clause extraction, and regulatory mapping.',                 '🗂',  20.00, 'active')
ON CONFLICT (slug) DO NOTHING;
