<?php

namespace App\Http\Controllers\Financial;

use App\Http\Controllers\Controller;
use App\Models\LoanScheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanSchemeController extends Controller
{
    private const ALLOWED_ROLES = ['state', 'district', 'block_admin'];

    public function index(): JsonResponse
    {
        return response()->json(
            LoanScheme::orderBy('bank_name')->orderBy('scheme_name')->get(['id', 'bank_name', 'acronym_name', 'scheme_name'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        if (!in_array($request->user()->role, self::ALLOWED_ROLES)) {
            return response()->json(['message' => 'Insufficient permissions.'], 403);
        }

        $data = $request->validate([
            'bank_name'    => 'required|string|max:200',
            'acronym_name' => 'required|string|max:50',
            'scheme_name'  => 'required|string|max:200',
        ]);

        $scheme = LoanScheme::create($data);
        return response()->json($scheme, 201);
    }

    public function update(Request $request, LoanScheme $loanScheme): JsonResponse
    {
        if (!in_array($request->user()->role, self::ALLOWED_ROLES)) {
            return response()->json(['message' => 'Insufficient permissions.'], 403);
        }

        $data = $request->validate([
            'bank_name'    => 'sometimes|string|max:200',
            'acronym_name' => 'sometimes|string|max:50',
            'scheme_name'  => 'sometimes|string|max:200',
        ]);

        $loanScheme->update($data);
        return response()->json($loanScheme);
    }

    public function destroy(Request $request, LoanScheme $loanScheme): JsonResponse
    {
        if (!in_array($request->user()->role, self::ALLOWED_ROLES)) {
            return response()->json(['message' => 'Insufficient permissions.'], 403);
        }

        $loanScheme->delete();
        return response()->json(['message' => 'Scheme deleted.']);
    }
}
