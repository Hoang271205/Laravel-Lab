<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Support\Facades\Session;

class CartServiceTest extends TestCase
{
    public CartService $cart_service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cart_service = new CartService();
    }

    public function test_get_valid_cart_instance(): void
    {
        $this->assertInstanceOf(Cart::class, $this->cart_service->getCart());
    }

    public function test_get_cart_with_correct_user_id()
    {
        $dummy_user_id = 'DUMMY_USER_ID';

        Session::put('user_id', $dummy_user_id);

        $cart = $this->cart_service->getCart();

        $this->assertSame($dummy_user_id, $cart->user_id);
    }

    public function test_should_return_new_cart_current_cart_already_have_order()
    {
        $current_cart = $this->cart_service->getCart();

        $current_cart->order()->save(Order::factory()->make());

        $new_cart = $this->cart_service->getCart();

        $this->assertNotSame($current_cart->id, $new_cart->id);
    }

    public function test_get_cart_should_return_same_instance_on_multiple_called_times()
    {
        $cart = $this->cart_service->getCart();

        $random_called_times = 8;

        $loop = 1;

        do {
            $cart_for_check = $this->cart_service->getCart();

            $loop++;
        } while ($loop < $random_called_times);

        $this->assertSame($cart->id, $cart_for_check->id);

        $this->assertSame($cart->user_id, $cart_for_check->user_id);
    }

    public function test_get_cart_item_by_product_id()
    {
        $cart = $this->cart_service->getCart();

        $cart->items()->saveMany(CartItem::factory()->count(3)->make());

        $cart_item = $cart->items->random();

        $cart_item_for_check = $this->cart_service->getCartItem($cart_item->product_id);

        $this->assertSame($cart_item->id, $cart_item_for_check->id);

        $this->assertSame($cart_item->product_id, $cart_item_for_check->product_id);
    }

    public function test_remove_cart_item_by_product_id()
    {
        $cart = $this->cart_service->getCart();

        $cart->items()->saveMany(CartItem::factory()->count(3)->make());

        $cart_item = $cart->items->random();

        // make sure original has that cart item
        $this->assertSame($cart->items->where('id', $cart_item->id)->first()->id, $cart_item->id);

        $result = $this->cart_service->removeCartItem($cart_item->product_id);

        $this->assertTrue($result);

        $cart->refresh();

        $this->assertNull($cart->items->where('id', $cart_item->id)->first());

        // test should return false if cart item not found
        $result = $this->cart_service->removeCartItem($cart_item->product_id);

        $this->assertFalse($result);
    }

    public function test_add_cart_item()
    {
        $product = Product::factory()->create();

        $cart = $this->cart_service->getCart();

        // make sure at the first time that product is not exists
        $this->assertNull($cart->items->where('product_id', $product->id)->first());

        $cart_item_data = [
            'product_id' => $product->id,
            'quantity' => 15,
            'notes' => 'here my notes',
        ];

        $this->cart_service->addCartItem($cart_item_data);

        $cart->refresh();

        $cart_item = $cart->items->where('product_id', $product->id)->first();

        $this->assertNotNull($cart_item);

        // verify created cart item data is correct
        $this->assertSame($cart_item->product_id, $cart_item_data['product_id']);

        $this->assertSame($cart_item->notes, $cart_item_data['notes']);

        $this->assertSame($cart_item->quantity, $cart_item_data['quantity']);
    }
    public function test_adding_existing_product_updates_one_item(): void
    {
        // Arrange
        $product = Product::factory()->create();
        $cart = $this->cart_service->getCart();
        $this->cart_service->addCartItem([
            'product_id' => $product->id, 'quantity' => 2, 'notes' => 'Original',
        ]);

        // Act
        $this->cart_service->addCartItem([
            'product_id' => $product->id, 'quantity' => 7, 'notes' => 'Updated',
        ]);

        // Assert
        $this->assertSame(1, $cart->items()->count());
        $item = $this->cart_service->getCartItem($product->id);
        $this->assertSame(7, $item->quantity);
        $this->assertSame('Updated', $item->notes);
    }

    public function test_cart_is_isolated_between_session_users(): void
    {
        // Arrange
        Session::put('user_id', 'ALICE');
        $alice = $this->cart_service->getCart();
        $product = Product::factory()->create();
        $this->cart_service->addCartItem([
            'product_id' => $product->id, 'quantity' => 2, 'notes' => '',
        ]);

        // Act
        Session::put('user_id', 'BOB');
        $bob = $this->cart_service->getCart();

        // Assert
        $this->assertNotSame($alice->id, $bob->id);
        $this->assertNull($this->cart_service->getCartItem($product->id));
        $this->assertFalse($this->cart_service->removeCartItem($product->id));
        $this->assertSame(1, $alice->items()->count());
    }
}
