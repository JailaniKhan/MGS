<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Account extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'uuid',
        'name',
        'type',
        'phone',
        'address',
        'currency',
        'legacy_id',
        'legacy_type',
    ];

    protected static function booted(): void
    {
        static::creating(function (Account $account) {
            if (empty($account->uuid)) {
                $account->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function bankDetails(): HasOne
    {
        return $this->hasOne(BankAccountDetail::class);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeParties($query)
    {
        return $query->whereIn('type', ['customer', 'supplier']);
    }

    public function getCurrencySymbolAttribute(): string
    {
        return $this->currency === 'USD' ? '$' : 'افغ';
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'customer' => 'ګیراک',
            'supplier' => 'پلورونکی',
            'bank' => 'بانک',
            'cash' => 'نغد',
            'income' => 'عاید',
            'expense' => 'لګښت',
            default => $this->type,
        };
    }
}
