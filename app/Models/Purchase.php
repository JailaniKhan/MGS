<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $fillable = ['supplier_id', 'total_amount', 'currency', 'status'];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
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
        // Sum payments from the purchase payments table
        $purchasePayments = $this->purchasePayments()->sum('amount');
        
        // Also sum ledger payments made for this purchase's supplier
        $ledgerPayments = $this->supplier 
            ? LedgerEntry::where('person_type', 'supplier')
                ->where('person_id', $this->supplier_id)
                ->where('type', 'payment_made')
                ->where('currency', $this->currency)
                ->sum('amount')
            : 0;

        return $purchasePayments + $ledgerPayments;
    }

    public function getRemainingAmountAttribute()
    {
        return $this->total_amount - $this->paid_amount;
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
