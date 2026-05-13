<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\DB;

class OrganisationService
{
    private DB $db;

    public function __construct()
    {
        $this->db = DB::getInstance();
    }

    public function findById(string $id): array|false
    {
        return $this->db->fetch(
            'SELECT * FROM organisations WHERE id = :id',
            ['id' => $id]
        );
    }

    public function findBySlug(string $slug): array|false
    {
        return $this->db->fetch(
            'SELECT * FROM organisations WHERE slug = :slug',
            ['slug' => $slug]
        );
    }

    public function create(array $data): string
    {
        $name = (string)($data['name'] ?? '');
        $slug = $this->generateSlug($name);
        $allowed = array_flip([
            'name', 'slug', 'account_type', 'status', 'billing_email', 'tax_number',
            'country', 'currency',
        ]);
        $data = array_intersect_key($data, $allowed);

        return $this->db->insert('organisations', array_merge([
            'slug'       => $slug,
            'status'     => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $data));
    }

    public function update(string $id, array $data): int
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->update('organisations', $data, ['id' => $id]);
    }

    public function getMembers(string $orgId): array
    {
        return $this->db->fetchAll(
            'SELECT u.id, u.email, up.first_name, up.last_name, u.role, u.status, u.created_at
             FROM users u
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE u.organisation_id = :org_id
             ORDER BY u.created_at',
            ['org_id' => $orgId]
        );
    }

    public function addMember(string $orgId, string $email, string $role = 'viewer'): bool
    {
        $result = $this->db->query(
            'UPDATE users SET organisation_id = :org_id, role = :role WHERE email = :email',
            ['org_id' => $orgId, 'role' => $role, 'email' => $email]
        );
        return $result->rowCount() > 0;
    }

    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        $base = $slug;
        $i    = 1;
        while ($this->findBySlug($slug)) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
