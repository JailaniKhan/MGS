<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Per-process read memo. Settings are read on EVERY request (locale,
     * app-lock, dashboard rate, company header) — on the phone's persistent
     * runtime that meant 4-8 identical SQLite queries per page. Every writer
     * flushes: set() drops its key, bulk writers (backup restore) and tests
     * call flushMemo(). Keyed by user so the global scope's isolation holds.
     *
     * @var array<string, mixed>
     */
    protected static array $memo = [];

    protected static function booted(): void
    {
        // Settings belong to the signed-in shop owner. Unauthenticated
        // contexts (guest pages, artisan commands) see only the userless
        // rows, so machine flags like double_entry_migrated keep working.
        // Every reader/writer goes through get()/set(), which is why the 68
        // call sites needed no edits when ownership landed.
        static::addGlobalScope('user', function (Builder $builder) {
            $column = $builder->getModel()->getQualifiedUserIdColumn();

            if (($userId = Auth::id()) !== null) {
                $builder->where($column, $userId);
            } else {
                $builder->whereNull($column);
            }
        });

        static::creating(function (self $setting) {
            $setting->user_id ??= Auth::id();
        });
    }

    protected function getQualifiedUserIdColumn(): string
    {
        return $this->qualifyColumn('user_id');
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $memoKey = (Auth::id() ?? 0).'|'.$key;

        if (! array_key_exists($memoKey, static::$memo)) {
            // Memoize the ROW value (null = missing), never the default:
            // a later call may pass a different default and must still
            // see it applied — the middleware reads ('pin_lock_enabled', '0')
            // while a test asserts the same key with no default.
            static::$memo[$memoKey] = static::where('key', $key)->first()?->value;
        }

        return static::$memo[$memoKey] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        unset(static::$memo[(Auth::id() ?? 0).'|'.$key]);
    }

    /** Drop the read memo — after bulk writers (backup restore) and between tests. */
    public static function flushMemo(): void
    {
        static::$memo = [];
    }
}
