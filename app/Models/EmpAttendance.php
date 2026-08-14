<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpAttendance extends Model
{
    protected $table = 'emp_attendance';

    protected $fillable = [
        'employee_id',
        'month',
        'year',
        'total_working_days',
        'days_present',
        'casual_leave_taken',
        'medical_leave_taken',
        'maternity_leave_taken',
        'paternity_leave_taken',
        'remarks',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'month'                  => 'integer',
            'year'                   => 'integer',
            'total_working_days'     => 'integer',
            'days_present'           => 'integer',
            'casual_leave_taken'     => 'integer',
            'medical_leave_taken'    => 'integer',
            'maternity_leave_taken'  => 'integer',
            'paternity_leave_taken'  => 'integer',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function salary()
    {
        return $this->hasOne(EmpSalary::class, 'attendance_id');
    }
}
