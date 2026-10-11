<?php

namespace App\Livewire\Checkout;

use App\Events\BrowserEvent;
use App\Events\LivewireEvent;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AddCartItemPopup extends Component
{
    const MIN_QUANTITY = 1;

    public $product;

    public $quantity = self::MIN_QUANTITY;

    public $notes = '';

    protected $listeners = [
        LivewireEvent::USER_SELECT_PRODUCT => 'displayAddCartItem',
    ];

    public function displayAddCartItem(CartService $cart, $product_id): void
    {
        $this->product = Product::findOrFail($product_id);

        $this->resetFormData();

        $cart_item = $cart->getCartItem($product_id);

        if ($cart_item) {
            $this->quantity = $cart_item->quantity;
            $this->notes = $cart_item->notes;
        }

        $this->dispatch(BrowserEvent::DISPLAY_OFFCANVAS);

    }

    public function increment(): void
    {
        $this->quantity = min(99, $this->quantity + 1);
    }

    public function decrement(): void
    {
        if ($this->quantity > self::MIN_QUANTITY) {
            $this->quantity--;
        }
    }

    public function addCartItem(CartService $cart): void
    {
        $this->validate(['quantity' => 'required|integer|min:1|max:99', 'notes' => 'nullable|string|max:1000']);
        abort_unless($this->product && Product::whereKey($this->product->id)->exists(), 422);
        $cart_item = $this->getCartItemInstance();

        $cart->addCartItem($cart_item);

        $this->dispatch(LivewireEvent::CART_UPDATED_EVENT);

        $this->dispatch(BrowserEvent::CLOSE_OFFCANVAS);

    }

    private function resetFormData(): void
    {
        $this->quantity = self::MIN_QUANTITY;
        $this->notes = '';
    }

    private function getCartItemInstance(): array
    {
        return [
            'product_id' => $this->product->id,
            'quantity' => $this->quantity,
            'notes' => $this->notes,
        ];
    }

    public function render(): View
    {
        return view('livewire.checkout.add-cart-item-popup');
    }
}
