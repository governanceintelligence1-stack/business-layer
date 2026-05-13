<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\Middleware;
use GI\Core\Session;
use GI\Core\View;
use GI\Services\ArticleService;

class UpdatesController
{
    public function index(): void
    {
        Middleware::auth();
        $user = Session::get('user');
        $articles = [];

        try {
            $articles = (new ArticleService())->getPublishedAll();
        } catch (\Throwable $e) {
            $articles = [];
        }

        View::render('updates/index', [
            'user' => $user,
            'articles' => $articles,
        ]);
    }
}
