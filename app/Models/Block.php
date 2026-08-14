<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $block_id
 * @property string $block_name
 * @property int    $district_id
 * @property-read District|null $district
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Gp> $gps
 */
class Block extends Model
{
    use HasFactory;

    protected $table      = 'block';
    protected $primaryKey = 'block_id';
    public    $timestamps = false;

    protected $fillable = ['block_id', 'block_name', 'district_id'];

    public function district(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id', 'district_id');
    }

    public function gps(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Gp::class, 'block_id', 'block_id');
    }

    public function scopeOrdered(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orderBy('block_name');
    }
}
