<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class JournalEntry extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'uuid',
        'idempotency_key',
        'description',
        'transaction_date',
        'currency',
        'source',
        'reference_type',
        'reference_id',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (JournalEntry $entry) {
            if (empty($entry->uuid)) {
                $entry->uuid = (string) Str::uuid();
            }
            if (empty($entry->idempotency_key)) {
                $entry->idempotency_key = (string) Str::uuid();
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

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
