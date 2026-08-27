<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class CashbookEntry extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
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
        return $this->currency === 'USD' ? '$' : 'افغانی';
    }
}
