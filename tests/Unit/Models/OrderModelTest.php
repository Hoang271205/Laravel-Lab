<?php

namespace Tests\Unit\Models;

use App\Models\Cart;
use App\Models\Order;
use Tests\TestCase;

class OrderModelTest extends TestCase
{
    public function test_able_to_get_cart()
    {
        $order = Order::factory()->create();

        $this->assertInstanceOf(Cart::class, $order->cart);
    }

    public function test_able_to_get_signed_path_to_order_detail_page()
    {
        $this->withoutExceptionHandling();

        $order = Order::factory()->create();

        $path = $order->getCheckoutConfirmationPath();

        $this->get($path)->assertOk();
    }

    public function test_confirmation_rejects_missing_signature(): void
    {
        // Arrange
        $order = Order::factory()->create();
        $path = strtok($order->getCheckoutConfirmationPath(), '?');

        // Act and Assert
        $this->get($path)->assertUnauthorized();
    }
}
