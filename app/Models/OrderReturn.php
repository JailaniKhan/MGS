<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class OrderReturn extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'uuid',
        'order_id',
        'customer_id',
        'return_date',
        'reason',
        'total_amount',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (OrderReturn $orderReturn) {
            if (empty($orderReturn->uuid)) {
                $orderReturn->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'total_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class);
    }
}
