<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ApiDemoSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 1000; $i++) {
            Category::firstOrCreate(['name' => sprintf('Category %04d', $i)], ['description' => 'Demo category '.$i]);
        }
        $category = Category::where('name', 'Category 0001')->firstOrFail();
        foreach (['Keyboard' => 350000, 'Mouse' => 150000, 'Monitor' => 2500000] as $name => $price) {
            Product::firstOrCreate(['name' => $name], ['description' => 'Demo '.$name, 'price' => $price, 'currency' => 'VND', 'display_image_url' => 'https://placehold.co/600x400.png', 'category_id' => $category->id]);
        }
    }
}
