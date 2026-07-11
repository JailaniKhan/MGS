<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PurchaseReturn extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'uuid',
        'purchase_id',
        'supplier_id',
        'return_date',
        'reason',
        'total_amount',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (PurchaseReturn $purchaseReturn) {
            if (empty($purchaseReturn->uuid)) {
                $purchaseReturn->uuid = (string) Str::uuid();
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

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }
}
