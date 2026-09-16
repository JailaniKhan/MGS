<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use BelongsToUser;

    protected $fillable = ['supplier_id', 'person_type', 'person_id', 'total_amount', 'currency', 'status', 'tax_rate', 'tax_amount', 'subtotal', 'tax_type'];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'person_id');
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

    public function purchaseReturns()
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'purchase_items')
            ->withPivot('quantity', 'unit_price', 'subtotal');
    }

    public function getPaidAmountAttribute()
    {
        // Only payments in the purchase's own currency may settle it; a foreign-currency
        // payment must never reduce the balance of a purchase it does not match.
        return bcadd('0.00', (string) ($this->purchasePayments()
            ->where('currency', $this->currency)
            ->sum('amount') ?: '0'), 2);
    }

    public function getReturnedAmountAttribute()
    {
        return bcadd('0.00', (string) (PurchaseReturn::where('purchase_id', $this->id)
            ->where('status', '!=', 'cancelled')
            ->where('currency', $this->currency)
            ->sum('total_amount') ?: '0'), 2);
    }

    public function getRemainingAmountAttribute()
    {
        // Use bcmath: total_amount is a decimal string, paid/returned are canonical strings.
        $afterPaid = bcsub((string) $this->total_amount, $this->paid_amount, 2);
        $afterReturned = bcsub($afterPaid, $this->returned_amount, 2);

        // Clamp at zero.
        return bccomp($afterReturned, '0', 2) >= 0 ? $afterReturned : '0.00';
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

        if ($this->status === 'processing') {
            return 'processing';
        }

        // Payment-aware: a purchase that still owes money must never read as
        // "completed" — show whether anything has been settled yet.
        if (bccomp($this->paid_amount, '0', 2) > 0) {
            return 'partial';
        }

        return 'pending';
    }

    public function getCurrencySymbolAttribute()
    {
        return $this->currency === 'USD' ? '$' : 'افغانی';
    }
}
