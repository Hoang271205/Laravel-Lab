<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoffeeDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_is_repeatable_and_relationships_match_assignment(): void
    {
        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('categories', 2);
        $this->assertDatabaseCount('products', 12);
        $categories = Category::with('products')->orderBy('id')->get();
        $this->assertSame(['Cafe', 'Chicken'], $categories->pluck('name')->all());
        foreach ($categories as $category) {
            $this->assertCount(6, $category->products);
            foreach ($category->products as $product) {
                $this->assertTrue($product->category->is($category));
            }
        }
        $this->assertSame('35,000 VNĐ', Product::findOrFail(1)->formatted_price);
        $this->assertSame('105,000 VNĐ', Product::findOrFail(1)->getFormattedTotalAmount(3));
    }

    public function test_factory_creates_related_category(): void
    {
        $product = Product::factory()->create();
        $this->assertInstanceOf(Category::class, $product->category);
        $this->assertSame(Product::DEFAULT_CURRENCY, $product->currency);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('categories', 1);
    }

    public function test_deleting_category_keeps_product_and_sets_foreign_key_to_null(): void
    {
        $product = Product::factory()->create();
        $product->category->delete();
        $this->assertNull($product->fresh()->category_id);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_invalid_category_is_rejected_by_database(): void
    {
        $this->expectException(QueryException::class);
        Product::factory()->create(['category_id' => 999999]);
    }

    public function test_category_id_update_cascades_and_uncategorized_product_is_allowed(): void
    {
        $product = Product::factory()->create();
        $newId = $product->category_id + 100;
        Category::whereKey($product->category_id)->update(['id' => $newId]);
        $this->assertSame($newId, $product->fresh()->category_id);
        $uncategorized = Product::factory()->create(['category_id' => null]);
        $this->assertNull($uncategorized->category);
    }

    public function test_query_command_and_catalog_display_seed_data(): void
    {
        $this->seed();
        $this->artisan('app:query-builder-playground')
            ->expectsOutput('Category name: Cafe')
            ->expectsOutput('Total products: 6')
            ->expectsOutput('Category name: Chicken')
            ->expectsOutput('Total products: 6')
            ->assertSuccessful();
        $this->get('/')->assertOk()->assertSee('Cafe')->assertSee('Chicken satay salad')->assertSee('35,000 VNĐ');
    }
}
