<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = ['rating', 'title', 'body'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'is_verified_purchase' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function getDisplayNameAttribute(): string
    {
        $parts = preg_split('/\s+/u', trim((string) $this->user?->name));
        $first = $parts[0] ?? 'Customer';
        $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1).'‧' : '';

        return trim($first.' '.$last);
    }
}
