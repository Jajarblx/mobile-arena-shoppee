<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class InventoryLedger
{
    public function record(
        Product $before,
        Product $after,
        string $type,
        ?User $actor = null,
        ?Model $reference = null,
        ?string $reason = null,
        array $metadata = [],
    ): InventoryMovement {
        return InventoryMovement::create([
            'product_id' => $before->id,
            'product_name' => $before->name,
            'product_sku' => $before->sku,
            'user_id' => $actor?->id,
            'type' => $type,
            'quantity_change' => (int) $after->stock - (int) $before->stock,
            'stock_before' => $before->stock,
            'stock_after' => $after->stock,
            'reserved_before' => $before->reserved_stock,
            'reserved_after' => $after->reserved_stock,
            'reference_type' => $reference ? class_basename($reference) : null,
            'reference_id' => $reference?->id,
            'reason' => $reason,
            'metadata' => $metadata ?: null,
        ]);
    }
}
