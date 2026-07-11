<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashbookEntry extends Model
{
    protected $fillable = [
        'type',
        'amount',
        'currency',
        'category',
        'notes',
        'entry_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'entry_date' => 'date',
    ];

    public function getCurrencySymbolAttribute()
    {
        return $this->currency === 'USD' ? '$' : 'افغ';
    }
}
