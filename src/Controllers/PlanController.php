<?php
declare(strict_types=1);

namespace GI\Controllers;

use GI\Core\Middleware;
use GI\Core\Session;
use GI\Core\View;
use GI\Services\PlanService;

class PlanController
{
    public function index(): void
    {
        Middleware::auth();
        $user = Session::get('user');

        $plans = [];

        try {
            $planService = new PlanService();
            $plans       = $planService->getActive();
            foreach ($plans as &$plan) {
                $plan['products'] = $planService->getPlanProducts($plan['id']);
                $decodedFeatures = is_string($plan['features'] ?? '')
                    ? json_decode($plan['features'], true)
                    : ($plan['features'] ?? []);

                if (!is_array($decodedFeatures)) {
                    $plan['features'] = [];
                    continue;
                }

                $isAssoc = array_keys($decodedFeatures) !== range(0, count($decodedFeatures) - 1);
                if (!$isAssoc) {
                    $plan['features'] = $decodedFeatures;
                    continue;
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
                foreach ($decodedFeatures as $key => $enabled) {
                    if (!$enabled) {
                        continue;
                    }

                    $normalizedFeatures[] = $featureLabelMap[$key]
                        ?? ucwords(str_replace('_', ' ', (string) $key));
                }
                $plan['features'] = $normalizedFeatures;
            }
            unset($plan);
        } catch (\Exception $e) {
            // Database may not be set up yet, continue with empty plans
        }

        View::render('plans/index', ['user' => $user, 'plans' => $plans]);
    }
}
