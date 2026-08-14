<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use Illuminate\Http\Request;

class DesignationController extends Controller
{
    public function index()
    {
        return response()->json(
            Designation::withCount('employees')->orderBy('role')->orderBy('designation_name')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'designation_name' => 'required|string|max:255',
            'role'             => 'required|in:state,district,block',
            'salary'           => 'required|numeric|min:0',
            'epf'              => 'required|numeric|min:0',
            'deduction'        => 'required|numeric|min:0',
            'total'            => 'required|numeric|min:0',
            'net_salary'       => 'required|numeric|min:0',
        ]);

        return response()->json(Designation::create($data), 201);
    }

    public function show(Designation $designation)
    {
        return response()->json($designation->load('employees'));
    }

    public function update(Request $request, Designation $designation)
    {
        $data = $request->validate([
            'designation_name' => 'sometimes|string|max:255',
            'role'             => 'sometimes|in:state,district,block',
            'salary'           => 'sometimes|numeric|min:0',
            'epf'              => 'sometimes|numeric|min:0',
            'deduction'        => 'sometimes|numeric|min:0',
            'total'            => 'sometimes|numeric|min:0',
            'net_salary'       => 'sometimes|numeric|min:0',
        ]);

        $designation->update($data);

        return response()->json($designation);
    }

    public function destroy(Designation $designation)
    {
        if ($designation->employees()->exists()) {
            return response()->json(
                ['message' => 'Cannot delete a designation that has employees assigned to it.'],
                422
            );
        }

        $designation->delete();

        return response()->json(['message' => 'Designation deleted successfully.']);
    }
}
