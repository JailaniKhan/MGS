<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['customer_id', 'status', 'total_amount', 'currency'];

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
        // Sum payments directly linked to this order
        $orderPayments = $this->payments()->sum('amount');
        
        // Also include ledger payments made for this customer in the same currency
        $ledgerPayments = $this->customer 
            ? LedgerEntry::where('person_type', 'customer')
                ->where('person_id', $this->customer_id)
                ->where('type', 'payment_received')
                ->where('currency', $this->currency)
                ->sum('amount')
            : 0;

        return $orderPayments + $ledgerPayments;
    }

    public function getRemainingAmountAttribute()
    {
        return max(0, $this->total_amount - $this->paid_amount);
    }

    public function getIsFullyPaidAttribute()
    {
        return $this->remaining_amount <= 0;
    }

    public function getCurrencySymbolAttribute()
    {
        return $this->currency === 'USD' ? '$' : 'افغ';
    }
}