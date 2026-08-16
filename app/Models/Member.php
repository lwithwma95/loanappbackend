<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $mem_code
 * @property string $mem_name
 * @property string $mem_guard_name
 * @property string $mem_mobile
 * @property int    $mem_status
 * @property string $shg_code
 * @property-read Shg|null $shg
 */
class Member extends Model
{
    use HasFactory;

    protected $table      = 'member';
    protected $primaryKey = 'mem_code';
    public    $keyType    = 'string';
    public    $incrementing = false;
    public    $timestamps = false;

    protected $fillable = ['mem_code', 'mem_name', 'mem_guard_name', 'mem_mobile', 'mem_status', 'shg_code'];

    /**
     * mem_code / shg_code are BIGINT in the MIS tables. PDO returns a BIGINT as a
     * PHP int when the value fits in PHP_INT_MAX and as a string when it doesn't,
     * so JSON responses would emit a number for short codes (and for every code on
     * 64-bit PHP) but a string for long ones. Clients echo the value back to
     * `required|string` endpoints, so pin it to string here.
     */
    protected $casts = [
        'mem_code' => 'string',
        'shg_code' => 'string',
    ];

    public function shg(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Shg::class, 'shg_code', 'shg_code');
    }
}
