<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
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
            'sha256' => hash('sha256', fake()->unique()->uuid()),
            'original_path' => 'originals/'.fake()->uuid().'.jpg',
            'original_name' => 'photo.jpg',
            'mime' => 'image/jpeg',
            'bytes' => 250_000,
        ];
    }
}
