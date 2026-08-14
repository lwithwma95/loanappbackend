<?php

namespace App\Http\Controllers\Financial;

use App\Http\Controllers\Controller;
use App\Models\MemberLoan;
use App\Models\MemberLoanPayment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberLoanPaymentController extends Controller
{
    public function index(MemberLoan $memberloan): JsonResponse
    {
        $payments = $memberloan->payments()
            ->with('collectedBy')
            ->orderBy('date_payment', 'desc')
            ->get();

        return response()->json($payments);
    }

    public function store(Request $request, MemberLoan $memberloan): JsonResponse
    {
        if (!in_array($request->user()->role, ['block', 'block_admin', 'block_cadre'])) {
            return response()->json(['message' => 'Only Block staff can record payments.'], 403);
        }

        $data = $request->validate([
            'payment_amount' => 'required|numeric|min:0.01',
            'date_payment'   => 'required|date',
            'updated_by'     => 'nullable|integer|exists:block_cadres,id',
            'transaction_id' => 'nullable|string|max:100',
            'mode_of_payment'=> 'required|in:cash,bank,online,cheque,upi',
            'remarks'        => 'nullable|string',
        ]);

        $data['loan_id'] = $memberloan->id;

        $payment = MemberLoanPayment::create($data);

        // Update running totals on the loan
        $newPaid = $memberloan->already_paid + $data['payment_amount'];
        $newDue  = max(0, $memberloan->total_due - $data['payment_amount']);

        $updates = [
            'already_paid' => $newPaid,
            'total_due'    => $newDue,
        ];

        // Advance next EMI due date by one month
        if ($memberloan->payment_date) {
            $updates['payment_date'] = Carbon::parse($memberloan->payment_date)->addMonth()->format('Y-m-d');
        }

        // Close automatically when fully paid
        if ($newDue <= 0) {
            $updates['status'] = 'closed';
        }

        $memberloan->update($updates);

        return response()->json($payment->load('collectedBy'), 201);
    }

    public function destroy(Request $request, MemberLoan $memberloan, MemberLoanPayment $payment): JsonResponse
    {
        $role = $request->user()->role;

        // State and District admins cannot delete payments
        if (in_array($role, ['state', 'district'])) {
            return response()->json(['message' => 'State and District admins cannot delete payments.'], 403);
        }

        if (!in_array($role, ['block', 'block_admin', 'block_cadre'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($payment->loan_id !== $memberloan->id) {
            return response()->json(['message' => 'Payment does not belong to this loan.'], 404);
        }

        $today = Carbon::today();

        // Check BOTH dates — hide delete if either exceeds the allowed window
        $daysFromPayment = $today->diffInDays(Carbon::parse($payment->date_payment)->startOfDay(), true);
        $daysFromCreated = $today->diffInDays(Carbon::parse($payment->created_at)->startOfDay(), true);
        $daysOld = max($daysFromPayment, $daysFromCreated);

        $limit = match ($role) {
            'block_cadre'           => 3,
            'block', 'block_admin'  => 5,
            default                 => 0,
        };

        if ($daysOld > $limit) {
            return response()->json([
                'message' => "Payments older than {$limit} days cannot be deleted.",
            ], 403);
        }

        // Delete first, then recalculate totals from remaining payments
        $payment->delete();

        $memberloan->refresh();
        $newPaid  = $memberloan->payments()->sum('payment_amount');
        $rate     = (float) ($memberloan->interest_rate ?? 0);
        $tenure   = (int) ($memberloan->tenure_months ?? 0);
        $interest = $tenure > 0 ? round($memberloan->loan_amount * ($rate / 100) * ($tenure / 12), 2) : 0;
        $newDue   = max(0, ($memberloan->loan_amount + $interest) - $newPaid);

        $updates = [
            'already_paid' => $newPaid,
            'total_due'    => $newDue,
        ];

        // Roll back the next-due date by one month
        if ($memberloan->payment_date) {
            $updates['payment_date'] = Carbon::parse($memberloan->payment_date)->subMonth()->format('Y-m-d');
        }

        // Reopen if it was auto-closed and now has outstanding balance
        if ($memberloan->status === 'closed' && $newDue > 0) {
            $updates['status'] = 'active';
        }

        $memberloan->update($updates);

        return response()->json(['message' => 'Payment deleted.']);
    }
}
