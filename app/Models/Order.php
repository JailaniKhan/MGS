<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use BelongsToUser;

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
        return bcadd('0.00', (string) ($this->payments()->sum('amount') ?: '0'), 2);
    }

    public function getReturnedAmountAttribute()
    {
        return bcadd('0.00', (string) (OrderReturn::where('order_id', $this->id)
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

        return $this->status;
    }

    public function getCurrencySymbolAttribute()
    {
        return $this->currency === 'USD' ? '$' : 'افغ';
    }
}
