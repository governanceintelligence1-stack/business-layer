<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\View;
use GI\Services\ProductService;
use GI\Services\PlanService;

class HomeController
{
    public function index(): void
    {
        $productService = new ProductService();
        $planService    = new PlanService();

        try {
            $products = $productService->getActive();
            $plans    = $planService->getActive();
        } catch (\Exception $e) {
            $products = [];
            $plans    = [];
        }

        View::render('home/index', ['products' => $products, 'plans' => $plans], 'public');
    }
}
