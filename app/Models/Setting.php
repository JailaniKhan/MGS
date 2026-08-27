<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

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
        $setting = static::where('key', $key)->first();

        return $setting?->value ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
