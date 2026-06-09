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
            'lead_id' => fake()->unique()->numberBetween(1, 2999999999),
            'customer_name' => fake()->name(),
            'customer_tel' => fake()->numerify('1##########'),
            'status' => 4,
            'data_type' => 0,
            'data_sub_type' => 0,
            'create_time' => fake()->dateTimeBetween('-30 days', 'now'),
            'site_name' => fake()->randomElement(['品牌落地页', '搜索获客页', '本地生活页']),
            'remark' => '',
            'ad_trace_id' => fake()->uuid(),
            'ad_source_type' => 0,
            'ad_search_word' => fake()->word(),
            'ad_keyword' => fake()->word(),
            'ad_bannerid' => fake()->numberBetween(100000, 999999),
            'ip_address' => fake()->ipv4(),
            'more_info' => [],
            'is_faker' => false,
        ];
    }
}
