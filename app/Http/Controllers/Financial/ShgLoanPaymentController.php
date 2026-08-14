<?php

namespace App\Http\Controllers\Financial;

use App\Http\Controllers\Controller;
use App\Models\Shgloan;
use App\Models\ShgloanPayment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShgLoanPaymentController extends Controller
{
    public function index(Shgloan $shgloan): JsonResponse
    {
        $payments = $shgloan->payments()
            ->with('collectedBy')
            ->orderBy('date_payment', 'desc')
            ->get();

        return response()->json($payments);
    }

    public function store(Request $request, Shgloan $shgloan): JsonResponse
    {
        if (!in_array($request->user()->role, ['block', 'block_admin', 'block_cadre'])) {
            return response()->json(['message' => 'Only Block staff can record payments.'], 403);
        }

        $data = $request->validate([
            'payment_amount'  => 'required|numeric|min:0.01',
            'date_payment'    => 'required|date',
            'updated_by'      => 'nullable|integer|exists:block_cadres,id',
            'transaction_id'  => 'nullable|string|max:100',
            'mode_of_payment' => 'required|in:cash,bank,online,cheque,upi',
            'remarks'         => 'nullable|string',
        ]);

        $data['loan_id'] = $shgloan->id;

        $payment = ShgloanPayment::create($data);

        $newPaid = $shgloan->already_paid + $data['payment_amount'];
        $newDue  = max(0, $shgloan->total_due - $data['payment_amount']);

        $updates = [
            'already_paid' => $newPaid,
            'total_due'    => $newDue,
        ];

        if ($shgloan->payment_date) {
            $updates['payment_date'] = Carbon::parse($shgloan->payment_date)->addMonth()->format('Y-m-d');
        }

        if ($newDue <= 0) {
            $updates['status'] = 'closed';
        }

        $shgloan->update($updates);

        return response()->json($payment->load('collectedBy'), 201);
    }

    public function destroy(Request $request, Shgloan $shgloan, ShgloanPayment $payment): JsonResponse
    {
        $role = $request->user()->role;

        if (in_array($role, ['state', 'district'])) {
            return response()->json(['message' => 'State and District admins cannot delete payments.'], 403);
        }

        if (!in_array($role, ['block', 'block_admin', 'block_cadre'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($payment->loan_id !== $shgloan->id) {
            return response()->json(['message' => 'Payment does not belong to this loan.'], 404);
        }

        $today = Carbon::today();

        $daysFromPayment = $today->diffInDays(Carbon::parse($payment->date_payment)->startOfDay(), true);
        $daysFromCreated = $today->diffInDays(Carbon::parse($payment->created_at)->startOfDay(), true);
        $daysOld = max($daysFromPayment, $daysFromCreated);

        $limit = match ($role) {
            'block_cadre'          => 3,
            'block', 'block_admin' => 5,
            default                => 0,
        };

        if ($daysOld > $limit) {
            return response()->json([
                'message' => "Payments older than {$limit} days cannot be deleted.",
            ], 403);
        }

        $payment->delete();

        $shgloan->refresh();
        $newPaid  = $shgloan->payments()->sum('payment_amount');
        $rate     = (float) ($shgloan->interest_rate ?? 0);
        $tenure   = (int) ($shgloan->tenure_months ?? 0);
        $interest = $tenure > 0 ? round($shgloan->loan_amount * ($rate / 100) * ($tenure / 12), 2) : 0;
        $newDue   = max(0, ($shgloan->loan_amount + $interest) - $newPaid);

        $updates = [
            'already_paid' => $newPaid,
            'total_due'    => $newDue,
        ];

        if ($shgloan->payment_date) {
            $updates['payment_date'] = Carbon::parse($shgloan->payment_date)->subMonth()->format('Y-m-d');
        }

        if ($shgloan->status === 'closed' && $newDue > 0) {
            $updates['status'] = 'active';
        }

        $shgloan->update($updates);

        return response()->json(['message' => 'Payment deleted.']);
    }
}
