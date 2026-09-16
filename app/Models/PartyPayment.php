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
        // customer/supplier they reference, mirroring BelongsToUser. The user
        // filter runs as subqueries (Customer/Supplier carry their own user
        // scope), so no IDs are loaded into PHP per query.
        static::addGlobalScope('user', function (Builder $builder) {
            if (! Auth::check()) {
                return;
            }

            $builder->where(function (Builder $q) {
                $q->where(fn (Builder $qq) => $qq->where('person_type', 'customer')->whereIn('person_id', Customer::select('id')))
                    ->orWhere(fn (Builder $qq) => $qq->where('person_type', 'supplier')->whereIn('person_id', Supplier::select('id')));
            });
        });
    }

    /**
     * The party this payment belongs to. Must be a real morphTo (not a
     * type-branching belongsTo): eager loading (with('person')) invokes the
     * relation on an empty prototype where person_type is null, which a
     * conditional belongsTo would resolve to the wrong table.
     */
    public function person()
    {
        return $this->morphTo();
    }
}
