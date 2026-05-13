<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\DB;

class ArticleService
{
    private DB $db;

    public function __construct()
    {
        $this->db = DB::getInstance();
    }

    /**
     * All published articles, newest first (by record timestamps).
     */
    public function getPublishedAll(): array
    {
        return $this->db->fetchAll(
            "SELECT id, title, article_date, summary, body, status, created_at, updated_at
             FROM articles
             WHERE status = 'published'
             ORDER BY created_at DESC, updated_at DESC"
        );
    }

    /**
     * Recent published articles for dashboard / previews.
     */
    public function getPublishedRecent(int $limit = 4): array
    {
        $limit = max(1, min(20, $limit));

        return $this->db->fetchAll(
            "SELECT id, title, article_date, summary, body, status, created_at, updated_at
             FROM articles
             WHERE status = 'published'
             ORDER BY created_at DESC, updated_at DESC
             LIMIT {$limit}"
        );
    }
}
