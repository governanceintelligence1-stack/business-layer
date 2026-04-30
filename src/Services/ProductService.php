<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\DB;

class ProductService
{
    private DB $db;

    public function __construct()
    {
        $this->db = DB::getInstance();
    }

    public function getAll(): array
    {
        return $this->db->fetchAll('SELECT * FROM products ORDER BY name');
    }

    public function getActive(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM products WHERE status = 'active' ORDER BY name"
        );
    }

    public function findById(string $id): array|false
    {
        return $this->db->fetch(
            'SELECT * FROM products WHERE id = :id',
            ['id' => $id]
        );
    }

    public function findBySlug(string $slug): array|false
    {
        return $this->db->fetch(
            'SELECT * FROM products WHERE slug = :slug',
            ['slug' => $slug]
        );
    }
}
