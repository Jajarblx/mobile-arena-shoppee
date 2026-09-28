<?php

namespace App\Http\Controllers;

use App\Models\Product;

class AdminInventoryController extends Controller
{
    public function show(Product $product)
    {
        return view('admin.inventory-history', [
            'product' => $product,
            'movements' => $product->inventoryMovements()->with('actor')->latest()->paginate(25),
        ]);
    }
}
