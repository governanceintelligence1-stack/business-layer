-- 005_create_plan_products.sql
CREATE TABLE IF NOT EXISTS plan_products (
    id         UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    plan_id    UUID NOT NULL REFERENCES plans(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE(plan_id, product_id)
);

CREATE INDEX IF NOT EXISTS idx_plan_products_plan_id    ON plan_products(plan_id);
CREATE INDEX IF NOT EXISTS idx_plan_products_product_id ON plan_products(product_id);
