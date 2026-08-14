<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $id
 * @property string $designation_name
 * @property string $role
 * @property string $salary
 * @property string $epf
 * @property string $deduction
 * @property string $total
 * @property string $net_salary
 * @property int    $employees_count
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Employee> $employees
 */
class Designation extends Model
{
    use HasFactory;
    protected $table = 'designations';
    protected $primaryKey = 'id';
    protected $fillable = [
        'designation_name',
        'role',
        'salary',
        'epf',
        'deduction',
        'total',
        'net_salary',
    ];

    protected function casts(): array
    {
        return [
            'salary'     => 'decimal:2',
            'epf'        => 'decimal:2',
            'deduction'  => 'decimal:2',
            'total'      => 'decimal:2',
            'net_salary' => 'decimal:2',
        ];
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
