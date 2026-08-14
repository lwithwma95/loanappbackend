<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\BlockCadre;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $role       = $request->input('role');
        $isCadre    = $role === 'block_cadre';
        $isEmployee = \in_array($role, ['block', 'district', 'state']);
        $needsBlock = \in_array($role, ['block_cadre', 'block']);

        $base = [
            'role'           => 'required|in:block_cadre,block,district,state',
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|unique:users,email',
            'mobile_number'  => 'nullable|string|max:15',
            'aadhaar_number' => 'required|digits:12|unique:users,aadhaar_number',
            'date_of_birth'  => 'required|date|before:today',
            'password'       => 'required|string|min:8|confirmed',
        ];

        $districtRule = $role === 'state'
            ? ['district_id' => 'nullable|integer|exists:district,district_id']
            : ['district_id' => 'required|integer|exists:district,district_id'];

        $blockRule = $needsBlock
            ? ['block_id' => 'required|integer|exists:block,block_id']
            : ['block_id' => 'nullable|integer|exists:block,block_id'];

        $employeeRule = $isEmployee
            ? ['designation_id' => 'required|integer|exists:designations,id']
            : [];

        $cadreRule = $isCadre
            ? [
                'cadre_type' => 'required|string|in:JS,KS,PS,BS,CRP-EP,BimaS,YS,BM',
                'cadre_code' => 'required|string|exists:member,mem_code|unique:block_cadres,cadre_code',
                'consent'    => 'required|accepted',
            ]
            : [];

        $validated = $request->validate([...$base, ...$districtRule, ...$blockRule, ...$employeeRule, ...$cadreRule]);

        // Store user with hashed password — account stays pending until an admin approves.
        $user = User::create([
            'name'           => $validated['name'],
            'email'          => $validated['email'],
            'password'       => $validated['password'], // cast to hashed automatically
            'mobile_number'  => $validated['mobile_number'] ?? null,
            'aadhaar_number' => $validated['aadhaar_number'],
            'date_of_birth'  => $validated['date_of_birth'],
            'role'           => $validated['role'],
            'district_id'    => $validated['district_id'] ?? null,
            'block_id'       => $validated['block_id'] ?? null,
            'status'         => 'pending',
        ]);

        if ($isCadre) {
            BlockCadre::create([
                'cadre_code'       => $validated['cadre_code'],
                'type'             => $validated['cadre_type'],
                'name'             => $validated['name'],
                'email'            => $validated['email'],
                'mobile'           => $validated['mobile_number'] ?? null,
                'aadhaar_number'   => $validated['aadhaar_number'],
                'date_of_birth'    => $validated['date_of_birth'],
                'consent_given_at' => now(),
                'district_id'      => $validated['district_id'],
                'block_id'         => $validated['block_id'],
                'user_id'          => $user->id,
                'role'             => 'block_cadre',
                'status'           => 'inactive',
            ]);
        }

        if ($isEmployee) {
            Employee::create([
                'emp_name'       => $validated['name'],
                'emp_id'         => Employee::generateEmpId((int) $validated['designation_id']),
                'designation_id' => $validated['designation_id'],
                'user_id'        => $user->id,
                'district_id'    => $validated['district_id'] ?? null,
                'block_id'       => $validated['block_id'] ?? null,
                'email'          => $validated['email'],
                'phone'          => $validated['mobile_number'] ?? null,
                'emp_doj'        => Carbon::today()->toDateString(),
                'salary'         => 0,
                'epf'            => 0,
                'deduction'      => 0,
                'gross_salary'   => 0,
                'net_salary'     => 0,
                'status'         => 'inactive',
            ]);
        }

        return response()->json([
            'message' => 'Registration submitted. Your account is pending approval by an administrator.',
        ], 201);
    }
}
