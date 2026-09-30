<?php

namespace Database\Factories;

use App\Models\AiGeneration;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiGeneration>
 */
class AiGenerationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'task' => 'site_content',
            'prompt_version' => 'v1',
            'status' => 'pending',
        ];
    }
}
