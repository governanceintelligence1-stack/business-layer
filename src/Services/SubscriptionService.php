<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\DB;

class SubscriptionService
{
    private DB $db;

    public function __construct()
    {
        $this->db = DB::getInstance();
    }

    public function getForOrganisation(string $orgId): array
    {
        return $this->db->fetchAll(
            'SELECT s.*, p.name as plan_name, p.slug as plan_slug,
                    p.price_monthly, p.credits_monthly
             FROM subscriptions s
             INNER JOIN plans p ON s.plan_id = p.id
             WHERE s.organisation_id = :org_id
             ORDER BY s.created_at DESC',
            ['org_id' => $orgId]
        );
    }

    public function getActive(string $orgId): array|false
    {
        return $this->db->fetch(
            "SELECT s.*, p.name as plan_name, p.slug as plan_slug,
                    p.price_monthly, p.credits_monthly, p.max_users, p.max_api_keys, p.features
             FROM subscriptions s
             INNER JOIN plans p ON s.plan_id = p.id
             WHERE s.organisation_id = :org_id AND s.status = 'active'
             ORDER BY s.created_at DESC LIMIT 1",
            ['org_id' => $orgId]
        );
    }

    public function create(string $orgId, string $planId, string $billingCycle = 'monthly'): string
    {
        $now = date('Y-m-d H:i:s');
        $end = $billingCycle === 'annual'
            ? date('Y-m-d H:i:s', strtotime('+1 year'))
            : date('Y-m-d H:i:s', strtotime('+1 month'));

        return $this->db->insert('subscriptions', [
            'organisation_id'      => $orgId,
            'plan_id'              => $planId,
            'billing_cycle'        => $billingCycle,
            'status'               => 'active',
            'current_period_start' => $now,
            'current_period_end'   => $end,
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);
    }

    public function cancel(string $subscriptionId): int
    {
        return $this->db->update('subscriptions', [
            'status'       => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ], ['id' => $subscriptionId]);
    }

    public function isActive(string $orgId): bool
    {
        return $this->getActive($orgId) !== false;
    }

    public function getCurrentPlan(string $orgId): array|false
    {
        return $this->getActive($orgId);
    }
}
