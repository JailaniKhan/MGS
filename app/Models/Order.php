<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['customer_id', 'status', 'total_amount'];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getPaidAmountAttribute()
    {
        return $this->payments()->sum('amount');
    }

    public function getRemainingAmountAttribute()
    {
        return $this->total_amount - $this->paid_amount;
    }

    public function getIsFullyPaidAttribute()
    {
        return $this->remaining_amount <= 0;
    }
}
