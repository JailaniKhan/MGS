<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PartyPayment extends Model
{
    protected $table = 'party_payments';

    protected $fillable = [
        'person_type',
        'person_id',
        'amount',
        'currency',
        'type',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // Party payments have no user_id of their own; scope them through the
        // customer/supplier they reference, mirroring BelongsToUser.
        static::addGlobalScope('user', function (Builder $builder) {
            if (! Auth::check()) {
                return;
            }

            $userId = Auth::id();

            $builder->where(function (Builder $q) use ($userId) {
                $customerIds = Customer::where('user_id', $userId)->pluck('id');
                $supplierIds = Supplier::where('user_id', $userId)->pluck('id');

                $q->where(fn ($qq) => $qq->where('person_type', 'customer')->whereIn('person_id', $customerIds))
                    ->orWhere(fn ($qq) => $qq->where('person_type', 'supplier')->whereIn('person_id', $supplierIds));
            });
        });
    }

    public function person()
    {
        if ($this->person_type === 'customer') {
            return $this->belongsTo(Customer::class, 'person_id');
        }
        return $this->belongsTo(Supplier::class, 'person_id');
    }
}
