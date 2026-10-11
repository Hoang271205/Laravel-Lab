<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\Models\Cart;
use App\Models\Order;
use App\Models\CartItem;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Notification;

class CheckoutControllerTest extends TestCase
{
    public function test_can_render_checkout_page()
    {
        Cart::factory()->has(CartItem::factory(), 'items')
            ->create(['user_id' => 'DUMMY_USER_ID']);

        $this->withSession(['user_id' => 'DUMMY_USER_ID'])->get(route('checkout.index'))
            ->assertOk();
    }

    public function test_display_correct_cart_data()
    {
        Notification::fake();
        Session::put('user_id', 'DUMMY_USER_ID');

        $cart = Cart::factory()
            ->has(CartItem::factory()->count(3)->sequence(
                ['quantity' => 2], ['quantity' => 4], ['quantity' => 7]
            ), 'items')
            ->create(['user_id' => 'DUMMY_USER_ID']);

        $response = $this->withSession(['user_id' => 'DUMMY_USER_ID'])
            ->get(route('checkout.index'));

        foreach ($cart->items as $item) {
            $response
                ->assertSee($item->product->name)
                ->assertSee($item->product->getFormattedTotalAmount($item->quantity));
        }
    }

    public function test_got_validation_errors()
    {
        $this->post(route('checkout.store'))
            ->assertInvalid([
                'full_name',
                'phone_number',
                'email',
                'shipping_address',
            ]);
    }

    public function test_can_create_order_when_submit_checkout()
    {
        Notification::fake();
        Session::put('user_id', 'DUMMY_USER_ID');

        $cart = Cart::factory()
            ->has(CartItem::factory()->count(3)->sequence(
                ['quantity' => 2], ['quantity' => 4], ['quantity' => 7]
            ), 'items')
            ->create(['user_id' => 'DUMMY_USER_ID']);

        $body = [
            'full_name' => 'John',
            'phone_number' => '+84111122222',
            'email' => 'john@example.com',
            'shipping_address' => 'lorem ipsum',
        ];

        $response = $this->withSession(['user_id' => 'DUMMY_USER_ID'])
            ->post(route('checkout.store'), $body);

        $order = Order::query()->where('cart_id', $cart->id)->first();

        $this->assertNotNull($order);

        $this->assertDatabaseHas('orders', array_merge(['id' => $order->id], $body));

        $response->assertRedirectToSignedRoute('orders.complete', ['order' => $order->id]);
    }
    public function test_empty_cart_redirects_to_homepage(): void
    {
        $this->get(route('checkout.index'))
            ->assertRedirectToRoute('homepage');
    }

    public function test_empty_cart_cannot_create_order(): void
    {
        Notification::fake();

        $this->post(route('checkout.store'), [
            'full_name' => 'John', 'phone_number' => '+84111122222',
            'email' => 'john@example.com', 'shipping_address' => 'Example street',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        Notification::assertNothingSent();
    }
}
