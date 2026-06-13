<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'free',     'name' => 'Free Trial',
                'price_ore' => 0,     'interval' => 'none',
                'sms_included' => 50, 'rate_per_minute' => 6,
                'stripe_price_id' => null, 'sort_order' => 0,
                'features' => ['trial' => true, 'trial_days' => 14],
            ],
            [
                'slug' => 'starter',  'name' => 'Starter',
                'price_ore' => 9900,  'interval' => 'month',
                'sms_included' => 500, 'rate_per_minute' => 30,
                'stripe_price_id' => env('PLAN_STARTER_PRICE_ID'),
                'sort_order' => 1,
                'features' => ['api' => true, 'webhooks' => 3, 'support' => 'email'],
            ],
            [
                'slug' => 'business', 'name' => 'Business',
                'price_ore' => 29900, 'interval' => 'month',
                'sms_included' => 3000, 'rate_per_minute' => 60,
                'stripe_price_id' => env('PLAN_BUSINESS_PRICE_ID'),
                'sort_order' => 2,
                'features' => ['api' => true, 'webhooks' => 10, 'automation' => true],
            ],
            [
                'slug' => 'pro',      'name' => 'Pro',
                'price_ore' => 99900, 'interval' => 'month',
                'sms_included' => 15000, 'rate_per_minute' => 120,
                'stripe_price_id' => env('PLAN_PRO_PRICE_ID'),
                'sort_order' => 3,
                'features' => ['api' => true, 'webhooks' => 50, 'automation' => true, 'priority_routing' => true],
            ],
            [
                'slug' => 'enterprise', 'name' => 'Enterprise',
                'price_ore' => 0,     'interval' => 'month',
                'sms_included' => null, 'rate_per_minute' => 600,
                'stripe_price_id' => null,
                'sort_order' => 4,
                'features' => ['dedicated_gateway' => true, 'custom_routing' => true, 'sla' => '99.9'],
                'is_public' => false,
            ],
        ];

        foreach ($plans as $p) {
            Plan::updateOrCreate(['slug' => $p['slug']], $p);
        }
    }
}
