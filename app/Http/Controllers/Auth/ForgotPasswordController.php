<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    /**
     * Step 1: Verify identity via email + date of birth.
     * Returns a short-lived reset token on success.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'email'         => 'required|email',
            'date_of_birth' => 'required|date',
        ]);

        $user = User::where('email', trim($request->input('email')))->first();

        if (!$user) {
            return response()->json([
                'message' => 'No account found with this email address.',
            ], 404);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Your account is not active. Please contact an administrator.',
            ], 403);
        }

        if (!$user->date_of_birth || !$user->date_of_birth->isSameDay(Carbon::parse($request->input('date_of_birth')))) {
            return response()->json([
                'message' => 'Date of birth does not match our records.',
            ], 422);
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        return response()->json([
            'message' => 'Identity verified successfully.',
            'email'   => $user->email,
            'token'   => $token,
        ]);
    }

    /**
     * Step 2: Reset password using the token from step 1.
     */
    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'email'                 => 'required|email',
            'token'                 => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->input('email'))
            ->first();

        if (!$record || !Hash::check($request->input('token'), $record->token)) {
            return response()->json(['message' => 'Invalid or expired reset token.'], 422);
        }

        // Token valid for 60 minutes.
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->input('email'))->delete();
            return response()->json(['message' => 'Reset token has expired. Please start over.'], 422);
        }

        $user = User::where('email', $request->input('email'))->first();
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Store the new password; the 'hashed' cast handles bcrypt.
        $user->update(['password' => $request->input('password')]);

        // Revoke all existing Sanctum tokens so the user must log in fresh.
        $user->tokens()->delete();

        DB::table('password_reset_tokens')->where('email', $request->input('email'))->delete();

        return response()->json(['message' => 'Password reset successfully. You can now log in.']);
    }
}
