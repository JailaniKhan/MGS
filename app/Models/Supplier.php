<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use BelongsToUser;

    protected $fillable = ['name', 'phone', 'address'];

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }
}
