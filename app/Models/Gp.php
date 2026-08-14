<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $gp_id
 * @property string $gp_name
 * @property int    $block_id
 * @property-read Block|null $block
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Village> $villages
 */
class Gp extends Model
{
    use HasFactory;

    protected $table      = 'gp';
    protected $primaryKey = 'gp_id';
    public    $keyType    = 'string';
    public    $incrementing = false;
    public    $timestamps = false;

    protected $fillable = ['gp_id', 'gp_name', 'block_id'];

    public function block(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Block::class, 'block_id', 'block_id');
    }

    public function villages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Village::class, 'gp_id', 'gp_id');
    }
}
