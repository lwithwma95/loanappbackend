<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $vill_id
 * @property string $vill_name
 * @property int    $gp_id
 * @property-read Gp|null $gp
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Shg> $shgs
 */
class Village extends Model
{
    use HasFactory;

    protected $table      = 'village';
    protected $primaryKey = 'vill_sn';
    public    $keyType    = 'int';
    public    $incrementing = true;
    public    $timestamps = false;

    protected $fillable = [
        'vill_id',
        'vill_name',
        'gp_id',
    ];

    public function gp(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Gp::class, 'gp_id', 'gp_id');
    }

    public function shgs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Shg::class, 'vill_id_new', 'vill_id');
    }
}