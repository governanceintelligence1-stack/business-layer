<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\Middleware;
use GI\Core\Session;
use GI\Core\View;
use GI\Services\PlanService;
use GI\Services\EntitlementService;

class PlanController
{
    /**
     * Temporary compatibility fallback while operations-api plan payload
     * does not include pricing/token columns.
     *
     * @var array<string, array{price_monthly: float, price_annual: float, credits_monthly: float}>
     */
    private const PLAN_FALLBACKS = [
        'starter' => [
            'price_monthly' => 500.0,
            'price_annual' => 5000.0,
            'credits_monthly' => 293.0,
        ],
        'professional' => [
            'price_monthly' => 5000.0,
            'price_annual' => 50000.0,
            'credits_monthly' => 5000.0,
        ],
        'enterprise' => [
            'price_monthly' => 20000.0,
            'price_annual' => 200000.0,
            'credits_monthly' => 20000.0,
        ],
    ];

    /**
     * @return list<string>
     */
    private function normalizeFeatures(mixed $features): array
    {
        if (is_string($features)) {
            $decoded = json_decode($features, true);
            $features = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($features)) {
            return [];
        }

        $isAssoc = array_keys($features) !== range(0, count($features) - 1);
        if (!$isAssoc) {
            return array_values(array_filter(array_map(
                static fn(mixed $feature): string => is_array($feature)
                    ? (string) ($feature['label'] ?? $feature['name'] ?? '')
                    : (string) $feature,
                $features
            )));
        }

        $featureLabelMap = [
            'ocr' => 'OCR',
            'api_access' => 'API Access',
            'mxa_mobile' => 'MXA Mobile',
            'transcription' => 'Transcription',
            'forensic_upload' => 'Forensic Upload',
            'bank_statement_analysis' => 'Bank Statement Analysis',
            'custom_limits' => 'Custom Limits',
            'file_comparison' => 'File Comparison',
            'priority_support' => 'Priority Support',
        ];

        $normalizedFeatures = [];
        foreach ($features as $key => $enabled) {
            if (!$enabled) {
                continue;
            }

            $normalizedFeatures[] = $featureLabelMap[$key]
                ?? ucwords(str_replace('_', ' ', (string) $key));
        }

        return $normalizedFeatures;
    }

    public function index(): void
    {
        Middleware::auth();
        $user = Session::get('user');

        $plans = [];
        $platformProducts = [];
        $minimumMonthlyTokens = 0.0;

        try {
            $planService = new PlanService();
            $plans       = $planService->getActive();
            $minimumMonthlyTokens = (new EntitlementService())->getMinimumMonthlyTokens();
            foreach ($plans as &$plan) {
                $slug = (string) ($plan['slug'] ?? '');
                $fallback = self::PLAN_FALLBACKS[$slug] ?? null;

                // Support both legacy and canonical field names from upstream APIs.
                $plan['price_monthly'] = (float) (
                    $plan['price_monthly']
                    ?? $plan['monthly_price']
                    ?? ($fallback['price_monthly'] ?? 0)
                );
                $plan['price_annual'] = (float) (
                    $plan['price_annual']
                    ?? $plan['annual_price']
                    ?? ($fallback['price_annual'] ?? 0)
                );
                $plan['credits_monthly'] = (float) (
                    $plan['credits_monthly']
                    ?? $plan['monthly_tokens']
                    ?? ($fallback['credits_monthly'] ?? 0)
                );

                $planProducts = [];
                $planId = (string) ($plan['id'] ?? '');
                if ($planId !== '') {
                    $planProducts = $planService->getPlanProducts($planId);
                }
                if ($planProducts === [] && $platformProducts === []) {
                    $platformProducts = $planService->getPlatformProducts();
                }

                $plan['products'] = $planProducts !== [] ? $planProducts : $platformProducts;
                $plan['features'] = $this->normalizeFeatures($plan['features'] ?? []);
            }
            unset($plan);
        } catch (\Exception $e) {
            // Database may not be set up yet, continue with empty plans
        }

        View::render('plans/index', [
            'user'                 => $user,
            'plans'                => $plans,
            'platformProducts'     => $platformProducts,
            'minimumMonthlyTokens' => $minimumMonthlyTokens,
        ]);
    }
}
