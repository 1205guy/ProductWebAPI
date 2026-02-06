<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->realText(20),
            'description' => fake()->realText(200),
            'price' => fake()->numberBetween(100, 100000),
            'stock' => fake()->numberBetween(0, 100),
            'is_active' => fake()->boolean(80), // 80%の確率でtrue
            'created_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'updated_at' => function (array $attributes) {
                return fake()->dateTimeBetween($attributes['created_at'], 'now');
            },
        ];
    }
}
