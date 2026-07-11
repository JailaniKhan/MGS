<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function person()
    {
        if ($this->person_type === 'customer') {
            return $this->belongsTo(Customer::class, 'person_id');
        }
        return $this->belongsTo(Supplier::class, 'person_id');
    }
}
