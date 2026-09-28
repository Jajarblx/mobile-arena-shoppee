<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $fillable = [
        'recipient_name', 'phone', 'region', 'province', 'city', 'barangay',
        'postal_code', 'street_address', 'landmark', 'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getFormattedAttribute(): string
    {
        return implode(', ', array_filter([
            $this->street_address,
            'Brgy. '.$this->barangay,
            $this->city,
            $this->province,
            $this->region,
            $this->postal_code,
            $this->landmark ? 'Landmark: '.$this->landmark : null,
        ]));
    }

    public function getDeliverySnapshotAttribute(): string
    {
        return $this->recipient_name.' · '.$this->phone."\n".$this->formatted;
    }
}
