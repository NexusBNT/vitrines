<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Plan;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = fake()->city();

        return [
            'client_id' => Client::factory(),
            'plan_id' => Plan::factory(),
            'slug' => fake()->unique()->slug(2),
            'brief' => [
                'business_name' => fake()->company(),
                'activity' => 'Plombier chauffagiste',
                'description' => fake()->paragraph(),
                'services' => [
                    ['name' => 'Dépannage plomberie', 'description' => null],
                    ['name' => 'Installation chaudière', 'description' => null],
                ],
                'phone' => '06'.fake()->numerify('########'),
                'email' => fake()->safeEmail(),
                'address' => ['street' => fake()->streetAddress(), 'postal_code' => fake()->postcode(), 'city' => $city],
                'opening_hours' => [
                    ['days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], 'opens' => '08:00', 'closes' => '18:00'],
                ],
                'opening_hours_note' => null,
                'city' => $city,
                'service_area' => [fake()->city(), fake()->city()],
                'service_radius_km' => 20,
                'socials' => [],
                'colors' => ['primary' => '#1d4ed8'],
                'style' => 'moderne',
                'notes' => null,
            ],
        ];
    }
}
