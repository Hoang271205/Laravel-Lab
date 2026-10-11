<?php

namespace Tests\Feature\Security;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAbilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_resource_routes_require_authentication(): void
    {
        foreach (['categories', 'products'] as $resource) {
            $this->getJson('/api/v1/'.$resource)->assertUnauthorized();
            $this->postJson('/api/v1/'.$resource, [])->assertUnauthorized();
            $this->putJson('/api/v1/'.$resource.'/1', [])->assertUnauthorized();
            $this->deleteJson('/api/v1/'.$resource.'/1')->assertUnauthorized();
        }
    }

    public function test_read_only_token_can_list_but_cannot_write(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Reader', ['read'])->plainTextToken;
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        foreach (['categories' => $category->id, 'products' => $product->id] as $resource => $id) {
            $this->withToken($token)->getJson('/api/v1/'.$resource)->assertOk();
            $this->postJson('/api/v1/'.$resource, [])->assertForbidden();
            $this->putJson('/api/v1/'.$resource.'/'.$id, [])->assertForbidden();
            $this->deleteJson('/api/v1/'.$resource.'/'.$id)->assertForbidden();
        }
    }

    public function test_full_permission_token_can_perform_crud(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Writer', ['read', 'create', 'update', 'delete'])->plainTextToken;
        $this->withToken($token);
        $category = $this->postJson('/api/v1/categories', ['name' => 'Secure category', 'description' => 'Section6 test'])->assertCreated()->json();
        $this->putJson('/api/v1/categories/'.$category['id'], ['name' => 'Updated secure category', 'description' => 'Updated'])->assertOk();
        $product = $this->postJson('/api/v1/products', ['name' => 'Secure coffee', 'description' => 'Section6 test', 'category_id' => $category['id'], 'price' => 45000, 'currency' => 'VND', 'display_image_url' => 'https://example.com/coffee.png'])->assertCreated()->json();
        $this->putJson('/api/v1/products/'.$product['id'], ['name' => 'Updated coffee', 'description' => 'Updated', 'category_id' => $category['id'], 'price' => 50000, 'currency' => 'VND', 'display_image_url' => 'https://example.com/coffee.png'])->assertOk();
        $this->deleteJson('/api/v1/products/'.$product['id'])->assertNoContent();
        $this->deleteJson('/api/v1/categories/'.$category['id'])->assertNoContent();
    }
}
