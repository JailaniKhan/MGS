<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use BelongsToUser;

    protected $fillable = ['name', 'phone', 'address', 'last_contacted_at'];

    protected $casts = [
        'last_contacted_at' => 'datetime',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}