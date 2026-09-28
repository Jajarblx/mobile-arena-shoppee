<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RepairBooking extends Model
{
    use HasFactory;

    public const STATUS_LABELS = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'diagnosing' => 'Diagnosing',
        'in_repair' => 'In repair',
        'ready_for_pickup' => 'Ready for pickup',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'reference', 'customer_name', 'phone', 'email', 'device_type', 'brand_model',
        'issue', 'preferred_date', 'service_type', 'status', 'estimated_cost', 'technician_notes',
    ];

    protected function casts(): array
    {
        return ['preferred_date' => 'date', 'estimated_cost' => 'decimal:2'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function statusEvents()
    {
        return $this->hasMany(RepairStatusEvent::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status === 'requested' ? 'pending' : $this->status]
            ?? ucwords(str_replace('_', ' ', $this->status));
    }
}
