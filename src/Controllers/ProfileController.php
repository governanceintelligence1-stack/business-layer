<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\Middleware;
use GI\Core\Session;
use GI\Core\View;

class ProfileController
{
    public function index(): void
    {
        Middleware::auth();
        $user = Session::get('user') ?? [];

        View::render('profile/index', [
            'user' => $user,
        ]);
    }
}

