<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\EmpAttendance;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    private function scopedQuery(Request $request, bool $forWrite = false)
    {
        $user  = $request->user();
        $query = Employee::with('designation');

        if ($user->role === 'state') {
            if ($forWrite) {
                // Writes: only state-designation employees
                $query->whereHas('designation', fn($q) => $q->where('role', 'state'));
            }
            // Reads: no filter — state admin sees all
        } elseif ($user->role === 'district') {
            $query->where('district_id', $user->district_id);
        } elseif (in_array($user->role, ['block', 'block_admin']) && $user->block_id) {
            $query->where('block_id', $user->block_id);
        } else {
            abort(403);
        }

        return $query;
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer|min:2020',
        ]);

        $empQuery = $this->scopedQuery($request);
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

        $records = EmpAttendance::with('employee.designation')
            ->whereIn('employee_id', $empIds)
            ->where('month', $request->month)
            ->where('year',  $request->year)
            ->get();

        return response()->json($records);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->role, ['state', 'district'])) {
            return response()->json(['message' => 'Insufficient permissions.'], 403);
        }

        $data = $request->validate([
            'employee_id'           => 'required|integer|exists:employees,id',
            'month'                 => 'required|integer|between:1,12',
            'year'                  => 'required|integer|min:2020',
            'total_working_days'    => 'required|integer|between:1,31',
            'days_present'          => 'required|integer|min:0|max:31',
            'casual_leave_taken'    => 'nullable|integer|min:0|max:2',
            'medical_leave_taken'   => 'nullable|integer|min:0|max:12',
            'maternity_leave_taken' => 'nullable|integer|min:0|max:180',
            'paternity_leave_taken' => 'nullable|integer|min:0|max:15',
            'remarks'               => 'nullable|string|max:500',
        ]);

        // Scope check
        $emp = Employee::with('designation')->findOrFail($data['employee_id']);

        if ($user->role === 'district' && $emp->district_id !== $user->district_id) {
            return response()->json(['message' => 'Employee not in your district.'], 403);
        }

        if ($user->role === 'state' && $emp->designation?->role !== 'state') {
            return response()->json(['message' => 'You can only manage state employees.'], 403);
        }

        $data['created_by']             = $user->id;
        $data['casual_leave_taken']     = $data['casual_leave_taken']    ?? 0;
        $data['medical_leave_taken']    = $data['medical_leave_taken']   ?? 0;
        $data['maternity_leave_taken']  = $data['maternity_leave_taken'] ?? 0;
        $data['paternity_leave_taken']  = $data['paternity_leave_taken'] ?? 0;

        $record = EmpAttendance::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'month' => $data['month'], 'year' => $data['year']],
            $data
        );

        return response()->json(
            $record->load('employee.designation'),
            $record->wasRecentlyCreated ? 201 : 200
        );
    }

    public function show(Request $request, EmpAttendance $attendance): JsonResponse
    {
        return response()->json($attendance->load('employee.designation'));
    }
}
