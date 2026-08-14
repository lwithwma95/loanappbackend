<?php

namespace App\Http\Controllers\Financial;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberSearchController extends Controller
{
    private const BLOCK_ROLES = ['block', 'block_admin', 'block_cadre'];

    public function search(Request $request): JsonResponse
    {
        $q    = (string) $request->query('q', '');
        $user = $request->user();

        $query = Member::with(['shg.village.gp.block.district']);

        // Block-level users are locked to their own block
        if (in_array($user->role, self::BLOCK_ROLES) && $user->block_id) {
            $blockId = (int) $user->block_id;
            $query->whereIn('shg_code', function ($sub) use ($blockId) {
                $sub->select('shg.shg_code')
                    ->from('shg')
                    ->join('village', 'shg.vill_id_new', '=', 'village.vill_id')
                    ->join('gp', 'village.gp_id', '=', 'gp.gp_id')
                    ->where('gp.block_id', $blockId);
            });
        } elseif ($request->filled('block_id')) {
            $blockId = (int) $request->query('block_id');
            $query->whereIn('shg_code', function ($sub) use ($blockId) {
                $sub->select('shg.shg_code')
                    ->from('shg')
                    ->join('village', 'shg.vill_id_new', '=', 'village.vill_id')
                    ->join('gp', 'village.gp_id', '=', 'gp.gp_id')
                    ->where('gp.block_id', $blockId);
            });
        }

        if ($request->filled('shg_code')) {
            $query->where('shg_code', $request->query('shg_code'));
        }

        if ($q !== '') {
            $mode = (string) $request->query('mode', '');
            if ($mode === 'code') {
                $query->where('mem_code', 'like', "%{$q}%");
            } elseif ($mode === 'name') {
                $query->where('mem_name', 'like', "%{$q}%");
            } else {
                $query->where(function ($sub) use ($q) {
                    $sub->where('mem_code', 'like', "%{$q}%")
                        ->orWhere('mem_name', 'like', "%{$q}%");
                });
            }
        }

        $members = $query
            ->orderBy('mem_name')
            ->limit(30)
            ->get()
            ->map(fn ($m) => [
                'mem_code'       => $m->mem_code,
                'mem_name'       => $m->mem_name,
                'mem_guard_name' => $m->mem_guard_name,
                'mem_mobile'     => $m->mem_mobile,
                'shg_code'       => $m->shg_code,
                'shg_name'       => $m->shg?->shg_name,
                'village_name'   => $m->shg?->village?->vill_name,
                'gp_name'        => $m->shg?->village?->gp?->gp_name,
                'block_name'     => $m->shg?->village?->gp?->block?->block_name,
                'district_name'  => $m->shg?->village?->gp?->block?->district?->district_name,
                'district_id'    => $m->shg?->village?->gp?->block?->district?->district_id,
            ]);

        return response()->json($members);
    }
}
