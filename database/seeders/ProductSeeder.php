<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        for ($id = 1; $id <= 12; $id++) {
            $isCoffee = $id <= 6;
            Product::updateOrCreate(['id' => $id], [
                'category_id' => $isCoffee ? 1 : 2,
                'name' => $isCoffee ? 'Cappuchino' : 'Chicken satay salad',
                'description' => $isCoffee
                    ? 'Cappuccino is a coffee drink that today is typically composed of a single espresso shot and hot milk'
                    : 'Marinate chicken breasts, then drizzle with a punchy peanut satay sauce',
                'price' => $isCoffee ? 35000 : 50000,
                'currency' => Product::DEFAULT_CURRENCY,
                'display_image_url' => Product::DEFAULT_IMAGE,
            ]);
        }
    }
}
