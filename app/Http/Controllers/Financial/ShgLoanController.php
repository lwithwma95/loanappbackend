<?php

namespace App\Http\Controllers\Financial;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Block;
use App\Models\BlockCadre;
use App\Models\Shg;
use App\Models\Shgloan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShgLoanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Shgloan::with(['shg.village.gp.block', 'cadre', 'bank', 'scheme:id,bank_name,scheme_name', 'district:district_id,district_name', 'block:block_id,block_name']);
        $user  = $request->user();

        if ($user->role === 'district' && $user->district_id) {
            $query->where('district_id', $user->district_id);
        } elseif (in_array($user->role, ['block', 'block_admin', 'block_cadre']) && $user->block_id) {
            $query->where('block_id', $user->block_id);
        }

        if ($request->filled('district_id') && $user->role === 'state') {
            $query->where('district_id', $request->integer('district_id'));
        }
        if ($request->filled('block_id') && in_array($user->role, ['state', 'district'])) {
            $query->where('block_id', $request->integer('block_id'));
        }

        // Per-SHG lookup (used by the loan pre-check before creating) — bypasses cadre filter
        if ($request->filled('shg_code')) {
            $query->where('shg_code', $request->string('shg_code'));
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
                $q->where('shg_code', 'like', "%{$s}%")
                  ->orWhere('loan_id', 'like', "%{$s}%")
                  ->orWhere('mobile_no', 'like', "%{$s}%")
                  ->orWhereHas('shg', fn($q2) => $q2->where('shg_name', 'like', "%{$s}%"));
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

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->role, ['block', 'block_admin', 'block_cadre'])) {
            return response()->json(['message' => 'Only Block staff can create SHG loans.'], 403);
        }

        $data = $request->validate([
            'loan_id'            => 'required|string|max:50|unique:shgloan,loan_id',
            // Same guard as MemberLoanController::store() on mem_code — the SHG picked in the
            // creation wizard always comes from our own hierarchy, so it must resolve.
            // (bulkUpload() deliberately skips this: banks report SHGs we may not have yet.)
            'shg_code'           => 'required|string|max:100|exists:shg,shg_code',
            'mobile_no'          => 'required|string|max:15',
            'loan_amount'        => 'required|numeric|min:1',
            'bank_acc_no'        => 'required|string|max:30',
            'loan_sanction_date' => 'required|date',
            'payment_date'       => 'nullable|date',
            'interest_rate'      => 'nullable|numeric|min:0',
            'tenure_months'      => 'nullable|integer|min:1',
            'emi_amount'         => 'nullable|numeric|min:0',
            'ifsc'               => 'nullable|string|max:11',
            'bank_id'            => 'nullable|integer|exists:bank,id',
            'scheme_id'          => 'nullable|integer|exists:loan_schemes,id',
            'bank_sakhi'         => 'nullable|string|max:150',
            'monitoring_cadre'   => 'nullable|integer|exists:block_cadres,id',
            'already_paid'       => 'nullable|numeric|min:0',
            'remarks'            => 'nullable|string',
        ]);

        // Block creating a second loan if the SHG still has an open one
        $hasOpenLoan = Shgloan::where('shg_code', $data['shg_code'])
            ->where('status', '!=', 'closed')
            ->exists();

        if ($hasOpenLoan) {
            return response()->json([
                'message' => 'Previous loan is not Closed. Please close the existing loan before creating a new one.',
            ], 422);
        }

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
        $data['status']       = 'active';

        if (!empty($data['bank_id']) && empty($data['ifsc'])) {
            $data['ifsc'] = Bank::find($data['bank_id'])?->ifsc;
        }

        $loan = Shgloan::create($data);
        $loan->load(['shg.village.gp.block', 'cadre', 'bank', 'scheme:id,bank_name,scheme_name', 'district:district_id,district_name', 'block:block_id,block_name']);

        return response()->json($this->formatRecord($loan), 201);
    }

    public function show(Shgloan $shgloan): JsonResponse
    {
        $shgloan->load(['shg.village.gp.block.district', 'cadre', 'bank', 'scheme:id,bank_name,scheme_name', 'district:district_id,district_name', 'block:block_id,block_name']);

        $payments = $shgloan->payments()->with('collectedBy')->orderBy('date_payment', 'desc')->get();

        return response()->json([
            ...$this->formatRecord($shgloan),
            'payments_history' => $payments,
        ]);
    }

    public function update(Request $request, Shgloan $shgloan): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->role, ['block', 'block_admin', 'block_cadre'])) {
            return response()->json(['message' => 'Insufficient permissions.'], 403);
        }

        $data = $request->validate([
            'loan_sanction_date' => 'sometimes|date',
            'payment_date'     => 'nullable|date',
            'interest_rate'    => 'nullable|numeric|min:0',
            'tenure_months'    => 'nullable|integer|min:1',
            'emi_amount'       => 'nullable|numeric|min:0',
            // shg_code may be remapped to the correct SHG during KYC completion.
            'shg_code'         => 'sometimes|string|max:100',
            'mobile_no'        => 'sometimes|nullable|string|max:15',
            'bank_acc_no'      => 'nullable|string|max:30',
            'ifsc'             => 'nullable|string|max:11',
            'bank_id'          => 'nullable|integer|exists:bank,id',
            'scheme_id'        => 'nullable|integer|exists:loan_schemes,id',
            'block_id'         => 'nullable|integer|exists:block,block_id',
            'bank_sakhi'       => 'nullable|string|max:150',
            'monitoring_cadre' => 'nullable|integer|exists:block_cadres,id',
            'status'           => 'sometimes|in:pending,active,closed,npa',
            'bank_status'      => 'sometimes|nullable|in:pending,active,closed,npa',
            // Explicitly marks the loan as reviewed, even if shg_code still can't be
            // matched to a real record — used by the "Complete KYC" flow.
            'kyc_completed'    => 'sometimes|boolean',
            'remarks'          => 'nullable|string',
        ]);

        // Changing the block is restricted to a block within the requesting
        // admin's own district — same guard as MemberLoanController::update().
        if (array_key_exists('block_id', $data) && $data['block_id']) {
            $block = Block::find($data['block_id']);
            if (!$block || !$user->district_id || (int) $block->district_id !== (int) $user->district_id) {
                return response()->json(['message' => 'Selected block does not belong to your district.'], 422);
            }
        }

        // Recalculate total_due and emi_amount whenever interest rate or tenure change (Reconcile action).
        if (array_key_exists('interest_rate', $data) || array_key_exists('tenure_months', $data)) {
            $amount  = (float) $shgloan->loan_amount;
            $paid    = (float) $shgloan->already_paid;
            $rate    = (float) (array_key_exists('interest_rate', $data) ? ($data['interest_rate'] ?? 0) : ($shgloan->interest_rate ?? 0));
            $tenure  = (int) (array_key_exists('tenure_months', $data) ? ($data['tenure_months'] ?? 0) : ($shgloan->tenure_months ?? 0));
            $interest = $tenure > 0 ? round($amount * ($rate / 100) * ($tenure / 12), 2) : 0;
            $data['total_due'] = round(max(0, ($amount + $interest) - $paid), 2);
            if ($tenure > 0 && !array_key_exists('emi_amount', $data)) {
                $data['emi_amount'] = round($amount / $tenure, 2);
            }
            // Clear the reconcile flag — loan is now up to date
            $data['prev_loan_amount'] = null;
        }

        $shgloan->update($data);
        $shgloan->load(['shg.village.gp.block', 'cadre', 'bank', 'scheme:id,bank_name,scheme_name', 'district:district_id,district_name', 'block:block_id,block_name']);

        return response()->json($this->formatRecord($shgloan));
    }

    public function destroy(Shgloan $shgloan): JsonResponse
    {
        if (!in_array(request()->user()->role, ['block', 'block_admin'])) {
            return response()->json(['message' => 'Insufficient permissions.'], 403);
        }

        $shgloan->update(['status' => 'closed']);
        return response()->json(['message' => 'Loan closed.']);
    }

    private function formatRecord(Shgloan $r): array
    {
        $npa = $r->computeNpaStatus();
        $paidPct = $r->total_due > 0 ? round(($r->already_paid / ($r->loan_amount ?: 1)) * 100, 1) : 100;

        $shg = $r->relationLoaded('shg') ? $r->shg : null;
        $shgGeo = $shg ? [
            'shg_name'    => $shg->shg_name,
            'village_name' => $shg->village?->vill_name,
            'gp_name'     => $shg->village?->gp?->gp_name,
            'block_name'  => $shg->village?->gp?->block?->block_name,
        ] : null;

        return array_merge($r->toArray(), $npa, [
            'paid_percentage' => $paidPct,
            'status_mismatch' => $r->bank_status !== null && $r->bank_status !== $r->status,
            'needs_kyc' => !$r->kyc_completed && $shg === null && $r->shg_code !== null,
            'is_old_nrlm_shg' => $shgGeo === null && $r->shg_code !== null,
            'shg_geo' => $shgGeo,
        ]);
    }

    /**
     * POST /financial/shg-loans/bulk
     * District/Block admins upload a bank-reported reconciliation sheet.
     * Existing loans (matched by loan_id) get their bank_status updated;
     * loans the bank reports that we don't have yet are created — even when
     * shg_code doesn't exist in our shg table (no FK enforced).
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
            'block_id'             => $user->role === 'district' ? 'required|integer|exists:block,block_id' : 'nullable|integer',
            'loans'                => 'required|array|min:1|max:1000',
            'loans.*.loan_id'      => 'required|string|max:50',
            'loans.*.shg_code'     => 'required|string|max:100',
            'loans.*.loan_amount'  => 'required|numeric|min:0',
            'loans.*.bank_acc_no'  => 'nullable|string|max:30',
            'loans.*.ifsc'         => 'nullable|string|max:11',
            'loans.*.bank_status'  => 'nullable|in:pending,active,closed,npa',
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

            $existing = Shgloan::where('loan_id', $loanId)->first();

            if ($existing) {
                $newAmount  = (float) $row['loan_amount'];
                $updateData = [
                    'prev_loan_amount' => (float) $existing->loan_amount,
                    'loan_amount' => $newAmount,
                    'total_due'   => round(max(0, $newAmount - (float) $existing->already_paid), 2),
                    'bank_status' => $row['bank_status'] ?? $existing->bank_status,
                    'district_id' => $existing->district_id ?? $districtId,
                    'block_id'    => $existing->block_id ?? $blockId,
                ];
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

            Shgloan::create([
                'loan_id'            => $loanId,
                'shg_code'           => trim($row['shg_code']),
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
     * GET /financial/shg-loans/export
     * Full (non-paginated) list, scoped by role like index(), for CSV export.
     */
    public function export(Request $request): JsonResponse
    {
        $query = Shgloan::with(['shg.village.gp.block', 'bank', 'scheme:id,bank_name,scheme_name', 'district:district_id,district_name', 'block:block_id,block_name']);
        $user  = $request->user();

        if ($user->role === 'district' && $user->district_id) {
            $query->where('district_id', $user->district_id);
        } elseif (in_array($user->role, ['block', 'block_admin', 'block_cadre']) && $user->block_id) {
            $query->where('block_id', $user->block_id);
        }

        if ($request->filled('district_id') && $user->role === 'state') {
            $query->where('district_id', $request->integer('district_id'));
        }
        if ($request->filled('block_id') && in_array($user->role, ['state', 'district'])) {
            $query->where('block_id', $request->integer('block_id'));
        }

        $records = $query->orderByDesc('id')->limit(5000)->get();

        return response()->json($records->map(fn ($r) => $this->formatRecord($r))->values());
    }
}
