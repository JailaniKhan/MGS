<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PurchasePayment extends Model
{
    protected $fillable = ['purchase_id', 'amount', 'currency', 'notes'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // Purchase payments have no user_id of their own; scope them through
        // the purchase they belong to, mirroring BelongsToUser.
        static::addGlobalScope('user', function (Builder $builder) {
            if (Auth::check()) {
                $builder->whereHas('purchase', fn ($q) => $q->where('user_id', Auth::id()));
            }
        });
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }
}
