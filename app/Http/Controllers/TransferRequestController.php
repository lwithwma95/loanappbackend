<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeTransferRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $me = $request->user();

        if ($me->role === 'state') {
            $requests = EmployeeTransferRequest::with([
                'employee:id,emp_name,emp_id,district_id,block_id',
                'fromDistrict:district_id,district_name',
                'fromBlock:block_id,block_name',
                'toDistrict:district_id,district_name',
                'toBlock:block_id,block_name',
                'reviewer:id,name',
            ])->orderByDesc('created_at')->get();
        } else {
            $emp = Employee::where('user_id', $me->id)->first();
            if (!$emp) {
                return response()->json([]);
            }
            $requests = EmployeeTransferRequest::with([
                'toDistrict:district_id,district_name',
                'toBlock:block_id,block_name',
                'reviewer:id,name',
            ])->where('employee_id', $emp->id)
              ->orderByDesc('created_at')
              ->get();
        }

        return response()->json($requests);
    }

    public function store(Request $request): JsonResponse
    {
        $me  = $request->user();
        $emp = Employee::where('user_id', $me->id)->first();

        if (!$emp) {
            return response()->json(['message' => 'No employee record linked to your account.'], 404);
        }

        if (EmployeeTransferRequest::where('employee_id', $emp->id)
                                   ->where('status', 'pending')
                                   ->exists()) {
            return response()->json(['message' => 'You already have a pending transfer request.'], 422);
        }

        $validated = $request->validate([
            'to_district_id' => 'required|integer|exists:district,district_id',
            'to_block_id'    => 'nullable|integer|exists:block,block_id',
            'reason'         => 'nullable|string|max:1000',
        ]);

        $transfer = EmployeeTransferRequest::create([
            'employee_id'      => $emp->id,
            'from_district_id' => $emp->district_id,
            'from_block_id'    => $emp->block_id,
            'to_district_id'   => $validated['to_district_id'],
            'to_block_id'      => $validated['to_block_id'] ?? null,
            'reason'           => $validated['reason'] ?? null,
            'status'           => 'pending',
        ]);

        return response()->json($transfer->load(['toDistrict', 'toBlock']), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $me = $request->user();
        if ($me->role !== 'state') {
            return response()->json(['message' => 'Only state admins can process transfer requests.'], 403);
        }

        $transfer = EmployeeTransferRequest::with('employee')->findOrFail($id);

        if ($transfer->status !== 'pending') {
            return response()->json(['message' => 'This request has already been processed.'], 422);
        }

        $validated = $request->validate([
            'action'        => 'required|in:approve,reject',
            'reviewer_note' => 'nullable|string|max:500',
        ]);

        $approved = $validated['action'] === 'approve';

        $transfer->update([
            'status'        => $approved ? 'approved' : 'rejected',
            'reviewed_by'   => $me->id,
            'reviewed_at'   => now(),
            'reviewer_note' => $validated['reviewer_note'] ?? null,
        ]);

        if ($approved) {
            $emp = $transfer->employee;
            $emp->update([
                'district_id' => $transfer->to_district_id,
                'block_id'    => $transfer->to_block_id,
            ]);
            // Keep User geo in sync
            if ($emp->user_id) {
                User::where('id', $emp->user_id)->update([
                    'district_id' => $transfer->to_district_id,
                    'block_id'    => $transfer->to_block_id,
                ]);
            }
        }

        return response()->json($transfer->fresh(['toDistrict', 'toBlock', 'reviewer']));
    }
}
