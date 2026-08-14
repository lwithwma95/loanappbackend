<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id
 * @property string      $mem_code
 * @property string|null $shg_code
 * @property string|null $mobile_no
 * @property string|null $member_name
 * @property string      $loan_id
 * @property float       $loan_amount
 * @property float|null  $bank_loan_amount
 * @property float       $total_due
 * @property float|null  $bank_os_balance
 * @property float       $already_paid
 * @property float|null  $bank_already_paid
 * @property string      $loan_sanction_date
 * @property string|null $payment_date
 * @property float       $interest_rate
 * @property int|null    $tenure_months
 * @property float|null  $emi_amount
 * @property string|null $ifsc
 * @property int|null    $bank_id
 * @property int|null    $monitoring_cadre
 * @property string      $status
 * @property string|null $remarks
 * @property-read Member|null      $member
 * @property-read Bank|null        $bank
 * @property-read BlockCadre|null  $cadre
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MemberLoanPayment> $payments
 */
class MemberLoan extends Model
{
    use HasFactory;

    protected $table = 'memberloan';

    protected $fillable = [
        'mem_code', 'shg_code', 'mobile_no', 'member_name', 'district_id', 'block_id',
        'loan_id', 'loan_amount', 'bank_loan_amount', 'prev_loan_amount',
        'total_due', 'bank_os_balance', 'already_paid', 'bank_already_paid',
        'loan_sanction_date', 'payment_date', 'interest_rate', 'tenure_months', 'emi_amount',
        'bank_acc_no', 'ifsc', 'bank_id', 'scheme_id', 'monitoring_cadre', 'status', 'bank_status', 'kyc_completed', 'remarks',
        'overdue_amount', 'npa_date', 'bank_reconcile_date',
    ];

    protected $casts = [
        'loan_amount'       => 'float',
        'bank_loan_amount'  => 'float',
        'prev_loan_amount'  => 'float',
        'total_due'         => 'float',
        'bank_os_balance'   => 'float',
        'already_paid'      => 'float',
        'bank_already_paid' => 'float',
        'interest_rate'     => 'float',
        'emi_amount'        => 'float',
        'loan_sanction_date'=> 'date',
        'payment_date'      => 'date',
        'kyc_completed'     => 'boolean',
        'overdue_amount'        => 'float',
        'npa_date'              => 'date',
        'bank_reconcile_date'   => 'datetime',
    ];

    public function member(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Member::class, 'mem_code', 'mem_code');
    }

    public function shg(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Shg::class, 'shg_code', 'shg_code');
    }

    public function bank(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function scheme(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(LoanScheme::class, 'scheme_id');
    }

    public function cadre(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BlockCadre::class, 'monitoring_cadre');
    }

    public function district(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id', 'district_id');
    }

    public function block(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Block::class, 'block_id', 'block_id');
    }

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MemberLoanPayment::class, 'loan_id');
    }

    public function computeNpaStatus(): array
    {
        if ($this->status === 'closed' || $this->total_due <= 0) {
            return ['computed_status' => 'closed', 'days_overdue' => 0];
        }

        if (!$this->payment_date) {
            return ['computed_status' => $this->status, 'days_overdue' => 0];
        }

        $daysOverdue = (int) max(0, now()->diffInDays(Carbon::parse($this->payment_date), false) * -1);

        $computed = match (true) {
            $daysOverdue >= 90 => 'npa',
            $daysOverdue >= 30 => 'overdue',
            $daysOverdue > 0   => 'due',
            default            => $this->status === 'active' ? 'active' : 'pending',
        };

        return ['computed_status' => $computed, 'days_overdue' => $daysOverdue];
    }
}
