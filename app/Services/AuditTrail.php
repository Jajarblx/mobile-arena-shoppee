<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AuditTrail
{
    private const SAFE_FIELDS = [
        'stock', 'reserved_stock', 'status', 'payment_status', 'amount',
        'estimated_cost', 'technician_notes', 'staff_notes', 'courier_name', 'tracking_number',
        'shipped_at', 'completed_at', 'return_disposition', 'stock_restored_at', 'stock_state',
        'category_id', 'name', 'slug', 'brand', 'model_name', 'condition', 'grade',
        'price', 'compare_price', 'sku', 'short_description', 'description',
        'specifications', 'image', 'images', 'featured', 'active', 'warranty_note',
    ];

    public function record(
        ?User $actor,
        string $action,
        Model $entity,
        array $before = [],
        array $after = [],
        ?string $reason = null,
    ): AuditLog {
        $http = ! app()->runningInConsole();

        return AuditLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => class_basename($entity),
            'auditable_id' => $entity->id,
            'old_values' => Arr::only($before, self::SAFE_FIELDS) ?: null,
            'new_values' => Arr::only($after, self::SAFE_FIELDS) ?: null,
            'reason' => $reason,
            'ip_address' => $http ? request()->ip() : null,
            'user_agent' => $http ? mb_substr((string) request()->userAgent(), 0, 255) : null,
        ]);
    }
}
