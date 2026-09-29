<?php

namespace Database\Factories;

use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Server>
 */
class ServerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'web-'.fake()->unique()->numberBetween(1, 999),
            'ipv4' => fake()->ipv4(),
            'ssh_host' => fake()->domainName(),
            'ssh_port' => 22,
            'status' => 'active',
        ];
    }
}
