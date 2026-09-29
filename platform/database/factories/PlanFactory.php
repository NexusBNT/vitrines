<?php

namespace Database\Factories;

use App\Enums\PlanFeature;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'max_pages' => 1,
            'features' => [],
            'is_active' => true,
        ];
    }

    public function pro(): static
    {
        return $this->state(fn (): array => [
            'max_pages' => 5,
            'features' => [
                PlanFeature::Gallery->value,
                PlanFeature::LocalSeo->value,
                PlanFeature::Stats->value,
                PlanFeature::SearchConsole->value,
            ],
        ]);
    }
}
