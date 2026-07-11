<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $fillable = ['supplier_id', 'person_type', 'person_id', 'total_amount', 'currency', 'status', 'tax_rate', 'tax_amount', 'subtotal', 'tax_type'];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'person_id');
    }

    public function getPartyAttribute()
    {
        if ($this->person_type === 'customer') {
            return $this->customer;
        }

        return $this->supplier;
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function purchasePayments()
    {
        return $this->hasMany(PurchasePayment::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'purchase_items')
            ->withPivot('quantity', 'unit_price', 'subtotal');
    }

    public function getPaidAmountAttribute()
    {
        return (float) $this->purchasePayments()->sum('amount');
    }

    public function getReturnedAmountAttribute()
    {
        return (float) PurchaseReturn::where('purchase_id', $this->id)
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
