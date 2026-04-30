<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\Middleware;
use GI\Core\Session;
use GI\Core\View;
use GI\Services\ProductService;
use GI\Services\EntitlementService;

class ProductController
{
    public function index(): void
    {
        Middleware::auth();
        $user  = Session::get('user');
        $orgId = $user['organisation_id'] ?? '';

        $productService     = new ProductService();
        $entitlementService = new EntitlementService();

        $products = [];
        try {
            $products = $productService->getActive();
            foreach ($products as &$product) {
                $product['has_access'] = $orgId
                    ? $entitlementService->checkAccess($orgId, $product['slug'])
                    : false;
            }
        } catch (\Exception $e) {
            // Database may not be set up yet
        }

        View::render('products/index', ['user' => $user, 'products' => $products]);
    }
}
