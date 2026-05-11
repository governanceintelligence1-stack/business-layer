<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\DB;

class PaymentMethodService
{
    private DB $db;

    public function __construct()
    {
        $this->db = DB::getInstance();
    }

    public function getForOrganisation(string $orgId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM payment_methods
             WHERE organisation_id = :org_id AND status = 'active'
             ORDER BY is_default DESC, created_at DESC",
            ['org_id' => $orgId]
        );
    }

    public function findById(string $id, string $orgId): array|false
    {
        return $this->db->fetch(
            "SELECT * FROM payment_methods
             WHERE id = :id AND organisation_id = :org_id AND status = 'active'",
            ['id' => $id, 'org_id' => $orgId]
        );
    }

    public function saveCard(
        string $orgId,
        string $userId,
        string $brand,
        string $last4,
        string $expiryMonth,
        string $expiryYear,
        string $cardholderName,
        bool $setDefault = false
    ): string {
        if ($setDefault) {
            $this->clearDefault($orgId);
        }

        return $this->db->insert('payment_methods', [
            'organisation_id' => $orgId,
            'user_id'         => $userId,
            'brand'           => $brand ?: 'Card',
            'last4'           => $last4,
            'expiry_month'    => $expiryMonth ?: null,
            'expiry_year'     => $expiryYear ?: null,
            'cardholder_name' => $cardholderName ?: null,
            'is_default'      => $setDefault,
            'status'          => 'active',
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    public function setDefault(string $id, string $orgId): int
    {
        $this->clearDefault($orgId);
        return $this->db->update('payment_methods', [
            'is_default' => true,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id, 'organisation_id' => $orgId]);
    }

    private function clearDefault(string $orgId): void
    {
        $this->db->query(
            'UPDATE payment_methods SET is_default = false, updated_at = :updated_at WHERE organisation_id = :org_id',
            ['updated_at' => date('Y-m-d H:i:s'), 'org_id' => $orgId]
        );
    }
}
