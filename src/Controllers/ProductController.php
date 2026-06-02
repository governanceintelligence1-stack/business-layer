<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\Middleware;
use GI\Core\Session;
use GI\Core\View;
use GI\Services\ProductService;
use GI\Services\EntitlementService;
use GI\Services\TokenService;

class ProductController
{
    private const COMPONENT_PAGES = [
        'upload-forensic-image' => [
            'title'       => 'Upload Forensic Image',
            'description' => 'Upload and prepare forensic disk images for downstream analysis workflows.',
            'cta'         => 'Upload Image',
        ],
        'ocr' => [
            'title'       => 'OCR',
            'description' => 'Extract searchable text from scanned documents and evidence bundles.',
            'cta'         => 'Run OCR',
        ],
        'transcription' => [
            'title'       => 'Transcription',
            'description' => 'Convert audio/video interviews into timestamped, searchable transcripts.',
            'cta'         => 'Start Transcription',
        ],
        'bank-statements' => [
            'title'       => 'Bank Statements',
            'description' => 'Parse, normalize, and categorize statement transactions for investigation.',
            'cta'         => 'Process Statements',
        ],
        'file-comparison' => [
            'title'       => 'File Comparison',
            'description' => 'Compare two files and highlight additions, deletions, and field-level changes.',
            'cta'         => 'Compare Files',
        ],
    ];

    private const COMPONENT_PRODUCT_SLUGS = [
        'upload-forensic-image' => 'forensic-upload',
        'ocr' => 'ocr-document-analysis',
        'transcription' => 'transcription',
        'bank-statements' => 'bank-statement-analysis',
        'file-comparison' => 'file-comparison',
    ];

    public function index(): void
    {
        Middleware::auth();
        $user  = Session::get('user');
        $orgId = (string) ($user['organisation_id'] ?? '');

        $productService     = new ProductService();
        $entitlementService = new EntitlementService();
        $tokenService       = new TokenService();

        $products = [];
        $availableBalance = 0.0;
        $hasSubscription = false;

        try {
            if ($orgId !== '') {
                $availableBalance = $tokenService->getAvailableBalance($orgId);
                $hasSubscription  = $entitlementService->hasActiveSubscription($orgId);
            }

            $products = $productService->getActive();
            foreach ($products as &$product) {
                $slug = (string) ($product['slug'] ?? '');
                $evaluation = $orgId !== '' && $slug !== ''
                    ? $entitlementService->evaluateProductAccess($orgId, $slug)
                    : [
                        'can_use'           => false,
                        'reason'            => 'Not signed in',
                        'has_subscription'  => false,
                        'token_cost'        => (float) ($product['credit_cost'] ?? 0),
                        'available_balance' => 0.0,
                    ];

                $product['has_access'] = $evaluation['can_use'];
                $product['access_reason'] = $evaluation['reason'];
                $product['token_cost'] = $evaluation['token_cost'] ?? (float) ($product['credit_cost'] ?? 0);
                $product['credit_cost'] = $product['token_cost'];
            }
            unset($product);
        } catch (\Exception $e) {
            // Database may not be set up yet
        }

        View::render('products/index', [
            'user'              => $user,
            'products'          => $products,
            'availableBalance'  => $availableBalance,
            'hasSubscription'   => $hasSubscription,
            'minimumMonthlyTokens' => (new EntitlementService())->getMinimumMonthlyTokens(),
        ]);
    }

    private function renderComponent(string $component): void
    {
        Middleware::auth();
        $user = Session::get('user');
        $orgId = (string)($user['organisation_id'] ?? '');

        if (!isset(self::COMPONENT_PAGES[$component])) {
            http_response_code(404);
            View::render('products/component', [
                'componentTitle'       => 'Product Not Found',
                'componentDescription' => 'The requested product component does not exist.',
                'componentCta'         => 'Back to Products',
                'componentPath'        => '/products',
            ]);
            return;
        }

        $productSlug = self::COMPONENT_PRODUCT_SLUGS[$component] ?? $component;
        $evaluation  = $orgId !== ''
            ? (new EntitlementService())->evaluateProductAccess($orgId, $productSlug)
            : ['can_use' => false, 'reason' => 'No organisation'];

        if (!$evaluation['can_use']) {
            http_response_code(403);
            $cost = $evaluation['token_cost'] ?? null;
            $available = $evaluation['available_balance'] ?? 0;

            if (($evaluation['reason'] ?? '') === 'Insufficient tokens') {
                $description = sprintf(
                    'This product costs %s tokens per use. You have %s available. Top up or upgrade your plan for more monthly tokens.',
                    $cost !== null ? number_format($cost, 2) : '—',
                    number_format($available, 2)
                );
                $cta  = 'View Tokens';
                $path = '/tokens';
            } else {
                $description = 'An active subscription is required. All plans include every product; usage is limited by your monthly token balance.';
                $cta  = 'View Plans';
                $path = '/plans';
            }

            View::render('products/component', [
                'componentTitle'       => 'Cannot use this product yet',
                'componentDescription' => $description,
                'componentCta'         => $cta,
                'componentPath'        => $path,
            ]);
            return;
        }

        $page = self::COMPONENT_PAGES[$component];
        View::render('products/component', [
            'componentTitle'       => $page['title'],
            'componentDescription' => $page['description'],
            'componentCta'         => $page['cta'],
            'componentPath'        => '/products/' . $component,
        ]);
    }

    public function uploadForensicImage(): void
    {
        $this->renderComponent('upload-forensic-image');
    }

    public function ocr(): void
    {
        $this->renderComponent('ocr');
    }

    public function transcription(): void
    {
        $this->renderComponent('transcription');
    }

    public function bankStatements(): void
    {
        $this->renderComponent('bank-statements');
    }

    public function fileComparison(): void
    {
        $this->renderComponent('file-comparison');
    }
}
