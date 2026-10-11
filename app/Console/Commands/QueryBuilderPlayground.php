<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;

class QueryBuilderPlayground extends Command
{
    protected $signature = 'app:query-builder-playground';

    protected $description = 'List categories and their products using Eloquent eager loading';

    public function handle(): int
    {
        foreach (Category::query()->with('products')->orderBy('id')->get() as $category) {
            $this->line('Category name: '.$category->name);
            $this->line('Total products: '.$category->products->count());
            foreach ($category->products as $product) {
                $this->line('--- Product name: '.$product->name);
            }
            $this->line('--------------------------------------------');
        }

        return self::SUCCESS;
    }
}
