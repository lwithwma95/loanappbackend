<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeTransferRequest extends Model
{
    protected $table = 'employee_transfer_requests';

    protected $fillable = [
        'employee_id',
        'from_district_id', 'from_block_id',
        'to_district_id',   'to_block_id',
        'reason',
        'status',
        'reviewed_by', 'reviewed_at', 'reviewer_note',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function fromDistrict(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(District::class, 'from_district_id', 'district_id');
    }

    public function fromBlock(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Block::class, 'from_block_id', 'block_id');
    }

    public function toDistrict(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(District::class, 'to_district_id', 'district_id');
    }

    public function toBlock(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Block::class, 'to_block_id', 'block_id');
    }

    public function reviewer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
