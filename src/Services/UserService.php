<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\DB;

class UserService
{
    private DB $db;

    public function __construct()
    {
        $this->db = DB::getInstance();
    }

    public function findByKeycloakId(string $keycloakId): array|false
    {
        return $this->db->fetch(
            'SELECT * FROM users WHERE keycloak_id = :id',
            ['id' => $keycloakId]
        );
    }

    public function findByEmail(string $email): array|false
    {
        return $this->db->fetch(
            'SELECT * FROM users WHERE email = :email',
            ['email' => $email]
        );
    }

    public function findById(string $id): array|false
    {
        return $this->db->fetch(
            'SELECT * FROM users WHERE id = :id',
            ['id' => $id]
        );
    }

    public function create(array $data): string
    {
        return $this->db->insert('users', array_merge([
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $data));
    }

    public function update(string $id, array $data): int
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->update('users', $data, ['id' => $id]);
    }

    public function getProfile(string $userId): array|false
    {
        return $this->db->fetch(
            'SELECT u.*, o.name as organisation_name, o.slug as organisation_slug
             FROM users u
             LEFT JOIN organisations o ON u.organisation_id = o.id
             WHERE u.id = :id',
            ['id' => $userId]
        );
    }

    public function upsertFromKeycloak(array $kcUser, string $organisationId = ''): array
    {
        $existing = $this->findByKeycloakId($kcUser['sub'] ?? '');

        $data = [
            'keycloak_id' => $kcUser['sub'] ?? '',
            'email'       => $kcUser['email'] ?? '',
            'first_name'  => $kcUser['given_name'] ?? '',
            'last_name'   => $kcUser['family_name'] ?? '',
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        if (!empty($organisationId)) {
            $data['organisation_id'] = $organisationId;
        }

        if ($existing) {
            $this->update($existing['id'], $data);
            return array_merge($existing, $data);
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $data['role']       = 'admin';
        $data['status']     = 'active';

        $id = $this->create($data);
        return array_merge($data, ['id' => $id]);
    }
}
