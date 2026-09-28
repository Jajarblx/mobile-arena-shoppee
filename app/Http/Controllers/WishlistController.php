<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function store(Request $request, Product $product)
    {
        abort_unless($product->active, 404);
        $request->user()->wishlistProducts()->syncWithoutDetaching([$product->id]);

        return back()->with('success', 'Saved to your wishlist.');
    }

    public function destroy(Request $request, Product $product)
    {
        $request->user()->wishlistProducts()->detach($product->id);

        return back()->with('success', 'Removed from your wishlist.');
    }
}
