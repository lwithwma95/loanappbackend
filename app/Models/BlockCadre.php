<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id
 * @property string      $cadre_code
 * @property string      $name
 * @property string      $type
 * @property string|null $mobile
 * @property string|null $email
 * @property int|null    $block_id
 * @property int|null    $district_id
 * @property int|null    $user_id
 * @property string      $role
 * @property string      $status
 * @property-read User|null    $user
 * @property-read Block|null   $block
 * @property-read District|null $district
 * @property-read Member|null  $member
 */
class BlockCadre extends Model
{
    use HasFactory;

    protected $fillable = [
        'cadre_code', 'name', 'type', 'mobile', 'email',
        'aadhaar_number', 'date_of_birth', 'consent_given_at',
        'block_id', 'district_id', 'user_id', 'role', 'status',
    ];

    protected $casts = [
        'date_of_birth'    => 'date:Y-m-d',
        'consent_given_at' => 'datetime',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function block(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Block::class, 'block_id', 'block_id');
    }

    public function district(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id', 'district_id');
    }

    public function member(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Member::class, 'cadre_code', 'mem_code');
    }
}
