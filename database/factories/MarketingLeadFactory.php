<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MarketingLeadFactory extends Factory
{

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => 1,
            'owner_id' => null,
            'clue_id' => (string)fake()->unique()->numberBetween(1, 2999999999),
            'username' => fake()->name(),
            'phone' => fake()->numerify('1##########'),
            'keyword' => fake()->word(),
            'search_word' => fake()->word(),
            'clue_time' => fake()->dateTimeBetween('-30 days', 'now'),
            'site_name' => fake()->randomElement(['品牌落地页', '搜索获客页', '本地生活页']),
            'is_faker' => false,
        ];
    }
}
