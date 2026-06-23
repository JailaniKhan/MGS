<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
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
        return $this->belongsTo(Customer::class, 'person_id');
    }

    public function getPersonNameAttribute()
    {
        if ($this->person_type === 'customer') {
            return optional(Customer::find($this->person_id))->name;
        } elseif ($this->person_type === 'supplier') {
            return optional(Supplier::find($this->person_id))->name;
        }
        return null;
    }

    public function getCurrencySymbolAttribute()
    {
        return $this->currency === 'USD' ? '$' : 'افغ';
    }
}