<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $shg_code
 * @property string $shg_name
 * @property int    $vill_id_new
 * @property-read Village|null $village
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Member> $members
 */
class Shg extends Model
{
    use HasFactory;

    protected $table      = 'shg';
    protected $primaryKey = 'shg_code';
    public    $keyType    = 'string';
    public    $incrementing = false;
    public    $timestamps = false;

    protected $fillable = ['shg_code', 'shg_name', 'vill_id_new','shg_status'];

    public function village(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Village::class, 'vill_id_new', 'vill_id');
    }

    public function members(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Member::class, 'shg_code', 'shg_code');
    }
}
