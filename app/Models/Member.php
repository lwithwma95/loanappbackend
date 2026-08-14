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

    public function shg(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Shg::class, 'shg_code', 'shg_code');
    }
}
