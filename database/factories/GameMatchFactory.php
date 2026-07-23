<?php

namespace Database\Factories;

use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameMatch>
 */
class GameMatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'opponent' => fake()->city().' FC',
            'played_on' => fake()->dateTimeBetween('-1 year', 'now'),
            'venue' => fake()->randomElement(['home', 'away']),
            'city' => fake()->city().', Polska',
            'lat' => fake()->latitude(49, 55),
            'lng' => fake()->longitude(14, 24),
            'distance_km' => fake()->numberBetween(0, 500),
            'goals_for' => fake()->numberBetween(0, 5),
            'goals_against' => fake()->numberBetween(0, 5),
        ];
    }
}
