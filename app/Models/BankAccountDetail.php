<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccountDetail extends Model
{
    protected $fillable = [
        'account_id',
        'bank_name',
        'account_number',
        'branch',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
