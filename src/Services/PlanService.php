<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\DB;

class PlanService
{
    private DB $db;

    public function __construct()
    {
        $this->db = DB::getInstance();
    }

    public function getAll(): array
    {
        return $this->db->fetchAll('SELECT * FROM plans ORDER BY price_monthly');
    }

    public function getActive(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM plans WHERE status = 'active' ORDER BY price_monthly"
        );
    }

    public function findById(string $id): array|false
    {
        return $this->db->fetch(
            'SELECT * FROM plans WHERE id = :id',
            ['id' => $id]
        );
    }

    public function findBySlug(string $slug): array|false
    {
        return $this->db->fetch(
            'SELECT * FROM plans WHERE slug = :slug',
            ['slug' => $slug]
        );
    }

    public function getPlanProducts(string $planId): array
    {
        return $this->db->fetchAll(
            'SELECT p.* FROM products p
             INNER JOIN plan_products pp ON pp.product_id = p.id
             WHERE pp.plan_id = :plan_id',
            ['plan_id' => $planId]
        );
    }

    /** All active platform products (every plan includes the same product set; usage is token-limited). */
    public function getPlatformProducts(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM products WHERE status = 'active' ORDER BY name"
        );
    }

    public function getMinimumMonthlyCredits(): float
    {
        $row = $this->db->fetch(
            "SELECT COALESCE(SUM(credit_cost), 0) AS total
             FROM products
             WHERE status = 'active'"
        );

        return (float) ($row['total'] ?? 0);
    }
}
