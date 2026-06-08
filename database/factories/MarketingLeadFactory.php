<?php

namespace Database\Factories;

use App\Models\User;
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
            'user_id' => User::factory(),

            'campaign_id' => fake()->numberBetween(100000, 999999),
            'campaign_name' => fake()->randomElement([
                '品牌词推广计划',
                '搜索获客计划',
                '信息流转化计划',
                '本地生活推广',
                '线索收集计划',
            ]),

            'group_id' => fake()->numberBetween(100000, 999999),
            'group_name' => fake()->randomElement([
                '核心词推广组',
                '长尾词推广组',
                '移动端推广组',
                '高意向人群组',
                '再营销推广组',
            ]),

            'name' => fake()->name(),
            'gender' => fake()->optional()->randomElement(['男', '女']),
            'phone' => fake()->optional()->numerify('1##########'),

            'create_time' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
