<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Exceptions\CartException;
use App\Http\Requests\OrderRequest;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    /**
     * Show the checkout form, or send the customer back to a cart that cannot be ordered as it stands.
     */
    public function create(Request $request, CartService $cartService): View|RedirectResponse
    {
        $cart = $cartService->cartFor($request->user());

        if ($cart->items->isEmpty()) {
            return redirect()->route('cart.show');
        }

        if ($cart->hasUnpurchasableItems()) {
            return redirect()
                ->route('cart.show')
                ->with('error', 'Update or remove the items that cannot be bought before you check out.');
        }

        return view('checkout.create', [
            'cart' => $cart,
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }

    /**
     * Place an order for everything in the cart.
     */
    public function store(OrderRequest $request, OrderService $orderService): RedirectResponse
    {
        try {
            $order = $orderService->placeOrder(
                $request->user(),
                $request->shippingAddress(),
                $request->paymentMethod(),
            );
        } catch (CartException $exception) {
            return redirect()->route('cart.show')->with('error', $exception->getMessage());
        }

        return redirect()->route('orders.success', $order->order_number);
    }
}
