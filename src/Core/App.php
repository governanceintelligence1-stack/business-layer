<?php
declare(strict_types=1);

namespace GI\Core;

use GI\Controllers\HomeController;
use GI\Controllers\AuthController;
use GI\Controllers\DashboardController;
use GI\Controllers\OrganisationController;
use GI\Controllers\ProductController;
use GI\Controllers\PlanController;
use GI\Controllers\SubscriptionController;
use GI\Controllers\TokenController;
use GI\Controllers\ApiKeyController;
use GI\Controllers\BillingController;
use GI\Controllers\ApiController;

class App
{
    private Router $router;

    public function __construct()
    {
        Session::start();
        $this->router = new Router();
        $this->loadRoutes();
    }

    private function loadRoutes(): void
    {
        $router = $this->router;
        require BASE_PATH . '/src/routes.php';
    }

    public function run(): void
    {
        $this->router->dispatch();
    }

    public function getRouter(): Router
    {
        return $this->router;
    }
}
