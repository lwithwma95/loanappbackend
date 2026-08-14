<?php

namespace App\Http\Controllers\MIS;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Shg;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MISController extends Controller
{
    /* ── Read hierarchy ──────────────────────────────────────── */

    private function countSelects(): array
    {
        return [
            DB::raw('COUNT(DISTINCT CASE WHEN s.shg_status = 1 THEN s.shg_code END) as active_shg_count'),
            DB::raw('COUNT(DISTINCT CASE WHEN s.shg_status = 0 THEN s.shg_code END) as inactive_shg_count'),
            DB::raw('COUNT(DISTINCT CASE WHEN m.mem_status = 1 THEN m.mem_code END) as active_member_count'),
            DB::raw('COUNT(DISTINCT CASE WHEN m.mem_status = 0 THEN m.mem_code END) as inactive_member_count'),
        ];
    }

    public function districts(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'state') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Skip the 5-table aggregation join at district level — too expensive over the full state.
        // Counts are available when the admin drills into a specific district (block level).
        $rows = DB::table('district')
            ->orderBy('district_name')
            ->get(['district_id', 'district_name']);

        return response()->json($rows);
    }

    public function blocks(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = DB::table('block as b')
            ->leftJoin('gp as g',      'g.block_id',    '=', 'b.block_id')
            ->leftJoin('village as v', 'v.gp_id',       '=', 'g.gp_id')
            ->leftJoin('shg as s',     's.vill_id_new', '=', 'v.vill_id')
            ->leftJoin('member as m',  'm.shg_code',    '=', 's.shg_code')
            ->select(['b.block_id', 'b.block_name', 'b.district_id', ...$this->countSelects()])
            ->groupBy('b.block_id', 'b.block_name', 'b.district_id')
            ->orderBy('b.block_name');

        if (\in_array($user->role, ['block', 'block_admin'])) {
            // Block admins can only see their own assigned block
            $query->where('b.block_id', $user->block_id);
        } else {
            $districtId = $user->role === 'district'
                ? $user->district_id
                : $request->input('district_id');

            if (!$districtId) {
                return response()->json([]);
            }
            $query->where('b.district_id', $districtId);
        }

        return response()->json($query->get());
    }

    public function gps(Request $request): JsonResponse
    {
        $user = $request->user();

        $blockId = \in_array($user->role, ['block', 'block_admin'])
            ? $user->block_id
            : $request->input('block_id');

        if (!$blockId) {
            return response()->json([]);
        }

        $rows = DB::table('gp as g')
            ->leftJoin('village as v', 'v.gp_id',       '=', 'g.gp_id')
            ->leftJoin('shg as s',     's.vill_id_new', '=', 'v.vill_id')
            ->leftJoin('member as m',  'm.shg_code',    '=', 's.shg_code')
            ->where('g.block_id', $blockId)
            ->select(['g.gp_id', 'g.gp_name', 'g.block_id', ...$this->countSelects()])
            ->groupBy('g.gp_id', 'g.gp_name', 'g.block_id')
            ->orderBy('g.gp_name')
            ->get();

        return response()->json($rows);
    }

    public function villages(Request $request): JsonResponse
    {
        $user = $request->user();
        $gpId = (string) $request->input('gp_id', '');

        if (!$gpId) {
            return response()->json([]);
        }

        if (\in_array($user->role, ['block', 'block_admin'])) {
            $belongs = DB::table('gp')
                ->where('gp_id', $gpId)
                ->where('block_id', $user->block_id)
                ->exists();
            if (!$belongs) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $rows = DB::table('village as v')
            ->leftJoin('shg as s',    's.vill_id_new', '=', 'v.vill_id')
            ->leftJoin('member as m', 'm.shg_code',    '=', 's.shg_code')
            ->where('v.gp_id', $gpId)
            ->select(['v.vill_sn', 'v.vill_id', 'v.vill_name', 'v.gp_id', ...$this->countSelects()])
            ->groupBy('v.vill_sn', 'v.vill_id', 'v.vill_name', 'v.gp_id')
            ->orderBy('v.vill_name')
            ->get();

        return response()->json($rows);
    }

    public function shgs(Request $request): JsonResponse
    {
        $user   = $request->user();
        $villId = (string) $request->input('vill_id', '');

        if (!$villId) {
            return response()->json([]);
        }

        if (\in_array($user->role, ['block', 'block_admin'])) {
            $belongs = DB::table('village as v')
                ->join('gp as g', 'g.gp_id', '=', 'v.gp_id')
                ->where('v.vill_id', $villId)
                ->where('g.block_id', $user->block_id)
                ->exists();
            if (!$belongs) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $rows = DB::table('shg as s')
            ->leftJoin('member as m', 'm.shg_code', '=', 's.shg_code')
            ->where('s.vill_id_new', $villId)
            ->select([
                's.shg_code',
                's.shg_name',
                's.vill_id_new',
                's.shg_status',
                DB::raw('COUNT(DISTINCT CASE WHEN m.mem_status = 1 THEN m.mem_code END) as active_member_count'),
                DB::raw('COUNT(DISTINCT CASE WHEN m.mem_status = 0 THEN m.mem_code END) as inactive_member_count'),
            ])
            ->groupBy('s.shg_code', 's.shg_name', 's.vill_id_new', 's.shg_status')
            ->orderBy('s.shg_name')
            ->get();

        return response()->json($rows);
    }

    public function members(Request $request): JsonResponse
    {
        $user    = $request->user();
        $shgCode = (string) $request->input('shg_code', '');

        if (!$shgCode) {
            return response()->json([]);
        }

        if (\in_array($user->role, ['block', 'block_admin'])) {
            $belongs = DB::table('shg as s')
                ->join('village as v', 'v.vill_id', '=', 's.vill_id_new')
                ->join('gp as g',      'g.gp_id',   '=', 'v.gp_id')
                ->where('s.shg_code', $shgCode)
                ->where('g.block_id', $user->block_id)
                ->exists();
            if (!$belongs) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        return response()->json(
            Member::where('shg_code', $shgCode)
                ->orderBy('mem_name')
                ->get(['mem_code', 'mem_name', 'mem_guard_name', 'mem_mobile', 'mem_status', 'shg_code'])
        );
    }

    /* ── Update ──────────────────────────────────────────────── */

    public function updateMember(Request $request, string $memCode): JsonResponse
    {
        $user   = $request->user();
        $member = Member::where('mem_code', $memCode)->firstOrFail();

        if (\in_array($user->role, ['block', 'block_admin'])) {
            $belongs = DB::table('shg as s')
                ->join('village as v', 'v.vill_id', '=', 's.vill_id_new')
                ->join('gp as g',      'g.gp_id',   '=', 'v.gp_id')
                ->where('s.shg_code', $member->shg_code)
                ->where('g.block_id', $user->block_id)
                ->exists();
            if (!$belongs) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $data = $request->validate([
            'mem_mobile' => 'nullable|string|max:20',
        ]);

        $member->mem_mobile = $data['mem_mobile'] ?? null;
        $member->save();

        return response()->json(
            $member->only(['mem_code', 'mem_name', 'mem_guard_name', 'mem_mobile', 'mem_status', 'shg_code'])
        );
    }

    /* ── Toggle status ───────────────────────────────────────── */

    public function toggleShg(Request $request, string $shgCode): JsonResponse
    {
        $shg = Shg::where('shg_code', $shgCode)->firstOrFail();
        $shg->shg_status = $shg->shg_status ? 0 : 1;
        $shg->save();

        return response()->json(['shg_code' => $shg->shg_code, 'shg_status' => $shg->shg_status]);
    }

    public function toggleMember(Request $request, string $memCode): JsonResponse
    {
        $member = Member::where('mem_code', $memCode)->firstOrFail();
        $member->mem_status = $member->mem_status ? 0 : 1;
        $member->save();

        return response()->json(['mem_code' => $member->mem_code, 'mem_status' => $member->mem_status]);
    }
}
