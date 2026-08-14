<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SetupPasswordController extends Controller
{
    /**
     * Public, self-service password setup — for admin-created accounts that
     * have never had a password set, or anyone who needs to set a new one.
     * Identity is verified via email + date of birth, then the password is
     * stored directly (the 'hashed' cast handles bcrypt automatically).
     */
    public function setup(Request $request)
    {
        $request->validate([
            'email'                 => 'required|email',
            'date_of_birth'         => 'required|date',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $user = User::where('email', trim($request->input('email')))->first();

        if (!$user) {
            return response()->json(['message' => 'No account found with this email address.'], 404);
        }

        if (!$user->date_of_birth || !$user->date_of_birth->isSameDay(Carbon::parse($request->input('date_of_birth')))) {
            return response()->json(['message' => 'Date of birth does not match our records.'], 422);
        }

        // Store the new password; the 'hashed' cast on the model handles bcrypt.
        $user->update(['password' => $request->input('password')]);

        // Revoke any existing tokens so the user must log in fresh.
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password set successfully. Please log in.',
        ], 201);
    }
}
