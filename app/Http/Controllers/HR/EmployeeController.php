<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $user  = $request->user();
        $query = Employee::query()->with(['designation', 'district', 'block'])->ordered();

        if ($user->role === 'state') {
            $scope = $request->input('scope');
            if ($scope === 'state_only') {
                $query->whereHas('designation', fn($q) => $q->where('role', 'state'));
            } elseif ($scope === 'district_only') {
                $query->whereHas('designation', fn($q) => $q->where('role', 'district'));
            } elseif ($scope === 'block_only') {
                $query->whereHas('designation', fn($q) => $q->where('role', 'block'));
            }
            if ($request->filled('district_id')) {
                $query->where('district_id', $request->integer('district_id'));
            }
            if ($request->filled('block_id')) {
                $query->where('block_id', $request->integer('block_id'));
            }
        } elseif ($user->role === 'district') {
            $query->where('district_id', $user->district_id);
            if ($request->input('scope') === 'district_only') {
                $query->whereHas('designation', fn($q) => $q->where('role', 'district'));
            }
            if ($request->filled('block_id')) {
                $query->where('block_id', $request->integer('block_id'));
            }
        } elseif (\in_array($user->role, ['block', 'block_admin']) && $user->block_id) {
            $query->where('block_id', $user->block_id);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $authUser = $request->user();

        $data = $request->validate([
            'designation_id'    => 'required|exists:designations,id',
            'emp_name'          => 'required|string|max:255',
            'email'             => 'nullable|email|max:255|unique:employees,email|unique:users,email',
            'emp_dob'           => 'nullable|date|before:today',
            'emp_doj'           => 'required|date',
            'emp_qualification' => 'nullable|string|max:255',
            'aadhaar_number'    => 'nullable|digits:12',
            'salary'            => 'required|numeric|min:0',
            'epf'               => 'required|numeric|min:0',
            'deduction'         => 'nullable|numeric|min:0',
            'gross_salary'      => 'nullable|numeric|min:0',
            'net_salary'        => 'nullable|numeric|min:0',
            'district_id'       => 'nullable|integer',
            'block_id'          => 'nullable|integer',
            'bank_account'      => 'nullable|string|max:20',
            'ifsc_code'         => 'nullable|string|max:11',
            'phone'             => 'nullable|string|max:15',
            'status'            => 'nullable|in:active,inactive',
        ]);

        if ($authUser->role === 'district') {
            $desig = Designation::findOrFail($data['designation_id']);
            $data['district_id'] = $authUser->district_id;
            if ($desig->role === 'block') {
                if (empty($data['block_id'])) {
                    return response()->json(['errors' => ['block_id' => ['Please select a block for this block-level designation.']]], 422);
                }
            } else {
                $data['block_id'] = null;
            }
        } elseif (\in_array($authUser->role, ['block', 'block_admin'])) {
            $desig = Designation::findOrFail($data['designation_id']);
            if ($desig->role !== 'block') {
                return response()->json(['message' => 'You can only create block-level employees.'], 403);
            }
            $data['district_id'] = $authUser->district_id;
            $data['block_id']    = $authUser->block_id;
        }

        $data['emp_id'] = Employee::generateEmpId((int) $data['designation_id']);

        $employee = Employee::create($data);
        $employee->load('designation');

        // Auto-create a login account (password null — employee sets it via /auth/setup-password).
        $userCreated = false;

        if (!empty($employee->email)) {
            $user = User::create([
                'name'           => $employee->emp_name,
                'email'          => $employee->email,
                'password'       => null,
                'role'           => $employee->designation?->role,
                'district_id'    => $employee->district_id,
                'block_id'       => $employee->block_id,
                'date_of_birth'  => $employee->emp_dob,
                'aadhaar_number' => $employee->aadhaar_number,
                'mobile_number'  => $employee->phone,
                'status'         => 'active',
            ]);

            $employee->update(['user_id' => $user->getKey()]);
            $userCreated = true;
        }

        return response()->json([
            'employee'     => $employee->fresh()->load('designation'),
            'user_created' => $userCreated,
        ], 201);
    }

    public function show(Employee $employee)
    {
        return response()->json($employee->load(['designation', 'district', 'block']));
    }

    public function update(Request $request, Employee $employee)
    {
        if (!$this->canManage($request->user(), $employee)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $empId  = (int) $employee->id;
        $userId = (int) $employee->user_id;

        $data = $request->validate([
            'emp_id'            => "sometimes|string|max:50|unique:employees,emp_id,{$empId}",
            'designation_id'    => 'sometimes|exists:designations,id',
            'emp_name'          => 'sometimes|string|max:255',
            'email'             => "nullable|email|max:255|unique:employees,email,{$empId}|unique:users,email,{$userId}",
            'emp_dob'           => 'nullable|date|before:today',
            'emp_doj'           => 'sometimes|date',
            'emp_qualification' => 'nullable|string|max:255',
            'aadhaar_number'    => 'nullable|digits:12',
            'salary'            => 'sometimes|numeric|min:0',
            'epf'               => 'sometimes|numeric|min:0',
            'deduction'         => 'nullable|numeric|min:0',
            'gross_salary'      => 'nullable|numeric|min:0',
            'net_salary'        => 'nullable|numeric|min:0',
            'district_id'       => 'nullable|integer',
            'block_id'          => 'nullable|integer',
            'bank_account'      => 'nullable|string|max:20',
            'ifsc_code'         => 'nullable|string|max:11',
            'phone'             => 'nullable|string|max:15',
            'status'            => 'nullable|in:active,inactive',
        ]);

        $employee->update($data);
        $employee->load('designation');

        // Sync linked user when relevant fields change.
        if ($userId > 0) {
            $user = User::find($userId);
            if ($user) {
                $sync = ['name' => $employee->emp_name];

                if (\array_key_exists('email', $data))         $sync['email']          = $employee->email;
                if (\array_key_exists('phone', $data))         $sync['mobile_number']  = $data['phone'];
                if (\array_key_exists('aadhaar_number', $data)) $sync['aadhaar_number'] = $data['aadhaar_number'];
                if (\array_key_exists('emp_dob', $data))       $sync['date_of_birth']  = $data['emp_dob'];
                if (\array_key_exists('district_id', $data))   $sync['district_id']    = $employee->district_id;
                if (\array_key_exists('block_id', $data))      $sync['block_id']       = $employee->block_id;
                if (\array_key_exists('status', $data))        $sync['status']         = $data['status'];
                if (\array_key_exists('designation_id', $data) && $employee->designation) {
                    $sync['role'] = $employee->designation->role;
                }

                $user->update($sync);
            }
        }

        return response()->json($employee);
    }

    public function destroy(Request $request, Employee $employee)
    {
        if (!$this->canManage($request->user(), $employee)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employee->delete();

        return response()->json(['message' => 'Employee deleted successfully.']);
    }

    /**
     * Returns whether this employee's login account has a password set.
     */
    public function credentialStatus(Request $request, Employee $employee)
    {
        if (!$this->canManage($request->user(), $employee)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $user = $employee->user_id ? User::find($employee->user_id) : null;

        return response()->json([
            'has_identity'   => (bool) $user,
            'has_credential' => !empty($user?->password),
        ]);
    }

    /**
     * Null out the employee's password so they must set a new one via
     * /auth/setup-password. Revokes all existing Sanctum tokens too.
     */
    public function resetPassword(Request $request, Employee $employee)
    {
        if (!$this->canManage($request->user(), $employee)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!$employee->user_id) {
            return response()->json(['message' => 'No login account linked to this employee.'], 422);
        }

        $user = User::find($employee->user_id);
        if (!$user) {
            return response()->json(['message' => 'Linked user account not found.'], 404);
        }

        $user->update(['password' => null]);
        $user->tokens()->delete();

        return response()->json(['message' => 'Password reset. Employee must set a new password via the setup-password page.']);
    }

    private function canManage(User $authUser, Employee $employee): bool
    {
        if ($authUser->role === 'state') return true;
        if ($authUser->role === 'district') {
            return (int) $employee->district_id === (int) $authUser->district_id;
        }
        if (\in_array($authUser->role, ['block', 'block_admin'])) {
            return (int) $employee->block_id === (int) $authUser->block_id;
        }
        return false;
    }
}
