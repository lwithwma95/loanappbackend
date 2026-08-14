<?php

namespace App\Http\Controllers\Financial;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Block;
use App\Models\BlockCadre;
use App\Models\Member;
use App\Models\MemberLoan;
use App\Models\Shg;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberLoanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MemberLoan::with(['member.shg.village.gp.block', 'cadre', 'bank', 'scheme:id,bank_name,scheme_name', 'district:district_id,district_name', 'block:block_id,block_name']);
        $user  = $request->user();

        // Loans reconciled by State with no block assigned yet belong in the
        // "Loan Mapping" tab, not here — showing them mixed into the individual
        // loan list before they're mapped is confusing.
        $query->whereNotNull('block_id');

        // Geographic scope — use stored shg_code to avoid deep nested whereHas chains.
        // Also match the loan's own district_id/block_id directly: bulk-uploaded loans
        // may carry a blank/unresolvable shg_code, so the shg_code join alone would hide them.
        if ($user->role === 'district' && $user->district_id) {
            $districtId = $user->district_id;
            $query->where(fn($q) => $q
                ->whereIn('shg_code', $this->shgCodesForDistrict($districtId))
                ->orWhere('district_id', $districtId));
        } elseif (in_array($user->role, ['block', 'block_admin', 'block_cadre']) && $user->block_id) {
            $blockId = $user->block_id;
            $query->where(fn($q) => $q
                ->whereIn('shg_code', $this->shgCodesForBlock($blockId))
                ->orWhere('block_id', $blockId));
        }

        if ($request->filled('district_id') && $user->role === 'state') {
            $districtId = $request->integer('district_id');
            $query->where(fn($q) => $q
                ->whereIn('shg_code', $this->shgCodesForDistrict($districtId))
                ->orWhere('district_id', $districtId));
        }
        if ($request->filled('block_id') && in_array($user->role, ['state', 'district'])) {
            $blockId = $request->integer('block_id');
            $query->where(fn($q) => $q
                ->whereIn('shg_code', $this->shgCodesForBlock($blockId))
                ->orWhere('block_id', $blockId));
        }

        // Per-member lookup (used by the loan pre-check before creating) — bypasses cadre filter
        if ($request->filled('mem_code')) {
            $query->where('mem_code', $request->string('mem_code'));
        } elseif ($user->role === 'block_cadre') {
            $ownCadreId = BlockCadre::where('user_id', $user->id)->value('id');
            if ($ownCadreId) {
                $query->where('monitoring_cadre', $ownCadreId);
            }
        }

        if ($request->filled('status')) {
            $statusFilter = (string) $request->string('status');

            if ($statusFilter === 'due') {
                // Any past-due loan that isn't NPA or closed yet
                $query->where('status', '!=', 'closed')
                      ->where('status', '!=', 'npa')
                      ->whereNotNull('payment_date')
                      ->whereDate('payment_date', '<', now());
            } elseif ($statusFilter === 'overdue') {
                $query->where('status', '!=', 'closed')
                      ->whereNotNull('payment_date')
                      ->whereDate('payment_date', '<=', now()->subDays(30))
                      ->whereDate('payment_date', '>', now()->subDays(90));
            } elseif ($statusFilter === 'npa') {
                $query->where(function ($q) {
                    $q->where('status', 'npa')
                      ->orWhere(function ($q2) {
                          $q2->where('status', '!=', 'closed')
                             ->whereNotNull('payment_date')
                             ->whereDate('payment_date', '<=', now()->subDays(90));
                      });
                });
            } elseif ($statusFilter === 'active') {
                $query->where('status', 'active')
                      ->where(function ($q) {
                          $q->whereNull('payment_date')
                            ->orWhereDate('payment_date', '>=', now());
                      });
            } elseif ($statusFilter === 'problems') {
                // All loans that are due, overdue, or NPA — used by "Due / Overdue" cadre badge
                $query->where('status', '!=', 'closed')
                      ->where(function ($q) {
                          $q->where('status', 'npa')
                            ->orWhere(function ($q2) {
                                $q2->whereNotNull('payment_date')
                                   ->whereDate('payment_date', '<', now());
                            });
                      });
            } else {
                $query->where('status', $statusFilter);
            }
        }

        if ($request->filled('search')) {
            $s = $request->string('search');
            $query->where(function ($q) use ($s) {
                $q->where('mem_code', 'like', "%{$s}%")
                  ->orWhere('loan_id', 'like', "%{$s}%")
                  ->orWhere('member_name', 'like', "%{$s}%")
                  ->orWhere('mobile_no', 'like', "%{$s}%")
                  ->orWhereHas('member', fn($q2) => $q2
                      ->where('mem_name', 'like', "%{$s}%")
                      ->orWhere('mem_mobile', 'like', "%{$s}%"));
            });
        }

        if ($request->input('sort') === 'due_date') {
            $query->orderByRaw('payment_date IS NULL, payment_date ASC');
        } else {
            $query->orderByDesc('id');
        }

        $paginator = $query->cursorPaginate(15);
        $paginator->through(fn($r) => $this->formatRecord($r));

        return response()->json($paginator);
    }

    // Returns a subquery of shg_codes belonging to a district (flat JOIN, no nested EXISTS)
    private function shgCodesForDistrict(int $districtId): \Illuminate\Database\Query\Builder
    {
        return Shg::select('shg.shg_code')
            ->join('village', 'shg.vill_id_new', '=', 'village.vill_id')
            ->join('gp', 'village.gp_id', '=', 'gp.gp_id')
            ->join('block', 'gp.block_id', '=', 'block.block_id')
            ->where('block.district_id', $districtId)
            ->toBase();
    }

    // Returns a subquery of shg_codes belonging to a block (flat JOIN, no nested EXISTS)
    private function shgCodesForBlock(int $blockId): \Illuminate\Database\Query\Builder
    {
        return Shg::select('shg.shg_code')
            ->join('village', 'shg.vill_id_new', '=', 'village.vill_id')
            ->join('gp', 'village.gp_id', '=', 'gp.gp_id')
            ->where('gp.block_id', $blockId)
            ->toBase();
    }

   

    public function store(Request $request): JsonResponse
    {
        if (!in_array($request->user()->role, ['block', 'block_admin', 'block_cadre'])) {
            return response()->json(['message' => 'Only Block staff can create loans.'], 403);
        }

        $data = $request->validate([
            'loan_id'          => 'required|string|max:50|unique:memberloan,loan_id',
            'mem_code'         => 'required|string|exists:member,mem_code',
            'member_name'      => 'nullable|string|max:200',
            'mobile_no'        => 'required|string|max:15',
            'loan_amount'      => 'required|numeric|min:1',
            'bank_acc_no'      => 'required|string|max:30',
            'loan_sanction_date' => 'required|date',
            'payment_date'     => 'nullable|date',
            'interest_rate'    => 'nullable|numeric|min:0',
            'tenure_months'    => 'nullable|integer|min:1',
            'emi_amount'       => 'nullable|numeric|min:0',
            'ifsc'             => 'nullable|string|max:11',
            'bank_id'          => 'nullable|integer|exists:bank,id',
            'scheme_id'        => 'nullable|integer|exists:loan_schemes,id',
            'monitoring_cadre' => 'nullable|integer|exists:block_cadres,id',
            'already_paid'     => 'nullable|numeric|min:0',
            'remarks'          => 'nullable|string',
        ]);

        // Block creating a second loan if the member still has an open one
        $hasOpenLoan = MemberLoan::where('mem_code', $data['mem_code'])
            ->where('status', '!=', 'closed')
            ->exists();

        if ($hasOpenLoan) {
            return response()->json([
                'message' => 'Previous loan is not Closed. Please close the existing loan before creating a new one.',
            ], 422);
        }

        $member = Member::find($data['mem_code']);
        $data['shg_code'] = $member?->shg_code;

        $user = $request->user();
        $data['district_id'] = $user->district_id ?? null;
        $data['block_id']    = $user->block_id ?? null;

        // Always derive monitoring_cadre from the auth user's own cadre record for block_cadre role
        if ($user->role === 'block_cadre') {
            $data['monitoring_cadre'] = BlockCadre::where('user_id', $user->id)->value('id');
        }

        $rate     = (float) ($data['interest_rate'] ?? 0);
        $tenure   = (int) ($data['tenure_months'] ?? 0);
        $interest = $tenure > 0 ? round($data['loan_amount'] * ($rate / 100) * ($tenure / 12), 2) : 0;
        $data['total_due']    = round($data['loan_amount'] + $interest, 2);
        $data['already_paid'] = (float) ($data['already_paid'] ?? 0);
        $data['status'] = 'active';

        if (!empty($data['bank_id']) && empty($data['ifsc'])) {
            $data['ifsc'] = Bank::find($data['bank_id'])?->ifsc;
        }

        $loan = MemberLoan::create($data);
        $loan->load(['member.shg.village.gp.block', 'cadre', 'bank', 'scheme:id,bank_name,scheme_name', 'district:district_id,district_name', 'block:block_id,block_name']);

        return response()->json($this->formatRecord($loan), 201);
    }

    public function show(MemberLoan $memberloan): JsonResponse
    {
        $memberloan->load(['member.shg.village.gp.block.district', 'cadre', 'bank', 'scheme:id,bank_name,scheme_name', 'district:district_id,district_name', 'block:block_id,block_name']);

        $payments = $memberloan->payments()->with('collectedBy')->orderBy('date_payment', 'desc')->get();

        return response()->json(array_merge(
            $this->formatRecord($memberloan),
            ['payments_history' => $payments]
        ));
    }

    public function update(Request $request, MemberLoan $memberloan): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->role, ['block', 'block_admin', 'block_cadre', 'state'])) {
            return response()->json(['message' => 'Insufficient permissions.'], 403);
        }

        $data = $request->validate([
            'loan_sanction_date' => 'sometimes|date',
            'payment_date' => 'nullable|date',
            'interest_rate' => 'nullable|numeric|min:0',
            'tenure_months' => 'nullable|integer|min:1',
            'emi_amount' => 'nullable|numeric|min:0',
            'mem_code'    => 'sometimes|nullable|string|max:50',
            'shg_code'    => 'sometimes|nullable|string|max:50',
            'mobile_no'   => 'sometimes|nullable|string|max:15',
            'member_name' => 'sometimes|nullable|string|max:255',
            'bank_acc_no' => 'sometimes|nullable|string|max:30',
            'ifsc'        => 'nullable|string|max:11',
            'bank_id'     => 'nullable|integer|exists:bank,id',
            'scheme_id'   => 'nullable|integer|exists:loan_schemes,id',
            'block_id'    => 'nullable|integer|exists:block,block_id',
            'monitoring_cadre' => 'nullable|integer|exists:block_cadres,id',
            'status' => 'sometimes|in:pending,active,closed,npa',
            'bank_status' => 'sometimes|nullable|in:pending,active,closed,npa',
            // Explicitly marks the loan as reviewed, even if mem_code/shg_code still can't
            // be matched to a real record — used by the "Complete KYC" flow.
            'kyc_completed' => 'sometimes|boolean',
            'remarks' => 'nullable|string',
        ]);

        // Changing the block is restricted to a block within the requesting
        // admin's own district — same guard as assignBlock() — regardless of
        // which flow (Loan Mapping or Complete KYC) triggered the change.
        if (array_key_exists('block_id', $data) && $data['block_id']) {
            $block = Block::find($data['block_id']);
            if (!$block || !$user->district_id || (int) $block->district_id !== (int) $user->district_id) {
                return response()->json(['message' => 'Selected block does not belong to your district.'], 422);
            }
        }

        // When mem_code is supplied, also sync shg_code from the member record.
        if (array_key_exists('mem_code', $data) && $data['mem_code']) {
            $member = \App\Models\Member::find($data['mem_code']);
            if ($member) {
                $data['shg_code'] = $member->shg_code;
            }
        }

        // Recalculate total_due and emi_amount whenever interest rate or tenure change (Reconcile action).
        if (array_key_exists('interest_rate', $data) || array_key_exists('tenure_months', $data)) {
            $amount  = (float) $memberloan->loan_amount;
            $paid    = (float) $memberloan->already_paid;
            $rate    = (float) (array_key_exists('interest_rate', $data) ? ($data['interest_rate'] ?? 0) : ($memberloan->interest_rate ?? 0));
            $tenure  = (int) (array_key_exists('tenure_months', $data) ? ($data['tenure_months'] ?? 0) : ($memberloan->tenure_months ?? 0));
            $interest = $tenure > 0 ? round($amount * ($rate / 100) * ($tenure / 12), 2) : 0;
            $data['total_due'] = round(max(0, ($amount + $interest) - $paid), 2);
            if ($tenure > 0 && !array_key_exists('emi_amount', $data)) {
                $data['emi_amount'] = round($amount / $tenure, 2);
            }
            // Clear the reconcile flag — loan is now up to date
            $data['prev_loan_amount'] = null;
        }

        $memberloan->update($data);
        $memberloan->load(['member.shg.village.gp.block', 'cadre', 'bank', 'scheme:id,bank_name,scheme_name', 'district:district_id,district_name', 'block:block_id,block_name']);

        return response()->json($this->formatRecord($memberloan));
    }

    public function destroy(MemberLoan $memberloan): JsonResponse
    {
        if (!in_array(request()->user()->role, ['block', 'block_admin'])) {
            return response()->json(['message' => 'Insufficient permissions.'], 403);
        }

        $memberloan->update(['status' => 'closed']);
        return response()->json(['message' => 'Loan closed.']);
    }

    private function formatRecord(MemberLoan $r): array
    {
        $npa = $r->computeNpaStatus();
        $paidPct = $r->total_due > 0 ? round(($r->already_paid / ($r->loan_amount ?: 1)) * 100, 1) : 100;

        // Geo chain comes from the member's own SHG (member.shg_code → shg → village → gp → block).
        // member == null: no member match yet (needs KYC).
        // member != null, shg == null: member exists but SHG is not in our system (Old NRLM SHG).
        // member != null, shg != null: full geo info available.
        $member = $r->relationLoaded('member') ? $r->member : null;
        $memberShg = ($member && $member->relationLoaded('shg')) ? $member->shg : null;
        $shgGeo = $memberShg ? [
            'shg_name'    => $memberShg->shg_name,
            'village_name' => $memberShg->village?->vill_name,
            'gp_name'     => $memberShg->village?->gp?->gp_name,
            'block_name'  => $memberShg->village?->gp?->block?->block_name,
        ] : null;

        // Computed overdue: months past the next-due date × EMI, capped at total_due.
        // payment_date is the next EMI due date — if it's in the past the loan is overdue.
        $computedOverdue = null;
        if ($r->status !== 'closed' && $r->total_due > 0 && $r->payment_date && $r->emi_amount > 0) {
            $dueMonthStart  = Carbon::parse($r->payment_date)->startOfMonth();
            $thisMonthStart = Carbon::now()->startOfMonth();

            if ($thisMonthStart->gt($dueMonthStart)) {
                $monthsOverdue   = (int) $dueMonthStart->diffInMonths($thisMonthStart);
                $computedOverdue = round(min((float) $r->total_due, $monthsOverdue * (float) $r->emi_amount), 2);
            } else {
                $computedOverdue = 0.0;
            }
        }

        // Bank vs our-side comparison — shown whenever a bank reconciliation upload has touched
        // this loan, even if no financial figures were reported (reconcile_date alone is enough).
        $ourOsBalance = (float) $r->total_due;
        $hasBankData  = $r->bank_loan_amount   !== null
                     || $r->bank_already_paid  !== null
                     || $r->bank_os_balance    !== null
                     || $r->bank_reconcile_date !== null;

        $bankComparison = $hasBankData ? [
            'loan_amount' => [
                'ours' => (float) $r->loan_amount,
                'bank' => $r->bank_loan_amount,
                'diff' => $r->bank_loan_amount !== null
                    ? round($r->bank_loan_amount - $r->loan_amount, 2) : null,
            ],
            'already_paid' => [
                'ours' => (float) $r->already_paid,
                'bank' => $r->bank_already_paid,
                'diff' => $r->bank_already_paid !== null
                    ? round($r->bank_already_paid - $r->already_paid, 2) : null,
            ],
            'os_balance' => [
                'ours' => $ourOsBalance,
                'bank' => $r->bank_os_balance,
                'diff' => $r->bank_os_balance !== null
                    ? round($r->bank_os_balance - $ourOsBalance, 2) : null,
            ],
            'has_discrepancy' =>
                ($r->bank_loan_amount  !== null && abs($r->bank_loan_amount  - $r->loan_amount)  > 0.01) ||
                ($r->bank_already_paid !== null && abs($r->bank_already_paid - $r->already_paid) > 0.01) ||
                ($r->bank_os_balance   !== null && abs($r->bank_os_balance   - $ourOsBalance)    > 0.01),
            'reported_date' => $r->bank_reconcile_date?->toIso8601String(),
        ] : null;

        return array_merge($r->toArray(), $npa, [
            'paid_percentage' => $paidPct,
            'status_mismatch' => $r->bank_status !== null && $r->bank_status !== $r->status,
            'needs_kyc' => !$r->kyc_completed
                && ($r->relationLoaded('member') ? $member === null : !Member::whereKey($r->mem_code)->exists()),
            'is_old_nrlm_shg' => $shgGeo === null && ($r->shg_code !== null || ($member !== null && $member->shg_code !== null)),
            'resolved_member_name' => $member?->mem_name ?? $r->member_name,
            'resolved_mobile' => $member?->mem_mobile ?? $r->mobile_no,
            'shg_geo' => $shgGeo,
            'computed_overdue_amount' => $computedOverdue,
            'bank_comparison' => $bankComparison,
        ]);
    }

    /**
     * POST /financial/member-loans/bulk
     * District/Block admins upload a bank-reported reconciliation sheet.
     * Existing loans (matched by loan_id) get their bank_status updated;
     * loans the bank reports that we don't have yet are created — even when
     * mem_code/shg_code don't exist in our member/shg tables (no FK enforced).
     */
    public function bulkUpload(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->role, ['district', 'block', 'block_admin'])) {
            return response()->json(['message' => 'Only District and Block admins can bulk-upload loans.'], 403);
        }

        // Normalize bank_status casing (CSV uploads commonly use "NPA", "Active", etc.)
        $loans = collect($request->input('loans', []))->map(function ($row) {
            if (isset($row['bank_status']) && is_string($row['bank_status'])) {
                $row['bank_status'] = strtolower(trim($row['bank_status'])) ?: null;
            }
            return $row;
        })->all();
        $request->merge(['loans' => $loans]);

        $payload = $request->validate([
            'block_id'               => $user->role === 'district' ? 'required|integer|exists:block,block_id' : 'nullable|integer',
            'loans'                  => 'required|array|min:1|max:1000',
            'loans.*.loan_id'        => 'required|string|max:50',
            'loans.*.mem_code'       => 'required|string|max:50',
            'loans.*.shg_code'       => 'nullable|string|max:50',
            'loans.*.mobile_no'      => 'nullable|string|max:15',
            'loans.*.member_name'    => 'nullable|string|max:255',
            'loans.*.loan_amount'    => 'required|numeric|min:0',
            'loans.*.bank_acc_no'    => 'nullable|string|max:30',
            'loans.*.ifsc'           => 'nullable|string|max:11',
            'loans.*.bank_status'    => 'nullable|in:pending,active,closed,npa',
        ]);

        $districtId = $user->district_id ?? null;
        $blockId    = $user->role === 'district' ? $payload['block_id'] : ($user->block_id ?? null);

        $inserted = 0;
        $updated  = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($payload['loans'] as $i => $row) {
            $loanId = trim($row['loan_id']);
            if ($loanId === '') {
                $skipped++;
                $errors[] = 'Row ' . ($i + 2) . ': missing loan_id.';
                continue;
            }

            $existing = MemberLoan::where('loan_id', $loanId)->first();

            if ($existing) {
                $newAmount  = (float) $row['loan_amount'];
                $updateData = [
                    'prev_loan_amount' => (float) $existing->loan_amount,  // snapshot before overwrite
                    'loan_amount' => $newAmount,
                    'total_due'   => round(max(0, $newAmount - (float) $existing->already_paid), 2),
                    'bank_status' => $row['bank_status'] ?? $existing->bank_status,
                    'district_id' => $existing->district_id ?? $districtId,
                    'block_id'    => $existing->block_id ?? $blockId,
                ];
                if (isset($row['mobile_no']) && $row['mobile_no'] !== '') {
                    $updateData['mobile_no'] = trim($row['mobile_no']);
                }
                if (isset($row['member_name']) && $row['member_name'] !== '') {
                    $updateData['member_name'] = trim($row['member_name']);
                }
                if (isset($row['bank_acc_no']) && $row['bank_acc_no'] !== '') {
                    $updateData['bank_acc_no'] = trim($row['bank_acc_no']);
                }
                if (isset($row['ifsc']) && $row['ifsc'] !== '') {
                    $updateData['ifsc'] = trim($row['ifsc']);
                }
                $existing->update($updateData);
                $updated++;
                continue;
            }

            MemberLoan::create([
                'loan_id'            => $loanId,
                'mem_code'           => trim($row['mem_code']),
                'shg_code'           => $row['shg_code'] ?? null,
                'mobile_no'          => isset($row['mobile_no']) && $row['mobile_no'] !== '' ? trim($row['mobile_no']) : null,
                'member_name'        => isset($row['member_name']) && $row['member_name'] !== '' ? trim($row['member_name']) : null,
                'bank_acc_no'        => isset($row['bank_acc_no']) && $row['bank_acc_no'] !== '' ? trim($row['bank_acc_no']) : null,
                'ifsc'               => isset($row['ifsc']) && $row['ifsc'] !== '' ? trim($row['ifsc']) : null,
                'district_id'        => $districtId,
                'block_id'           => $blockId,
                'loan_amount'        => $row['loan_amount'],
                'total_due'          => $row['loan_amount'],
                'already_paid'       => 0,
                'loan_sanction_date' => now()->toDateString(),
                'status'             => 'pending',
                'bank_status'        => $row['bank_status'] ?? null,
            ]);
            $inserted++;
        }

        return response()->json(compact('inserted', 'updated', 'skipped', 'errors'));
    }

    /**
     * POST /financial/member-loans/bank-reconcile
     * State Admin only. Imports a bank-reported loan statement with the new format:
     * Bank_Name, Branch, Scheme_Name, Loan_Disbursed_Date, Loan_Acc_No, Beneficiary_Name,
     * OS_Balance, Overdue_Amount, Status (NPA/Overdue), NPA_Date.
     * Existing loans are updated; unmatched loans are created with mem_code=null for later KYC.
     */
    public function bankReconcile(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'state') {
            return response()->json(['message' => 'Only State Admin can perform bank reconciliation uploads.'], 403);
        }

        // Normalize bank_status casing (bank CSVs use NPA, Overdue, Active etc.)
        $loans = collect($request->input('loans', []))->map(function ($row) {
            if (isset($row['bank_status']) && is_string($row['bank_status'])) {
                $row['bank_status'] = strtolower(trim($row['bank_status'])) ?: null;
            }
            return $row;
        })->all();
        $request->merge(['loans' => $loans]);

        $payload = $request->validate([
            'district_id'               => 'required|integer|exists:district,district_id',
            'block_id'                  => 'nullable|integer|exists:block,block_id',
            'loans'                     => 'required|array|min:1|max:2000',
            'loans.*.loan_id'           => 'required|string|max:50',
            'loans.*.member_name'       => 'nullable|string|max:255',
            'loans.*.mobile_no'         => 'nullable|string|max:15',
            'loans.*.bank_acc_no'       => 'nullable|string|max:30',
            'loans.*.bank_id'           => 'nullable|integer|exists:bank,id',
            'loans.*.scheme_id'         => 'nullable|integer|exists:loan_schemes,id',
            'loans.*.loan_sanction_date'=> 'nullable|date',
            'loans.*.ifsc'              => 'nullable|string|max:11',
            'loans.*.loan_amount'       => 'nullable|numeric|min:0',
            'loans.*.os_balance'        => 'nullable|numeric|min:0',
            'loans.*.already_paid'      => 'nullable|numeric|min:0',
            'loans.*.overdue_amount'    => 'nullable|numeric|min:0',
            'loans.*.bank_status'       => 'nullable|in:active,overdue,npa,closed',
            'loans.*.npa_date'          => 'nullable|date',
        ]);

        $districtId = $payload['district_id'];
        $blockId    = $payload['block_id'] ?? null;

        $inserted = 0;
        $updated  = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($payload['loans'] as $i => $row) {
            $rawLoanId = trim($row['loan_id']);

            // Strip Excel-added trailing .0 / .00 / .000 (e.g. account numbers formatted as floats)
            $loanId = preg_replace('/\.0+$/', '', $rawLoanId);

            if ($loanId === '') {
                $skipped++;
                $errors[] = 'Row ' . ($i + 2) . ': missing Loan Number.';
                continue;
            }

            // Reject loan IDs that still contain a decimal after normalization (e.g. "ML001.5")
            if (str_contains($loanId, '.')) {
                $skipped++;
                $errors[] = 'Row ' . ($i + 2) . ': Loan Number "' . $rawLoanId . '" contains a decimal point — remove it (Excel may have auto-formatted the account number as a float).';
                continue;
            }

            $mobileNo   = isset($row['mobile_no'])   && trim($row['mobile_no'])   !== '' ? trim($row['mobile_no'])   : null;
            $bankAccNo  = isset($row['bank_acc_no']) && trim($row['bank_acc_no']) !== '' ? trim($row['bank_acc_no']) : null;
            $memberName = isset($row['member_name']) && trim($row['member_name']) !== '' ? trim($row['member_name']) : null;

            $osBalance      = isset($row['os_balance'])   && $row['os_balance']   !== null ? (float) $row['os_balance']   : null;
            $loanAmtCsv     = isset($row['loan_amount'])  && $row['loan_amount']  !== null ? (float) $row['loan_amount']  : null;
            $alreadyPaidCsv = isset($row['already_paid']) && $row['already_paid'] !== null ? (float) $row['already_paid'] : null;

            $existing = MemberLoan::where('loan_id', $loanId)->first();

            // When loan_id is not found, use pairwise 2-of-3 matching to detect a mistaken field.
            // Rule: if any TWO of the three identifiers match an existing loan together, the third
            // field is considered wrong/mistaken — block the row.
            //
            //   Mobile mistaken  → validate Loan No + Bank A/C No together → but Loan No is new,
            //                       so falls back to Bank A/C No alone
            //   Loan No mistaken → validate Mobile No + Bank A/C No together (pair check)
            //   Bank A/C mistaken→ validate Loan No + Mobile No together → but Loan No is new,
            //                       so falls back to Mobile No alone
            //
            // Effectively three steps, in priority order:
            //   1. (mobile_no + bank_acc_no) pair       → strongest signal, loan_id was wrong
            //   2. bank_acc_no alone                    → mobile may also be wrong/absent
            //   3. mobile_no alone                      → bank_acc may also be wrong/absent
            if (!$existing) {
                $conflict  = null;
                $matchedOn = [];

                if ($mobileNo && $bankAccNo) {
                    $hit = MemberLoan::where('mobile_no', $mobileNo)
                                     ->where('bank_acc_no', $bankAccNo)
                                     ->first();
                    if ($hit) { $conflict = $hit; $matchedOn = ['Mobile No', 'Bank A/C No']; }
                }

                if (!$conflict && $bankAccNo) {
                    $hit = MemberLoan::where('bank_acc_no', $bankAccNo)->first();
                    if ($hit) { $conflict = $hit; $matchedOn = ['Bank A/C No']; }
                }

                if (!$conflict && $mobileNo) {
                    $hit = MemberLoan::where('mobile_no', $mobileNo)->first();
                    if ($hit) { $conflict = $hit; $matchedOn = ['Mobile No']; }
                }

                if ($conflict) {
                    $skipped++;
                    $errors[] = 'Row ' . ($i + 2) . ': Discrepancy detected — '
                        . implode(' and ', $matchedOn)
                        . ' in CSV row matches existing loan "' . $conflict->loan_id . '".'
                        . ' Loan "' . $loanId . '" was not found. Verify the Loan Number, Mobile Number,'
                        . ' and Bank Account Number — all three must be different for a new entry.';
                    continue;
                }
            }

            if ($existing) {
                // When loan_id is found, validate that mobile_no and bank_acc_no in the CSV
                // match what is stored — a different value means a field was changed by mistake.
                // Only flag when BOTH sides are non-null (skip if DB field was never stored).
                $mobileConflict  = $mobileNo  && $existing->mobile_no
                                   && $existing->mobile_no  !== $mobileNo;
                $bankAccConflict = $bankAccNo && $existing->bank_acc_no
                                   && $existing->bank_acc_no !== $bankAccNo;

                if ($mobileConflict || $bankAccConflict) {
                    $conflicted = array_values(array_filter([
                        $mobileConflict  ? 'Mobile No (CSV: '     . $mobileNo  . ', stored: ' . $existing->mobile_no  . ')' : null,
                        $bankAccConflict ? 'Bank A/C No (CSV: '   . $bankAccNo . ', stored: ' . $existing->bank_acc_no . ')' : null,
                    ]));
                    $skipped++;
                    $errors[] = 'Row ' . ($i + 2) . ': Discrepancy detected in loan "' . $loanId . '" — '
                        . implode(' and ', $conflicted)
                        . '. Rectify before upload.';
                    continue;
                }

                $updateData = [
                    'bank_status'         => $row['bank_status'] ?? $existing->bank_status,
                    'bank_reconcile_date' => now(),
                ];
                if (isset($row['overdue_amount']) && $row['overdue_amount'] !== null) {
                    $updateData['overdue_amount'] = (float) $row['overdue_amount'];
                }
                if (!empty($row['npa_date'])) {
                    $updateData['npa_date'] = $row['npa_date'];
                }
                // Store bank-reported financials in bank_* columns — do NOT overwrite our records.
                if ($loanAmtCsv !== null)     { $updateData['bank_loan_amount']  = $loanAmtCsv; }
                if ($alreadyPaidCsv !== null) { $updateData['bank_already_paid'] = $alreadyPaidCsv; }
                if ($osBalance !== null)      { $updateData['bank_os_balance']   = $osBalance; }
                if (!empty($row['bank_id']))   { $updateData['bank_id']   = $row['bank_id']; }
                if (!empty($row['scheme_id'])) { $updateData['scheme_id'] = $row['scheme_id']; }
                if (isset($row['ifsc']) && $row['ifsc'] !== '') {
                    $updateData['ifsc'] = trim($row['ifsc']);
                }
                if ($memberName !== null) { $updateData['member_name'] = $memberName; }
                if ($mobileNo   !== null) { $updateData['mobile_no']   = $mobileNo; }
                if ($bankAccNo  !== null) { $updateData['bank_acc_no'] = $bankAccNo; }
                $existing->update($updateData);
                $updated++;
                continue;
            }

            // New loan from bank — no prior record, so our side == bank side initially.
            // total_due = bank OS Balance directly (it already represents the outstanding amount).
            $newLoanAmount  = $loanAmtCsv ?? $osBalance ?? 0;
            $newAlreadyPaid = $alreadyPaidCsv ?? 0;
            $newTotalDue    = $osBalance !== null ? (float) $osBalance : $newLoanAmount;
            MemberLoan::create([
                'loan_id'            => $loanId,
                'mem_code'           => null,
                'member_name'        => $memberName,
                'mobile_no'          => $mobileNo,
                'bank_acc_no'        => $bankAccNo,
                'district_id'        => $districtId,
                'block_id'           => $blockId,
                'bank_id'            => $row['bank_id'] ?? null,
                'scheme_id'          => $row['scheme_id'] ?? null,
                'ifsc'               => isset($row['ifsc']) && $row['ifsc'] !== '' ? trim($row['ifsc']) : null,
                'loan_sanction_date' => !empty($row['loan_sanction_date']) ? $row['loan_sanction_date'] : now()->toDateString(),
                'loan_amount'        => $newLoanAmount,
                'bank_loan_amount'   => $loanAmtCsv,
                'total_due'          => $newTotalDue,
                'bank_os_balance'    => $osBalance,
                'already_paid'       => $newAlreadyPaid,
                'bank_already_paid'  => $alreadyPaidCsv,
                'overdue_amount'     => isset($row['overdue_amount']) && $row['overdue_amount'] !== null ? (float) $row['overdue_amount'] : null,
                'bank_status'        => $row['bank_status'] ?? null,
                'npa_date'           => !empty($row['npa_date']) ? $row['npa_date'] : null,
                'bank_reconcile_date'=> now(),
                'status'             => match($row['bank_status'] ?? null) {
                    'active'  => 'active',
                    'overdue' => 'active',
                    'npa'     => 'npa',
                    'closed'  => 'closed',
                    default   => 'pending',
                },
            ]);
            $inserted++;
        }

        return response()->json(compact('inserted', 'updated', 'skipped', 'errors'));
    }

    /**
     * GET /financial/member-loans/needs-block-mapping
     * State Admin, District Admin, and Block Admin. Lists loans with no
     * block_id — e.g. loans created by a State bank-reconciliation upload,
     * which only assigns a District. District/Block Admin see only their own
     * district's unmapped loans and assign the correct block via
     * assignBlock() below. State Admin sees every district's unmapped loans
     * and can instead correct the district via assignDistrict() below.
     */
    public function needsBlockMapping(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->role, ['state', 'district', 'block', 'block_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = MemberLoan::whereNull('memberloan.block_id')
            ->leftJoin('bank', 'bank.ifsc', '=', 'memberloan.ifsc')
            ->with('district:district_id,district_name');

        if ($user->role !== 'state') {
            if (!$user->district_id) {
                return response()->json([]);
            }
            $query->where('memberloan.district_id', $user->district_id);
        }

        $loans = $query->orderByDesc('memberloan.id')
            ->get([
                'memberloan.id', 'memberloan.loan_id', 'memberloan.member_name', 'memberloan.mobile_no',
                'memberloan.bank_acc_no', 'memberloan.ifsc', 'memberloan.district_id', 'memberloan.bank_id',
                'memberloan.created_at', 'bank.branch_name',
            ]);

        return response()->json($loans);
    }

    /**
     * PATCH /financial/member-loans/{memberloan}/assign-block
     * District Admin and Block Admin only. Assigns a block to a loan that's
     * missing one — scoped to blocks within the user's own district.
     */
    public function assignBlock(Request $request, MemberLoan $memberloan): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->role, ['district', 'block', 'block_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if (!$user->district_id || (int) $memberloan->district_id !== (int) $user->district_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'block_id' => 'required|integer|exists:block,block_id',
        ]);

        $block = Block::find($data['block_id']);
        if (!$block || (int) $block->district_id !== (int) $user->district_id) {
            return response()->json(['message' => 'Selected block does not belong to your district.'], 422);
        }

        $memberloan->update(['block_id' => $data['block_id']]);

        return response()->json($memberloan->fresh());
    }

    /**
     * PATCH /financial/member-loans/{memberloan}/assign-district
     * State Admin corrects the district on a loan that hasn't been
     * block-mapped yet — e.g. a bank-reconciliation upload picked the wrong
     * district — by setting it to any valid district. District/Block Admin
     * can only flag a loan as "Not my District" (clear it to null) for a
     * loan currently in their own district, sending it back to State to be
     * reassigned. Only allowed while the loan is still unmapped, since once
     * a block is assigned it belongs to a specific district already.
     */
    public function assignDistrict(Request $request, MemberLoan $memberloan): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->role, ['state', 'district', 'block', 'block_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if ($memberloan->block_id) {
            return response()->json(['message' => 'This loan already has a block assigned — its district can no longer be changed here.'], 422);
        }

        $data = $request->validate([
            'district_id' => 'nullable|integer|exists:district,district_id',
        ]);

        if ($user->role !== 'state') {
            if (!is_null($data['district_id'] ?? null)) {
                return response()->json(['message' => 'Only State Admin can reassign a loan to a specific district.'], 403);
            }
            if (!$user->district_id || (int) $memberloan->district_id !== (int) $user->district_id) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $memberloan->update(['district_id' => $data['district_id'] ?? null]);

        return response()->json($memberloan->fresh());
    }

    /**
     * GET /financial/member-loans/export
     * Full (non-paginated) list, scoped by role like index(), for CSV export.
     */
    public function export(Request $request): JsonResponse
    {
        $query = MemberLoan::with(['member.shg.village.gp.block', 'bank', 'district:district_id,district_name', 'block:block_id,block_name']);
        $user  = $request->user();

        $query->whereNotNull('block_id');

        if ($user->role === 'district' && $user->district_id) {
            $districtId = $user->district_id;
            $query->where(fn($q) => $q
                ->whereIn('shg_code', $this->shgCodesForDistrict($districtId))
                ->orWhere('district_id', $districtId));
        } elseif (in_array($user->role, ['block', 'block_admin', 'block_cadre']) && $user->block_id) {
            $blockId = $user->block_id;
            $query->where(fn($q) => $q
                ->whereIn('shg_code', $this->shgCodesForBlock($blockId))
                ->orWhere('block_id', $blockId));
        }

        if ($request->filled('district_id') && $user->role === 'state') {
            $districtId = $request->integer('district_id');
            $query->where(fn($q) => $q
                ->whereIn('shg_code', $this->shgCodesForDistrict($districtId))
                ->orWhere('district_id', $districtId));
        }
        if ($request->filled('block_id') && in_array($user->role, ['state', 'district'])) {
            $blockId = $request->integer('block_id');
            $query->where(fn($q) => $q
                ->whereIn('shg_code', $this->shgCodesForBlock($blockId))
                ->orWhere('block_id', $blockId));
        }

        $records = $query->orderByDesc('id')->limit(5000)->get();

        return response()->json($records->map(fn ($r) => $this->formatRecord($r))->values());
    }
}
