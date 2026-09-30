<?php

namespace Database\Seeders;

use App\Enums\PlanFeature;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Crée ou met à jour les trois offres commerciales.
     */
    public function run(): void
    {
        $pro = [
            PlanFeature::Gallery->value,
            PlanFeature::LocalSeo->value,
            PlanFeature::Stats->value,
            PlanFeature::SearchConsole->value,
        ];

        $plans = [
            ['code' => 'essentiel', 'name' => 'Essentiel', 'max_pages' => 1, 'features' => [], 'sort_order' => 1],
            ['code' => 'pro', 'name' => 'Pro', 'max_pages' => 5, 'features' => $pro, 'sort_order' => 2],
            ['code' => 'proplus', 'name' => 'Pro+', 'max_pages' => 5, 'features' => [
                ...$pro,
                PlanFeature::Posts->value,
                PlanFeature::SeoReports->value,
                PlanFeature::PrioritySupport->value,
                PlanFeature::AiFull->value,
            ], 'sort_order' => 3],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
