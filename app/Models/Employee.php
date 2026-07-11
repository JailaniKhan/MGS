<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Employee extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'uuid',
        'name',
        'phone',
        'position',
        'monthly_salary',
        'currency',
        'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'monthly_salary' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Employee $employee) {
            if (empty($employee->uuid)) {
                $employee->uuid = (string) Str::uuid();
            }
        });
    }

    public function salaryPayments()
    {
        return $this->hasMany(SalaryPayment::class);
    }
}
