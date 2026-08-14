<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\BlockCadre;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    /**
     * Authenticate via email + password, issue a Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', trim($data['email']))->first();

        if (!$user || !$user->password || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        if ($user->status === 'pending') {
            return response()->json(['message' => 'Your account is pending approval by an administrator.'], 403);
        }

        if ($user->status === 'inactive') {
            return response()->json(['message' => 'Your account has been deactivated. Contact an administrator.'], 403);
        }

        // Revoke previous tokens so only one session is active at a time.
        $user->tokens()->delete();

        $token = $user->createToken('api')->plainTextToken;

        return response()->json(['token' => $token]);
    }

    /**
     * Revoke the current Sanctum access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = [
            'id'             => $user->id,
            'name'           => $user->name,
            'email'          => $user->email,
            'mobile_number'  => $user->mobile_number,
            'aadhaar_number' => $user->aadhaar_number,
            'date_of_birth'  => $user->date_of_birth?->format('Y-m-d'),
            'role'           => $user->role,
            'status'         => $user->status,
            'district_id'    => $user->district_id,
            'block_id'       => $user->block_id,
        ];

        if ($user->role === 'block_cadre') {
            $data['cadre_type'] = BlockCadre::where('user_id', $user->id)->value('type');
        }

        return response()->json($data);
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'mobile_number'  => 'nullable|string|max:15',
            'aadhaar_number' => 'nullable|digits:12',
            'date_of_birth'  => 'nullable|date|before:today',
        ]);

        $update = array_filter($data, fn($v) => $v !== null && $v !== '');
        $user->update($update);

        return response()->json([
            'id'             => $user->id,
            'name'           => $user->name,
            'email'          => $user->email,
            'mobile_number'  => $user->mobile_number,
            'aadhaar_number' => $user->aadhaar_number,
            'date_of_birth'  => $user->date_of_birth?->format('Y-m-d'),
            'role'           => $user->role,
            'status'         => $user->status,
            'district_id'    => $user->district_id,
            'block_id'       => $user->block_id,
        ]);
    }
}
