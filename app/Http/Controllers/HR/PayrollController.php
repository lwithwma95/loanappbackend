<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\EmpAttendance;
use App\Models\EmpSalary;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    /**
     * Build a base Employee query scoped to the authenticated user's role.
     */
    private function scopedEmployeeQuery(Request $request, bool $forWrite = false)
    {
        $user  = $request->user();
        $query = Employee::with('designation');

        if ($user->role === 'state') {
            if ($forWrite) {
                // Writes: only state-designation employees
                $query->whereHas('designation', fn($q) => $q->where('role', 'state'));
            }
            // Reads: no filter — state admin views all payroll
        } elseif ($user->role === 'district') {
            $query->where('district_id', $user->district_id);
        } elseif (in_array($user->role, ['block', 'block_admin']) && $user->block_id) {
            $query->where('block_id', $user->block_id);
        } else {
            abort(403);
        }

        return $query;
    }

    /**
     * Verify an employee belongs to the authenticated user's scope.
     */
    private function assertEmployeeScope($user, Employee $employee): ?JsonResponse
    {
        if ($user->role === 'district' && $employee->district_id !== $user->district_id) {
            return response()->json(['message' => 'Employee not in your district.'], 403);
        }

        if ($user->role === 'state' && $employee->designation?->role !== 'state') {
            return response()->json(['message' => 'You can only manage state employees.'], 403);
        }

        if (in_array($user->role, ['block', 'block_admin']) && $employee->block_id !== $user->block_id) {
            return response()->json(['message' => 'Employee not in your block.'], 403);
        }

        return null;
    }

    /**
     * GET /hr/payroll?month=&year=
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer|min:2020',
        ]);

        $empQuery = $this->scopedEmployeeQuery($request);
        $role  = $request->user()->role;
        $scope = $request->input('scope');
        if ($role === 'state') {
            if ($scope === 'state_only') {
                $empQuery->whereHas('designation', fn($q) => $q->where('role', 'state'));
            } elseif ($scope === 'district_only') {
                $empQuery->whereHas('designation', fn($q) => $q->where('role', 'district'));
            } elseif ($scope === 'block_only') {
                $empQuery->whereHas('designation', fn($q) => $q->where('role', 'block'));
            }
            if ($request->filled('district_id')) $empQuery->where('district_id', $request->integer('district_id'));
        }
        if ($role === 'district' && $scope === 'district_only') {
            $empQuery->whereHas('designation', fn($q) => $q->where('role', 'district'));
        }
        if (in_array($role, ['state', 'district']) && $request->filled('block_id')) {
            $empQuery->where('block_id', $request->integer('block_id'));
        }
        $empIds = $empQuery->pluck('id');

        $records = EmpSalary::with(['employee.designation', 'attendance'])
            ->whereIn('employee_id', $empIds)
            ->where('month', $request->month)
            ->where('year',  $request->year)
            ->get();

        return response()->json($records);
    }

    /**
     * POST /hr/payroll
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->role, ['state', 'district'])) {
            return response()->json(['message' => 'Insufficient permissions.'], 403);
        }

        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'month'       => 'required|integer|between:1,12',
            'year'        => 'required|integer|min:2020',
        ]);

        $employee = Employee::with('designation')->findOrFail($validated['employee_id']);

        $scopeError = $this->assertEmployeeScope($user, $employee);
        if ($scopeError) {
            return $scopeError;
        }

        // Prevent duplicate
        $exists = EmpSalary::where('employee_id', $employee->id)
            ->where('month', $validated['month'])
            ->where('year',  $validated['year'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Salary for this employee and month/year already exists.'], 409);
        }

        // Attendance is required before salary can be generated
        $attendance = EmpAttendance::where('employee_id', $employee->id)
            ->where('month', $validated['month'])
            ->where('year',  $validated['year'])
            ->first();

        if (!$attendance) {
            return response()->json([
                'message' => 'Attendance has not been recorded for this employee for the selected month/year. Please record attendance first.',
            ], 422);
        }

        // ── Salary calculation ───────────────────────────────────
        $basic   = (float) $employee->salary;
        $mobile  = (float) ($employee->mobile_allowance ?? 1500);
        $profDev = (float) ($employee->professional_dev ?? 2500);
        $gross   = $basic + $mobile + $profDev;
        $epf     = (float) $employee->epf;
        $profTax = (float) $employee->deduction;
        $net     = $gross - $epf - $profTax;

        $paidDays = $attendance->days_present
            + $attendance->casual_leave_taken
            + $attendance->medical_leave_taken
            + $attendance->maternity_leave_taken
            + $attendance->paternity_leave_taken;
        $totalWD = $attendance->total_working_days;

        $actual = ($paidDays >= $totalWD)
            ? $net
            : round(($net / $totalWD) * $paidDays, 2);
        // ────────────────────────────────────────────────────────

        $salary = EmpSalary::create([
            'employee_id'       => $employee->id,
            'attendance_id'     => $attendance?->id,
            'month'             => $validated['month'],
            'year'              => $validated['year'],
            'total_working_days'=> $totalWD,
            'paid_days'         => $paidDays,
            'basic_salary'      => $basic,
            'mobile_allowance'  => $mobile,
            'professional_dev'  => $profDev,
            'gross_salary'      => $gross,
            'epf_deduction'     => $epf,
            'professional_tax'  => $profTax,
            'net_salary'        => $net,
            'actual_paid_salary'=> $actual,
            'status'            => 'draft',
            'created_by'        => $user->id,
        ]);

        return response()->json($salary->load(['employee.designation', 'attendance']), 201);
    }

    /**
     * GET /hr/payroll/{payroll}
     */
    public function show(Request $request, EmpSalary $payroll): JsonResponse
    {
        $user     = $request->user();
        $employee = $payroll->employee()->with('designation')->first();

        $scopeError = $this->assertEmployeeScope($user, $employee);
        if ($scopeError) {
            return $scopeError;
        }

        return response()->json($payroll->load(['employee.designation', 'attendance']));
    }

    /**
     * PUT /hr/payroll/{payroll}  — full update (re-compute if still draft)
     */
    public function update(Request $request, EmpSalary $payroll): JsonResponse
    {
        $user = $request->user();

        if ($payroll->status !== 'draft') {
            return response()->json(['message' => 'Only draft salary records can be updated.'], 422);
        }

        $employee = $payroll->employee()->with('designation')->first();
        $scopeError = $this->assertEmployeeScope($user, $employee);
        if ($scopeError) {
            return $scopeError;
        }

        // Re-fetch attendance in case it was updated
        $attendance = EmpAttendance::where('employee_id', $payroll->employee_id)
            ->where('month', $payroll->month)
            ->where('year',  $payroll->year)
            ->first();

        $basic   = (float) $employee->salary;
        $mobile  = (float) ($employee->mobile_allowance ?? 1500);
        $profDev = (float) ($employee->professional_dev ?? 2500);
        $gross   = $basic + $mobile + $profDev;
        $epf     = (float) $employee->epf;
        $profTax = (float) $employee->deduction;
        $net     = $gross - $epf - $profTax;

        if ($attendance) {
            $paidDays = $attendance->days_present
                + $attendance->casual_leave_taken
                + $attendance->medical_leave_taken
                + $attendance->maternity_leave_taken
                + $attendance->paternity_leave_taken;
            $totalWD = $attendance->total_working_days;
        } else {
            $paidDays = 26;
            $totalWD  = 26;
        }

        $actual = ($paidDays >= $totalWD)
            ? $net
            : round(($net / $totalWD) * $paidDays, 2);

        $payroll->update([
            'attendance_id'     => $attendance?->id,
            'total_working_days'=> $totalWD,
            'paid_days'         => $paidDays,
            'basic_salary'      => $basic,
            'mobile_allowance'  => $mobile,
            'professional_dev'  => $profDev,
            'gross_salary'      => $gross,
            'epf_deduction'     => $epf,
            'professional_tax'  => $profTax,
            'net_salary'        => $net,
            'actual_paid_salary'=> $actual,
        ]);

        return response()->json($payroll->fresh()->load(['employee.designation', 'attendance']));
    }

    /**
     * PATCH /hr/payroll/{payroll}/status
     */
    public function updateStatus(Request $request, EmpSalary $payroll): JsonResponse
    {
        $user = $request->user();

        $employee = $payroll->employee()->with('designation')->first();
        $scopeError = $this->assertEmployeeScope($user, $employee);
        if ($scopeError) {
            return $scopeError;
        }

        $validated = $request->validate([
            'status'         => 'required|in:paid,cancelled',
            'payment_date'   => 'required_if:status,paid|nullable|date',
            'payment_mode'   => 'nullable|in:cash,bank,online,cheque,upi',
            'transaction_id' => 'nullable|string|max:100',
            'remarks'        => 'nullable|string',
        ]);

        // Enforce allowed transitions: draft→paid, draft→cancelled, paid→cancelled
        $current = $payroll->status;
        $next    = $validated['status'];

        $allowed = [
            'draft' => ['paid', 'cancelled'],
            'paid'  => ['cancelled'],
        ];

        if (!isset($allowed[$current]) || !in_array($next, $allowed[$current])) {
            return response()->json([
                'message' => "Transition from '{$current}' to '{$next}' is not allowed.",
            ], 422);
        }

        $payroll->update([
            'status'         => $next,
            'payment_date'   => $validated['payment_date']   ?? null,
            'payment_mode'   => $validated['payment_mode']   ?? null,
            'transaction_id' => $validated['transaction_id'] ?? null,
            'remarks'        => $validated['remarks']        ?? $payroll->remarks,
        ]);

        return response()->json($payroll->fresh()->load(['employee.designation', 'attendance']));
    }

    /**
     * DELETE /hr/payroll/{payroll}
     */
    public function destroy(Request $request, EmpSalary $payroll): JsonResponse
    {
        $user = $request->user();

        $employee = $payroll->employee()->with('designation')->first();
        $scopeError = $this->assertEmployeeScope($user, $employee);
        if ($scopeError) {
            return $scopeError;
        }

        if ($payroll->status !== 'draft') {
            return response()->json(['message' => 'Only draft salary records can be deleted.'], 422);
        }

        $payroll->delete();

        return response()->json(['message' => 'Salary record deleted successfully.'], 200);
    }
}
