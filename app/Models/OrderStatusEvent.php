<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatusEvent extends Model
{
    protected $fillable = ['status', 'note'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
