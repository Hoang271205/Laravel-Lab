<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([1 => 'Cafe', 2 => 'Chicken'] as $id => $name) {
            Category::updateOrCreate(['id' => $id], ['name' => $name, 'description' => '']);
        }
    }
}
