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

    private function renderComponent(string $component): void
    {
        Middleware::auth();

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
