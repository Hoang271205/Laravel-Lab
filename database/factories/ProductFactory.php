<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(10000, 99999),
            'currency' => Product::DEFAULT_CURRENCY,
            'display_image_url' => Product::DEFAULT_IMAGE,
            'category_id' => Category::factory(),
        ];
    }
}
