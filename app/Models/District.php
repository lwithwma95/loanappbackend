<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $district_id
 * @property string $district_name
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Block> $blocks
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Gp>    $gps
 */
class District extends Model
{
    use HasFactory;

    protected $table      = 'district';
    protected $primaryKey = 'district_id';
    public    $timestamps = false;

    protected $fillable = ['district_id', 'district_name'];

    public function blocks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Block::class, 'district_id', 'district_id');
    }

    public function scopeOrdered(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        $col = 'district_name';
        return $query->orderBy($col);
    }
}
