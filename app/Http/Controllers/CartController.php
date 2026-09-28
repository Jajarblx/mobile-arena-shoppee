<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index()
    {
        [$items, $subtotal] = $this->cartDetails();
        return view('cart.index', compact('items', 'subtotal'));
    }

    public function store(Request $request, Product $product)
    {
        abort_unless($product->active && $product->available_stock > 0, 404);
        $quantity = max(1, min((int) $request->input('quantity', 1), $product->available_stock));
        $cart = session()->get('cart', []);
        $current = $cart[$product->id] ?? 0;
        $cart[$product->id] = min($current + $quantity, $product->available_stock);
        session()->put('cart', $cart);

        return back()->with('success', "{$product->name} added to your cart.");
    }

    public function update(Request $request, Product $product)
    {
        $quantity = max(0, min((int) $request->input('quantity', 1), $product->available_stock));
        $cart = session()->get('cart', []);

        if ($quantity === 0) {
            unset($cart[$product->id]);
        } else {
            $cart[$product->id] = $quantity;
        }

        session()->put('cart', $cart);
        return back()->with('success', 'Cart updated.');
    }

    public function destroy(Product $product)
    {
        $cart = session()->get('cart', []);
        unset($cart[$product->id]);
        session()->put('cart', $cart);
        return back()->with('success', 'Item removed from cart.');
    }

    public static function count(): int
    {
        return array_sum(session()->get('cart', []));
    }

    public function cartDetails(): array
    {
        $cart = session()->get('cart', []);
        if (empty($cart)) {
            return [collect(), 0];
        }

        $products = Product::whereIn('id', array_keys($cart))->get()->keyBy('id');
        $items = collect($cart)->map(function ($quantity, $productId) use ($products) {
            $product = $products->get($productId);
            if (!$product) {
                return null;
            }

            return [
                'product' => $product,
                'quantity' => $quantity,
                'line_total' => (float) $product->price * $quantity,
                'stock_issue' => ! $product->active || $quantity > $product->available_stock,
            ];
        })->filter()->values();

        return [$items, $items->sum('line_total')];
    }
}
