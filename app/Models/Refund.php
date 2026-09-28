<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    public const STATUS_LABELS = [
        'requested' => 'Requested',
        'under_review' => 'Under review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'refunded' => 'Refunded',
    ];

    protected $fillable = [
        'user_id', 'amount', 'reason', 'status', 'staff_notes', 'return_disposition',
        'reviewed_by', 'requested_at', 'reviewed_at', 'refunded_at', 'stock_restored_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2', 'requested_at' => 'datetime',
            'reviewed_at' => 'datetime', 'refunded_at' => 'datetime',
            'stock_restored_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucwords(str_replace('_', ' ', $this->status));
    }
}
