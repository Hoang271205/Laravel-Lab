<?php

namespace App\Http\Controllers;

use App\Events\OrderCreated;
use App\Models\Cart;
use App\Models\Order;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function index(CartService $cart)
    {
        $cart = $cart->getCart();
        if ($cart->items->isEmpty()) {
            return redirect()->route('homepage');
        }

        return view('checkout.index', compact('cart'));
    }

    public function store(Request $request, CartService $cart)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:30',
            'email' => 'required|email|max:255',
            'shipping_address' => 'required|string|max:255',
            'coupon_code' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:255',
        ]);
        $activeCart = $cart->getCart();
        $order = DB::transaction(function () use ($activeCart, $validated) {
            $lockedCart = Cart::query()->lockForUpdate()->findOrFail($activeCart->id);
            abort_if($lockedCart->order()->exists(), 409);
            abort_if($lockedCart->items()->doesntExist(), 422, 'Your cart is empty.');

            return $lockedCart->order()->create([
                ...$validated,
                'code' => 'OR-'.Str::upper(Str::random(12)),
            ]);
        });
        OrderCreated::dispatch($order);

        return redirect(URL::signedRoute('orders.complete', ['order' => $order->id]));
    }

    public function show(Request $request, Order $order)
    {
        abort_unless($request->hasValidSignature(), 401);
        $order->load('cart.items.product');

        return view('checkout.show', compact('order'));
    }
}
