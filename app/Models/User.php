<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'google_id', 'avatar'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Find a user by their Google ID, or create/attach one from the Google user payload.
     */
    public static function findOrCreateFromGoogle(array $googleUser): self
    {
        $user = static::where('google_id', $googleUser['id'])->first();

        if ($user) {
            return $user;
        }

        $user = static::where('email', $googleUser['email'])->first();

        if ($user) {
            $user->update([
                'google_id' => $googleUser['id'],
                'avatar' => $googleUser['avatar'] ?? null,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);

            return $user;
        }

        return static::create([
            'name' => $googleUser['name'],
            'email' => $googleUser['email'],
            'google_id' => $googleUser['id'],
            'avatar' => $googleUser['avatar'] ?? null,
            'email_verified_at' => now(),
            'password' => bcrypt(Str::random(32)),
        ]);
    }
}
