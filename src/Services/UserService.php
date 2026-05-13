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
            'SELECT u.*, up.first_name, up.last_name, up.display_name
             FROM users u
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE u.keycloak_id = :id',
            ['id' => $keycloakId]
        );
    }

    public function findByEmail(string $email): array|false
    {
        return $this->db->fetch(
            'SELECT u.*, up.first_name, up.last_name, up.display_name
             FROM users u
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE u.email = :email',
            ['email' => $email]
        );
    }

    public function findById(string $id): array|false
    {
        return $this->db->fetch(
            'SELECT u.*, up.first_name, up.last_name, up.display_name
             FROM users u
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE u.id = :id',
            ['id' => $id]
        );
    }

    public function create(array $data): string
    {
        $firstName = trim((string)($data['first_name'] ?? ''));
        $lastName = trim((string)($data['last_name'] ?? ''));
        $phone = trim((string)($data['phone_number'] ?? $data['phone'] ?? ''));
        unset($data['first_name'], $data['last_name'], $data['phone'], $data['phone_number']);

        if (!isset($data['sso_provider']) || $data['sso_provider'] === '') {
            $data['sso_provider'] = 'keycloak';
        }
        if (empty($data['username']) && !empty($data['email'])) {
            $data['username'] = $data['email'];
        }
        if (!array_key_exists('email_verified', $data)) {
            $data['email_verified'] = false;
        }

        $id = $this->db->insert('users', array_merge([
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $data));

        $profile = [
            'user_id' => $id,
            'first_name' => $firstName ?: null,
            'last_name' => $lastName ?: null,
            'display_name' => trim($firstName . ' ' . $lastName) ?: null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($phone !== '') {
            $profile['phone_number'] = $phone;
        }
        $this->db->insert('user_profiles', $profile);

        return $id;
    }

    public function update(string $id, array $data): int
    {
        $firstName = array_key_exists('first_name', $data) ? trim((string)$data['first_name']) : null;
        $lastName = array_key_exists('last_name', $data) ? trim((string)$data['last_name']) : null;
        unset($data['first_name'], $data['last_name']);
        $data['updated_at'] = date('Y-m-d H:i:s');
        $updated = $this->db->update('users', $data, ['id' => $id]);

        if ($firstName !== null || $lastName !== null) {
            $existing = $this->db->fetch('SELECT id FROM user_profiles WHERE user_id = :id', ['id' => $id]);
            $profileData = [
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($firstName !== null) {
                $profileData['first_name'] = $firstName ?: null;
            }
            if ($lastName !== null) {
                $profileData['last_name'] = $lastName ?: null;
            }
            $fn = $firstName ?? '';
            $ln = $lastName ?? '';
            if ($fn !== '' || $ln !== '') {
                $profileData['display_name'] = trim($fn . ' ' . $ln);
            }

            if ($existing) {
                $this->db->update('user_profiles', $profileData, ['user_id' => $id]);
            } else {
                $this->db->insert('user_profiles', array_merge([
                    'user_id' => $id,
                    'created_at' => date('Y-m-d H:i:s'),
                ], $profileData));
            }
        }

        return $updated;
    }

    public function getProfile(string $userId): array|false
    {
        return $this->db->fetch(
            'SELECT u.*, up.first_name, up.last_name, up.display_name,
                    o.name as organisation_name, o.slug as organisation_slug
             FROM users u
             LEFT JOIN user_profiles up ON up.user_id = u.id
             LEFT JOIN organisations o ON u.organisation_id = o.id
             WHERE u.id = :id',
            ['id' => $userId]
        );
    }

    public function upsertFromKeycloak(array $kcUser, string $organisationId = ''): array
    {
        $existing = $this->findByKeycloakId($kcUser['sub'] ?? '');

        $profileFirst = trim((string)($kcUser['given_name'] ?? ''));
        $profileLast = trim((string)($kcUser['family_name'] ?? ''));

        $data = [
            'keycloak_id'     => $kcUser['sub'] ?? '',
            'email'           => $kcUser['email'] ?? '',
            'username'        => $kcUser['preferred_username'] ?? null,
            'email_verified'  => filter_var($kcUser['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'sso_provider'    => 'keycloak',
            'updated_at'      => date('Y-m-d H:i:s'),
        ];

        if ($organisationId !== '') {
            $data['organisation_id'] = $organisationId;
        }

        if ($existing) {
            if (empty($data['organisation_id'])) {
                unset($data['organisation_id']);
            }
            $this->update($existing['id'], array_merge($data, [
                'first_name' => $profileFirst,
                'last_name'  => $profileLast,
            ]));
            return array_merge($existing, $data, [
                'first_name' => $profileFirst,
                'last_name'  => $profileLast,
            ]);
        }

        $orgId = $organisationId;
        if ($orgId === '') {
            $slug = trim((string)($_ENV['DEFAULT_ORGANISATION_SLUG'] ?? 'governance-intelligence-test'));
            $org = $this->db->fetch('SELECT id FROM organisations WHERE slug = :slug LIMIT 1', ['slug' => $slug]);
            if ($org && !empty($org['id'])) {
                $orgId = (string) $org['id'];
            }
        }
        if ($orgId === '') {
            $org = $this->db->fetch('SELECT id FROM organisations ORDER BY created_at ASC LIMIT 1');
            if ($org && !empty($org['id'])) {
                $orgId = (string) $org['id'];
            }
        }
        if ($orgId === '') {
            throw new \RuntimeException(
                'No organisation available for new user. Set DEFAULT_ORGANISATION_SLUG or seed organisations.'
            );
        }

        $data['organisation_id'] = $orgId;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['role'] = 'admin';
        $data['status'] = 'active';

        $id = $this->create(array_merge($data, [
            'first_name' => $profileFirst,
            'last_name'  => $profileLast,
        ]));

        return array_merge($data, ['id' => $id, 'first_name' => $profileFirst, 'last_name' => $profileLast]);
    }
}
