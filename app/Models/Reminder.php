<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'remindable_type',
        'remindable_id',
        'amount',
        'currency',
        'channel',
        'message',
        'status',
        'provider_message_id',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'sent_at' => 'datetime',
    ];

    public function remindable()
    {
        return $this->morphTo();
    }
}
