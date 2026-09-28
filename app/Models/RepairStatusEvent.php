<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepairStatusEvent extends Model
{
    protected $fillable = ['status', 'note'];

    public function repairBooking()
    {
        return $this->belongsTo(RepairBooking::class);
    }
}
