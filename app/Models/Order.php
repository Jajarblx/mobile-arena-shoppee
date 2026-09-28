<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    public const STATUS_LABELS = [
        'pending_payment' => 'Pending payment',
        'processing' => 'Processing',
        'to_ship' => 'To ship',
        'shipped' => 'Shipped',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    public const PAYMENT_METHOD_LABELS = [
        'pay_at_store' => 'Pay at store',
        'cash_on_pickup' => 'Cash on pickup',
        'cash_on_delivery' => 'Cash on delivery',
        'gcash_manual' => 'GCash (manual confirmation)',
    ];

    public const PAYMENT_STATUS_LABELS = [
        'unpaid' => 'Unpaid',
        'pending_verification' => 'Pending verification',
        'paid' => 'Paid',
        'failed' => 'Confirmation rejected',
        'refunded' => 'Refunded',
    ];

    protected $fillable = [
        'reference', 'customer_name', 'email', 'phone', 'fulfillment', 'address',
        'payment_method', 'payment_status', 'payment_reference', 'payment_confirmed_at',
        'subtotal', 'shipping_fee', 'checkout_token', 'status', 'stock_state',
        'reservation_expires_at', 'notes',
        'courier_name', 'tracking_number', 'shipped_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2', 'shipping_fee' => 'decimal:2',
            'reservation_expires_at' => 'datetime', 'payment_confirmed_at' => 'datetime',
            'shipped_at' => 'datetime', 'completed_at' => 'datetime',
        ];
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function statusEvents()
    {
        return $this->hasMany(OrderStatusEvent::class);
    }

    public function refund()
    {
        return $this->hasOne(Refund::class);
    }

    public function getTotalAttribute(): float
    {
        return (float) $this->subtotal + (float) ($this->shipping_fee ?? 0);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabelFor($this->status);
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return self::PAYMENT_METHOD_LABELS[$this->payment_method] ?? ucwords(str_replace('_', ' ', $this->payment_method));
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return self::PAYMENT_STATUS_LABELS[$this->payment_status] ?? ucwords(str_replace('_', ' ', $this->payment_status));
    }

    public static function statusLabelFor(string $status): string
    {
        return self::STATUS_LABELS[$status === 'pending' ? 'pending_payment' : $status]
            ?? ucwords(str_replace('_', ' ', $status));
    }
}
