<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

class ReviewEligibility
{
    public function eligibleItems(User $user, Product $product): Collection
    {
        return OrderItem::where('product_id', $product->id)
            ->whereHas('order', fn ($query) => $query->where('user_id', $user->id)->where('status', 'completed'))
            ->whereDoesntHave('review')
            ->with('order:id,reference,user_id,status,created_at')
            ->orderByDesc('id')
            ->get();
    }

    public function ownsCompletedItem(User $user, Product $product, OrderItem $item): bool
    {
        return $item->product_id === $product->id
            && $item->order?->user_id === $user->id
            && $item->order->status === 'completed';
    }
}
