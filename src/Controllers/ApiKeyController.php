<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\Middleware;
use GI\Core\Session;
use GI\Core\View;
use GI\Services\ApiKeyService;
use GI\Services\ProductService;

class ApiKeyController
{
    public function index(): void
    {
        Middleware::auth();
        $user  = Session::get('user');
        $orgId = $user['organisation_id'] ?? '';

        $apiKeyService  = new ApiKeyService();
        $productService = new ProductService();

        $apiKeys  = [];
        $products = [];

        try {
            $apiKeys  = $orgId ? $apiKeyService->getForOrganisation($orgId) : [];
            $products = $productService->getActive();
        } catch (\Exception $e) {
            // Database may not be set up yet
        }

        View::render('api-keys/index', [
            'user'     => $user,
            'apiKeys'  => $apiKeys,
            'products' => $products,
        ]);
    }

    public function create(): void
    {
        Middleware::auth();
        $user   = Session::get('user');
        $orgId  = $user['organisation_id'] ?? '';
        $userId = $user['id'] ?? '';

        $name      = trim($_POST['name'] ?? '');
        $productId = trim($_POST['product_id'] ?? '');

        if (empty($name) || empty($orgId)) {
            Session::flash('error', 'API key name is required.');
            header('Location: /api-keys');
            exit;
        }

        $apiKeyService = new ApiKeyService();
        $result        = $apiKeyService->generate($orgId, $userId, $name, $productId);

        Session::flash('new_api_key', $result['key']);
        Session::flash('success', "API key '{$name}' created. Copy your key now — it will not be shown again.");
        header('Location: /api-keys');
        exit;
    }

    public function revoke(string $id): void
    {
        Middleware::auth();

        $apiKeyService = new ApiKeyService();
        $apiKeyService->revoke($id);

        Session::flash('success', 'API key revoked.');
        header('Location: /api-keys');
        exit;
    }
}
