<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\DB;

class ApiKeyService
{
    private DB $db;

    public function __construct()
    {
        $this->db = DB::getInstance();
    }

    public function generate(string $orgId, string $userId, string $name, string $productId = ''): array
    {
        $rawKey  = 'gi_' . bin2hex(random_bytes(24));
        $prefix  = substr($rawKey, 0, 10);
        $keyHash = hash('sha256', $rawKey);

        $data = [
            'organisation_id' => $orgId,
            'user_id'         => $userId,
            'name'            => $name,
            'key_hash'        => $keyHash,
            'key_prefix'      => $prefix,
            'status'          => 'active',
            'created_at'      => date('Y-m-d H:i:s'),
        ];

        if (!empty($productId)) {
            $data['product_id'] = $productId;
        }

        $id = $this->db->insert('api_keys', $data);

        return [
            'id'         => $id,
            'key'        => $rawKey,
            'key_prefix' => $prefix,
            'name'       => $name,
        ];
    }

    public function revoke(string $keyId): int
    {
        return $this->db->update('api_keys', ['status' => 'revoked'], ['id' => $keyId]);
    }

    public function findByKey(string $apiKey): array|false
    {
        $keyHash = hash('sha256', $apiKey);
        return $this->db->fetch(
            "SELECT * FROM api_keys WHERE key_hash = :hash AND status = 'active'",
            ['hash' => $keyHash]
        );
    }

    public function getForOrganisation(string $orgId): array
    {
        return $this->db->fetchAll(
            'SELECT ak.*, p.name as product_name
             FROM api_keys ak
             LEFT JOIN products p ON ak.product_id = p.id
             WHERE ak.organisation_id = :org_id
             ORDER BY ak.created_at DESC',
            ['org_id' => $orgId]
        );
    }

    public function logUsage(string $keyId, string $endpoint, float $creditsUsed = 0, int $responseCode = 200): void
    {
        $this->db->insert('api_usage_logs', [
            'api_key_id'    => $keyId,
            'endpoint'      => $endpoint,
            'credits_used'  => $creditsUsed,
            'response_code' => $responseCode,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        $this->db->update('api_keys', ['last_used_at' => date('Y-m-d H:i:s')], ['id' => $keyId]);
    }

    public function getUsageStats(string $apiKey): array
    {
        $keyHash = hash('sha256', $apiKey);
        $key     = $this->db->fetch(
            'SELECT id FROM api_keys WHERE key_hash = :hash',
            ['hash' => $keyHash]
        );

        if (!$key) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT endpoint, COUNT(*) as calls, SUM(credits_used) as total_credits
             FROM api_usage_logs WHERE api_key_id = :key_id
             GROUP BY endpoint ORDER BY calls DESC',
            ['key_id' => $key['id']]
        );
    }
}
