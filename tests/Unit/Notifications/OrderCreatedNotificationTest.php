<?php

namespace Tests\Unit\Notifications;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Notifications\OrderCreatedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class OrderCreatedNotificationTest extends TestCase
{
    public function test_should_send_order_created_notification()
    {
        Notification::fake();

        Session::put('user_id', 'DUMMY_USER_ID');

        $cart = Cart::factory()
            ->has(CartItem::factory()->count(3), 'items')
            ->create(['user_id' => 'DUMMY_USER_ID']);

        $body = [
            'full_name' => 'John',
            'phone_number' => '+84111122222',
            'email' => 'john@example.com',
            'shipping_address' => 'lorem ipsum',
        ];

        $this->withSession(['user_id' => 'DUMMY_USER_ID'])
            ->post(route('checkout.store'), $body)
            ->assertRedirect();

        $order = Order::query()->where('cart_id', $cart->id)->first();

        $this->assertNotNull($order);

        Notification::assertSentOnDemand(
            OrderCreatedNotification::class,
            function (OrderCreatedNotification $notification, array $channels, object $notifiable) use ($order) {
                return in_array('mail', $channels, true)
                    && array_key_first($notifiable->routes['mail']) === $order->email;
            }
        );
    }
}
