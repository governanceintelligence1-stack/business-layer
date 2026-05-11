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
                $plan['features'] = is_string($plan['features'] ?? '')
                    ? json_decode($plan['features'], true)
                    : ($plan['features'] ?? []);
            }
        } catch (\Exception $e) {
            // Database may not be set up yet, continue with empty plans
        }

        View::render('plans/index', ['user' => $user, 'plans' => $plans]);
    }
}
