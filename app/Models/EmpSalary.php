<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpSalary extends Model
{
    protected $table = 'emp_salary';

    protected $fillable = [
        'employee_id',
        'attendance_id',
        'month',
        'year',
        'total_working_days',
        'paid_days',
        'basic_salary',
        'mobile_allowance',
        'professional_dev',
        'gross_salary',
        'epf_deduction',
        'professional_tax',
        'net_salary',
        'actual_paid_salary',
        'status',
        'payment_date',
        'payment_mode',
        'transaction_id',
        'remarks',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'month'              => 'integer',
            'year'               => 'integer',
            'total_working_days' => 'integer',
            'paid_days'          => 'integer',
            'basic_salary'       => 'decimal:2',
            'mobile_allowance'   => 'decimal:2',
            'professional_dev'   => 'decimal:2',
            'gross_salary'       => 'decimal:2',
            'epf_deduction'      => 'decimal:2',
            'professional_tax'   => 'decimal:2',
            'net_salary'         => 'decimal:2',
            'actual_paid_salary' => 'decimal:2',
            'payment_date'       => 'date',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendance()
    {
        return $this->belongsTo(EmpAttendance::class, 'attendance_id');
    }
}
