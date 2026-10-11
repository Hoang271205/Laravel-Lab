<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\ApiDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Section2ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(), ['*']);
    }

    private function category(): Category
    {
        return Category::create(['name' => 'Electronics', 'description' => 'Electronic goods']);
    }

    private function payload(int $id): array
    {
        return ['name' => 'Keyboard', 'description' => 'Mechanical keyboard', 'price' => 350000, 'currency' => 'VND', 'display_image_url' => 'https://example.com/keyboard.png', 'category_id' => $id];
    }

    public function test_category_crud_and_unchanged_unique_name(): void
    {
        $r = $this->postJson('/api/v1/categories', ['name' => 'Books', 'description' => 'Books category'])->assertCreated();
        $id = $r->json('id');
        $this->getJson('/api/v1/categories/'.$id)->assertOk()->assertJsonPath('name', 'Books');
        $this->putJson('/api/v1/categories/'.$id, ['name' => 'Books', 'description' => 'Updated'])->assertOk()->assertJsonPath('description', 'Updated');
        $this->deleteJson('/api/v1/categories/'.$id)->assertNoContent();
        $this->getJson('/api/v1/categories/'.$id)->assertNotFound();
    }

    public function test_category_validation(): void
    {
        $this->postJson('/api/v1/categories', [])->assertUnprocessable()->assertJsonPath('error.fields', ['name', 'description']);
        $c = $this->category();
        $this->postJson('/api/v1/categories', ['name' => $c->name, 'description' => 'Duplicate'])->assertUnprocessable();
        $this->patchJson('/api/v1/categories/'.$c->id, ['name' => null])->assertUnprocessable();
    }

    public function test_seeding_is_repeatable_and_pagination_has_1000_categories(): void
    {
        $this->seed(ApiDemoSeeder::class);
        $this->seed(ApiDemoSeeder::class);
        $this->assertDatabaseCount('categories', 1000);
        $this->assertDatabaseCount('products', 3);
        $this->getJson('/api/v1/categories?page=2')->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('data.0.name', 'Category 0016');
        $this->getJson('/api/v1/categories?page=67')->assertJsonCount(10, 'data')->assertJsonPath('next_page_url', null);
        $this->getJson('/api/v1/categories?search=001')->assertOk()->assertJsonCount(11, 'data');
    }

    public function test_product_crud_and_optional_relationship(): void
    {
        $c = $this->category();
        $r = $this->postJson('/api/v1/products', $this->payload($c->id))->assertCreated();
        $id = $r->json('id');
        $this->getJson('/api/v1/products/'.$id)->assertOk()->assertJsonMissingPath('category');
        $this->getJson('/api/v1/products/'.$id.'?with=category')->assertOk()->assertJsonPath('category.id', $c->id);
        $this->patchJson('/api/v1/products/'.$id, ['name' => 'Keyboard', 'price' => 123.45])->assertOk()->assertJsonPath('price', '123.45');
        $this->deleteJson('/api/v1/products/'.$id)->assertNoContent();
        $this->getJson('/api/v1/products/'.$id)->assertNotFound();
    }

    public function test_product_name_checks_products_table_and_not_categories(): void
    {
        $c = $this->category();
        $data = $this->payload($c->id);
        $data['name'] = $c->name;
        $this->postJson('/api/v1/products', $data)->assertCreated();
        $this->postJson('/api/v1/products', $data)->assertUnprocessable()->assertJsonPath('error.fields', ['name']);
    }

    public function test_product_validation_and_foreign_key(): void
    {
        $this->postJson('/api/v1/products', [])->assertUnprocessable()->assertJsonPath('error.fields', ['name', 'description', 'price', 'currency', 'display_image_url', 'category_id']);
        $data = $this->payload(999);
        $data['price'] = -1;
        $data['currency'] = 'bad';
        $data['display_image_url'] = 'invalid';
        $this->postJson('/api/v1/products', $data)->assertUnprocessable()->assertJsonPath('error.fields', ['price', 'currency', 'display_image_url', 'category_id']);
        $c = $this->category();
        $product = Product::create($this->payload($c->id));
        $this->patchJson('/api/v1/products/'.$product->id, ['category_id' => null])->assertUnprocessable();
    }

    public function test_sort_search_and_eager_loading(): void
    {
        $this->seed(ApiDemoSeeder::class);
        $this->getJson('/api/v1/products?sort=price&with=category')->assertOk()->assertJsonPath('data.0.name', 'Mouse')->assertJsonPath('data.2.name', 'Monitor')->assertJsonPath('data.0.category.name', 'Category 0001');
        $this->getJson('/api/v1/products?sort=-price')->assertOk()->assertJsonPath('data.0.name', 'Monitor')->assertJsonPath('data.2.name', 'Mouse');
        $this->getJson('/api/v1/products?search=eyb')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Keyboard');
    }

    public function test_invalid_query_parameters_return_422(): void
    {
        $this->getJson('/api/v1/products?with=missing&sort=invalid&per_page=101&page=0')->assertUnprocessable()->assertJsonPath('error.fields', ['with', 'sort', 'page', 'per_page']);
        $this->getJson('/api/v1/categories?per_page=0')->assertUnprocessable();
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $c = $this->category();
        $product = Product::create($this->payload($c->id));
        $this->deleteJson('/api/v1/categories/'.$c->id)->assertStatus(409);
        $this->assertDatabaseHas('categories', ['id' => $c->id]);
        $this->deleteJson('/api/v1/products/'.$product->id)->assertNoContent();
        $this->deleteJson('/api/v1/categories/'.$c->id)->assertNoContent();
    }

    public function test_api_errors_are_json_even_without_accept_header(): void
    {
        $this->get('/api/v1/products/999')->assertNotFound()->assertHeader('Content-Type', 'application/json');
        $this->post('/api/v1/categories')->assertUnprocessable()->assertHeader('Content-Type', 'application/json');
    }

    public function test_documentation_includes_both_resources(): void
    {
        $this->app['env'] = 'local';
        $this->get('/docs/api')->assertOk();
        $r = $this->getJson('/docs/api.json')->assertOk();
        $paths = $r->json('paths');
        $this->assertArrayHasKey('/v1/categories', $paths);
        $this->assertArrayHasKey('/v1/products', $paths);
    }
}
