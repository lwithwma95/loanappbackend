<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id
 * @property int         $designation_id
 * @property int|null    $user_id
 * @property int|null    $district_id
 * @property int|null    $block_id
 * @property string      $emp_name
 * @property string      $emp_id
 * @property string|null $email
 * @property string|null $emp_dob
 * @property string      $emp_doj
 * @property string|null $emp_qualification
 * @property string      $salary
 * @property string      $epf
 * @property string      $deduction
 * @property string      $gross_salary
 * @property string      $net_salary
 * @property string|null $bank_account
 * @property string|null $ifsc_code
 * @property string|null $phone
 * @property string      $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Designation|null $designation
 * @property-read District|null $district
 * @property-read Block|null $block
 */
class Employee extends Model
{
    use HasFactory;
    protected $table = 'employees';
    protected $primaryKey = 'id';

    protected $fillable = [
        'designation_id',
        'user_id',
        'district_id',
        'block_id',
        'emp_name',
        'emp_id',
        'email',
        'emp_dob',
        'emp_doj',
        'emp_qualification',
        'aadhaar_number',
        'salary',
        'mobile_allowance',
        'professional_dev',
        'epf',
        'deduction',
        'gross_salary',
        'net_salary',
        'bank_account',
        'ifsc_code',
        'phone',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'emp_dob'          => 'date:Y-m-d',
            'emp_doj'          => 'date:Y-m-d',
            'salary'           => 'decimal:2',
            'mobile_allowance' => 'decimal:2',
            'professional_dev' => 'decimal:2',
            'epf'              => 'decimal:2',
            'deduction'        => 'decimal:2',
            'gross_salary'     => 'decimal:2',
            'net_salary'       => 'decimal:2',
        ];
    }

    public function designation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function district(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id', 'district_id');
    }

    public function block(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Block::class, 'block_id', 'block_id');
    }

    public function scopeOrdered(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        $col = 'emp_name';
        return $query->orderBy($col);
    }

    public function empAttendances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmpAttendance::class);
    }

    public function empSalaries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmpSalary::class);
    }

    public static function generateEmpId(int $designationId): string
    {
        $designation = Designation::findOrFail($designationId);
        $roleRaw     = (string) $designation->role;
        $role        = strtoupper($roleRaw);
        $year        = date('Y');

        $count  = self::whereHas('designation', fn($q) => $q->where('role', $roleRaw))->count();
        $serial = str_pad($count + 1, 3, '0', STR_PAD_LEFT);

        return "ASRLM/HR/{$year}/{$role}/{$serial}";
    }
}
