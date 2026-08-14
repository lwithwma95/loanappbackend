<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id
 * @property string      $bank_name
 * @property string|null $bank_full_name
 * @property string      $ifsc
 * @property string|null $branch_name
 * @property string|null $address
 * @property int|null    $district_id
 * @property-read District|null $district
 */
class Bank extends Model
{
    use HasFactory;

    protected $table = 'bank';

    protected $fillable = [
        'bank_name', 'bank_full_name', 'ifsc', 'branch_name', 'address', 'district_id',
        'city', 'state', 'micr', 'contact', 'imps', 'rtgs', 'neft', 'upi', 'source',
    ];

    protected function casts(): array
    {
        return [
            'imps' => 'boolean',
            'rtgs' => 'boolean',
            'neft' => 'boolean',
            'upi'  => 'boolean',
        ];
    }

    public function district(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id', 'district_id');
    }

    public function memberLoans(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MemberLoan::class, 'bank_id');
    }
}
