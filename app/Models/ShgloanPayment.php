<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id
 * @property int         $loan_id
 * @property float       $payment_amount
 * @property string      $date_payment
 * @property int|null    $updated_by
 * @property string|null $transaction_id
 * @property string      $mode_of_payment
 * @property string|null $remarks
 * @property-read Shgloan|null     $loan
 * @property-read BlockCadre|null  $collectedBy
 */
class ShgloanPayment extends Model
{
    use HasFactory;

    protected $table = 'shgloan_payment';

    protected $fillable = [
        'loan_id', 'payment_amount', 'date_payment', 'updated_by',
        'transaction_id', 'mode_of_payment', 'remarks',
    ];

    protected $casts = [
        'payment_amount' => 'float',
        'date_payment'   => 'date',
    ];

    public function loan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Shgloan::class, 'loan_id');
    }

    public function collectedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BlockCadre::class, 'updated_by');
    }
}
