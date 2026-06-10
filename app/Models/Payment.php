<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['order_id', 'amount', 'notes'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}