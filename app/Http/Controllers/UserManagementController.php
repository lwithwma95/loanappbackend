<?php

namespace App\Http\Controllers;

use App\Models\BlockCadre;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    /**
     * Approval chain:
     *  block_cadre → approved by block/block_admin (same block)
     *  block       → approved by block/block_admin (same block) OR district (same district)
     *  district    → approved by state
     *  state       → approved by an existing state admin (peer approval)
     */
    public function pending(Request $request): JsonResponse
    {
        $me = $request->user();

        $query = User::with(['blockCadre', 'employee'])
            ->where('status', 'pending');

        if (in_array($me->role, ['block_admin', 'block'])) {
            $query->whereIn('role', ['block_cadre', 'block'])
                  ->where('block_id', $me->block_id);
        } elseif ($me->role === 'district') {
            $query->whereIn('role', ['block', 'block_admin'])
                  ->where('district_id', $me->district_id);
        } elseif ($me->role === 'state') {
            $query->whereIn('role', ['district', 'state']);
        } else {
            return response()->json([]);
        }

        return response()->json(
            $query->orderByDesc('created_at')->get([
                'id', 'name', 'email', 'mobile_number', 'role',
                'status', 'district_id', 'block_id', 'created_at',
            ])
        );
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $me   = $request->user();
        $user = User::findOrFail($id);

        if (!$this->canApprove($me, $user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user->update(['status' => 'active']);

        // Activate linked BlockCadre record
        if ($user->role === 'block_cadre') {
            BlockCadre::where('user_id', $user->id)->update(['status' => 'active']);
        }

        // Activate linked Employee record
        if (in_array($user->role, ['block', 'block_admin', 'district', 'state'])) {
            Employee::where('user_id', $user->id)->update(['status' => 'active']);
        }

        return response()->json(['message' => 'User approved.', 'user' => $user]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $me   = $request->user();
        $user = User::findOrFail($id);

        if (!$this->canApprove($me, $user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate(['reason' => 'nullable|string|max:500']);

        $user->update(['status' => 'inactive']);

        return response()->json(['message' => 'User rejected.']);
    }

    /**
     * Returns whether this user has a password set.
     * Replaces the old Keycloak credential-status check.
     */
    public function credentialStatus(Request $request, int $id): JsonResponse
    {
        $me   = $request->user();
        $user = User::findOrFail($id);

        if (!$this->canManageUser($me, $user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'has_identity'   => true,
            'has_credential' => !empty($user->password),
        ]);
    }

    /**
     * Admin sets the user's password directly so the account is usable immediately.
     */
    public function setPassword(Request $request, int $id): JsonResponse
    {
        $me   = $request->user();
        $user = User::findOrFail($id);

        if (!$this->canManageUser($me, $user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $user->update(['password' => $data['password']]);

        // Revoke existing tokens so the user must log in with the new password.
        $user->tokens()->delete();

        return response()->json(['message' => 'Password set successfully.']);
    }

    public function all(Request $request): JsonResponse
    {
        $me = $request->user();
        if ($me->role !== 'state') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = User::with(['blockCadre', 'employee'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        return response()->json($query->get([
            'id', 'name', 'email', 'mobile_number', 'role',
            'status', 'district_id', 'block_id', 'created_at',
        ]));
    }

    private function canApprove(User $approver, User $target): bool
    {
        $approverIsBlock = in_array($approver->role, ['block_admin', 'block']);
        $targetIsBlock   = in_array($target->role,   ['block_admin', 'block']);

        if ($approverIsBlock) {
            return in_array($target->role, ['block_cadre', 'block'])
                && $target->block_id === $approver->block_id;
        }

        return match ($approver->role) {
            'district' => $targetIsBlock
                       && $target->district_id === $approver->district_id,
            'state'    => in_array($target->role, ['district', 'state']),
            default    => false,
        };
    }

    private function canManageUser(User $approver, User $target): bool
    {
        if ($approver->role === 'state') {
            return true;
        }
        if ($approver->role === 'district') {
            return (int) $target->district_id === (int) $approver->district_id;
        }
        if (\in_array($approver->role, ['block', 'block_admin'])) {
            return (int) $target->block_id === (int) $approver->block_id;
        }

        return false;
    }
}
