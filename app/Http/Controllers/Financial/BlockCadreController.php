<?php

namespace App\Http\Controllers\Financial;

use App\Http\Controllers\Controller;
use App\Models\BlockCadre;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlockCadreController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = BlockCadre::with(['block', 'district']);

        $user = $request->user();
        if (\in_array($user->role, ['block', 'block_admin', 'block_cadre']) && $user->block_id) {
            $query->where('block_id', $user->block_id);
        }

        return response()->json($query->orderBy('name')->get());
    }

    /**
     * Clients read cadre_code straight off a member record, where it originates as a
     * BIGINT and can arrive as a JSON number rather than a string. Normalise it so the
     * `string` rule below judges the value, not the JSON type it happened to travel in.
     */
    private function normalizeCadreCode(Request $request): void
    {
        $code = $request->input('cadre_code');

        if (\is_int($code) || \is_float($code)) {
            $request->merge(['cadre_code' => (string) $code]);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $this->normalizeCadreCode($request);

        $data = $request->validate([
            'cadre_code'     => 'required|string|unique:block_cadres,cadre_code|exists:member,mem_code',
            'email'          => 'required|email|unique:block_cadres,email|unique:users,email',
            'mobile_number'  => 'nullable|string|max:15',
            'aadhaar_number' => 'nullable|digits:12',
            'date_of_birth'  => 'nullable|date|before:today',
            'type'           => 'nullable|string|max:20',
            'block_id'       => 'nullable|integer',
            'district_id'    => 'nullable|integer',
        ]);

        $authUser = $request->user();

        $member = Member::find($data['cadre_code']);
        $data['name']   = $member?->mem_name ?? $data['cadre_code'];
        $data['mobile'] = $member?->mem_mobile;
        $data['role']   = 'block_cadre';

        // Create a login account (password null — cadre sets it via /auth/setup-password).
        $user = User::create([
            'name'           => $data['name'],
            'email'          => $data['email'],
            'password'       => null,
            'mobile_number'  => $data['mobile_number'] ?? null,
            'aadhaar_number' => $data['aadhaar_number'] ?? null,
            'date_of_birth'  => $data['date_of_birth'] ?? null,
            'role'           => 'block_cadre',
            'block_id'       => $authUser->block_id,
            'district_id'    => $authUser->district_id,
            'status'         => 'active',
        ]);

        $data['user_id'] = $user->getKey();
        $cadre = BlockCadre::create($data);
        $cadre->load(['block', 'district', 'user']);

        return response()->json($cadre, 201);
    }

    public function show(BlockCadre $blockCadre): JsonResponse
    {
        $blockCadre->load(['block', 'district', 'user', 'member']);
        return response()->json($blockCadre);
    }

    public function update(Request $request, BlockCadre $blockCadre): JsonResponse
    {
        $this->normalizeCadreCode($request);

        $data = $request->validate([
            'block_id'       => 'nullable|integer',
            'district_id'    => 'nullable|integer',
            'role'           => 'nullable|in:block_cadre,block_admin',
            'status'         => 'nullable|in:active,inactive',
            'cadre_code'     => 'nullable|string|exists:member,mem_code',
            'name'           => 'nullable|string|max:255',
            'mobile'         => 'nullable|string|max:20',
            'type'           => 'nullable|string|in:JS,KS,PS,BS,CRP-EP,BimaS,YS,BM',
            'aadhaar_number' => 'nullable|digits:12',
            'date_of_birth'  => 'nullable|date|before:today',
        ]);

        $blockCadre->update($data);

        // Sync fields to the linked user account.
        if ($blockCadre->user_id) {
            $userSync = [];
            if (isset($data['role']))                          $userSync['role']           = $data['role'];
            if (isset($data['status']))                        $userSync['status']         = $data['status'];
            if (\array_key_exists('aadhaar_number', $data))   $userSync['aadhaar_number'] = $data['aadhaar_number'];
            if (\array_key_exists('date_of_birth', $data))    $userSync['date_of_birth']  = $data['date_of_birth'];
            if ($userSync) $blockCadre->user?->update($userSync);
        }

        return response()->json($blockCadre->fresh(['block', 'district']));
    }

    public function destroy(BlockCadre $blockCadre): JsonResponse
    {
        if (!\in_array(request()->user()->role, ['block', 'block_admin'])) {
            return response()->json(['message' => 'Insufficient permissions.'], 403);
        }

        // Revoke all tokens before deleting the user account.
        $blockCadre->user?->tokens()->delete();
        $blockCadre->user?->delete();
        $blockCadre->delete();

        return response()->json(['message' => 'Cadre deleted.']);
    }
}
