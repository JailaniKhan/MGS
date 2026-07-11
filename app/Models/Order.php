<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['customer_id', 'person_type', 'person_id', 'status', 'total_amount', 'currency', 'tax_rate', 'tax_amount', 'subtotal', 'tax_type'];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'person_id');
    }

    public function getPartyAttribute()
    {
        if ($this->person_type === 'supplier') {
            return $this->supplier;
        }

        return $this->customer;
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
        return (float) $this->payments()->sum('amount');
    }

    public function getReturnedAmountAttribute()
    {
        return (float) OrderReturn::where('order_id', $this->id)
            ->where('status', '!=', 'cancelled')
            ->where('currency', $this->currency)
            ->sum('total_amount');
    }

    public function getRemainingAmountAttribute()
    {
        return max(0, $this->total_amount - $this->paid_amount - $this->returned_amount);
    }

    public function getIsFullyPaidAttribute()
    {
        return $this->remaining_amount <= 0;
    }

    public function getDisplayStatusAttribute()
    {
        if ($this->status === 'cancelled') {
            return 'cancelled';
        }

        if ($this->is_fully_paid) {
            return 'paid';
        }

        return $this->status;
    }

    public function getCurrencySymbolAttribute()
    {
        return $this->currency === 'USD' ? '$' : 'افغ';
    }
}
