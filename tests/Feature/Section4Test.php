<?php

namespace Tests\Feature;

use App\Livewire\Checkout\AddCartItemPopup;
use App\Livewire\Checkout\CartBar;
use App\Livewire\SettingDropdown;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Notifications\OrderCreatedNotification;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class Section4Test extends TestCase
{
    use RefreshDatabase;

    private function customer(): array
    {
        return ['full_name' => 'Section4 Tester', 'phone_number' => '0901234567', 'email' => 'tester@example.test', 'shipping_address' => '12 Test Street', 'notes' => 'No onions'];
    }

    public function test_homepage_renders_seeded_menu_and_livewire(): void
    {
        $this->seed();
        $this->get('/')->assertOk()->assertSee('Cappuchino')->assertSee('Chicken')->assertSee('wire:snapshot', false);
    }

    public function test_add_edit_remove_cart_and_quantity_validation(): void
    {
        $product = Product::factory()->create(['price' => 35000]);
        Livewire::test(AddCartItemPopup::class)
            ->call('displayAddCartItem', $product->id)
            ->call('decrement')->assertSet('quantity', 1)
            ->set('quantity', 0)->call('addCartItem')->assertHasErrors(['quantity'])
            ->set('quantity', 3)->set('notes', 'Less sugar')->call('addCartItem')
            ->assertDispatched('CART_UPDATED_EVENT')->assertDispatched('close-offcanvas');
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 3, 'notes' => 'Less sugar']);
        Livewire::test(AddCartItemPopup::class)->call('displayAddCartItem', $product->id)
            ->assertSet('quantity', 3)->set('quantity', 2)->call('addCartItem');
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertSame(70000.0, app(CartService::class)->getCart()->total_amount);
        Livewire::test(CartBar::class)->assertSet('display', true)->call('removeCartItem', $product->id)->assertSet('display', false);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_cart_sessions_are_isolated(): void
    {
        $product = Product::factory()->create();
        Session::put('user_id', 'customer-a');
        app(CartService::class)->addCartItem(['product_id' => $product->id, 'quantity' => 1]);
        Session::put('user_id', 'customer-b');
        $this->assertCount(0, app(CartService::class)->getCart()->items);
        $this->assertFalse(app(CartService::class)->removeCartItem($product->id));
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_checkout_creates_order_sends_notification_and_protects_invoice(): void
    {
        Notification::fake();
        $product = Product::factory()->create(['price' => 50000]);
        $cart = Cart::factory()->create(['user_id' => 'buyer']);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 2]);
        $this->withSession(['user_id' => 'buyer'])->get('/checkout')->assertOk()->assertSee('100,000');
        $response = $this->withSession(['user_id' => 'buyer'])->post('/checkout', $this->customer());
        $order = Order::firstOrFail();
        $this->assertSame($cart->id, $order->cart_id);
        $response->assertRedirect(URL::signedRoute('orders.complete', ['order' => $order->id]));
        Notification::assertSentOnDemand(OrderCreatedNotification::class, fn ($notification, $channels, $notifiable) => $channels === ['mail'] && $notifiable->routes['mail'] === ['tester@example.test' => 'Section4 Tester']);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee($order->code);
        $this->get($order->getCheckoutConfirmationPath())->assertOk()->assertSee('Section4 Tester')->assertSee('100,000');
        $this->get('/checkout/'.$order->id)->assertUnauthorized();
        $this->get('/order-complete/'.$order->id)->assertUnauthorized();
        $this->get($order->getCheckoutConfirmationPath().'&tampered=1')->assertUnauthorized();
        $this->withSession(['user_id' => 'buyer'])->post('/checkout', $this->customer())->assertStatus(422);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_checkout_rejects_empty_cart_and_invalid_customer(): void
    {
        $this->get('/checkout')->assertRedirect('/');
        $this->post('/checkout', [])->assertSessionHasErrors(['full_name', 'email', 'phone_number', 'shipping_address']);
        $this->post('/checkout', $this->customer())->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_theme_locale_persist_and_translations_render(): void
    {
        Livewire::test(SettingDropdown::class)->call('setTheme', 'theme-dark')->assertDispatched('theme-changed', theme: 'theme-dark');
        $this->assertSame('theme-dark', Session::get('theme'));
        Livewire::test(SettingDropdown::class)->call('setLocale', 'vi');
        $this->assertSame('vi', Session::get('locale'));
        $this->withSession(['locale' => 'vi', 'theme' => 'theme-dark'])->get('/')->assertOk()->assertSee('Giao diện')->assertSee('theme-dark');
        $this->withSession(['locale' => 'ja'])->get('/')->assertOk()->assertSee('テーマ');
        Livewire::test(SettingDropdown::class)->call('setLocale', '../../invalid')->assertStatus(422);
        Livewire::test(SettingDropdown::class)->call('setTheme', 'invalid')->assertStatus(422);
    }
}
