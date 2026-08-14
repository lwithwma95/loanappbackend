<?php

namespace App\Http\Controllers\MIS;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\District;
use App\Models\Gp;
use App\Models\Member;
use App\Models\Shg;
use App\Models\Village;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Mapping / remapping of the geo hierarchy:
 *   member.shg_code     = shg.shg_code
 *   shg.vill_id_new      = village.vill_id
 *   village.gp_id        = gp.gp_id
 *   gp.block_id          = block.block_id
 *   block.district_id    = district.district_id
 *
 * Plus creation of new Member / SHG / Village / GP / Block records
 * (District is not creatable here — it's the top of the hierarchy).
 */
class MappingController extends Controller
{
    /* ── Role guards ─────────────────────────────────────────── */

    // Block creation and all remap operations are state/district only —
    // these restructure the official hierarchy, unlike day-to-day
    // Village/SHG/Member creation which stays open to block-tier roles.
    private function assertElevated(Request $request): ?JsonResponse
    {
        if (!\in_array($request->user()->role, ['state', 'district'])) {
            return response()->json(['message' => 'Only State and District admins can perform this action.'], 403);
        }
        return null;
    }

    // For district-role users, the entity's *current* and *new* district must
    // both equal their own — they can reorganize within their own district,
    // not move things in/out of it. State role is unrestricted.
    private function assertDistrictScope(Request $request, ?int $currentDistrictId, ?int $newDistrictId = null): ?JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'district') {
            return null;
        }
        if ($currentDistrictId !== (int) $user->district_id) {
            return response()->json(['message' => 'That record is outside your district.'], 403);
        }
        if ($newDistrictId !== null && $newDistrictId !== (int) $user->district_id) {
            return response()->json(['message' => 'You can only remap within your own district.'], 403);
        }
        return null;
    }

    private function districtIdOfBlock(int $blockId): ?int
    {
        return DB::table('block')->where('block_id', $blockId)->value('district_id');
    }

    private function districtIdOfGp(string $gpId): ?int
    {
        return DB::table('gp as g')->join('block as b', 'b.block_id', '=', 'g.block_id')
            ->where('g.gp_id', $gpId)->value('b.district_id');
    }

    private function districtIdOfVillage(string $villId): ?int
    {
        return DB::table('village as v')
            ->join('gp as g', 'g.gp_id', '=', 'v.gp_id')
            ->join('block as b', 'b.block_id', '=', 'g.block_id')
            ->where('v.vill_id', $villId)->value('b.district_id');
    }

    private function districtIdOfShg(string $shgCode): ?int
    {
        return DB::table('shg as s')
            ->join('village as v', 'v.vill_id', '=', 's.vill_id_new')
            ->join('gp as g', 'g.gp_id', '=', 'v.gp_id')
            ->join('block as b', 'b.block_id', '=', 'g.block_id')
            ->where('s.shg_code', $shgCode)->value('b.district_id');
    }

    private function districtIdOfMember(string $memCode): ?int
    {
        return DB::table('member as m')
            ->join('shg as s', 's.shg_code', '=', 'm.shg_code')
            ->join('village as v', 'v.vill_id', '=', 's.vill_id_new')
            ->join('gp as g', 'g.gp_id', '=', 'v.gp_id')
            ->join('block as b', 'b.block_id', '=', 'g.block_id')
            ->where('m.mem_code', $memCode)->value('b.district_id');
    }

    private function blockIdOfGp(string $gpId): ?int
    {
        return DB::table('gp')->where('gp_id', $gpId)->value('block_id');
    }

    private function blockIdOfVillage(string $villId): ?int
    {
        return DB::table('village as v')
            ->join('gp as g', 'g.gp_id', '=', 'v.gp_id')
            ->where('v.vill_id', $villId)->value('g.block_id');
    }

    private function blockIdOfShg(string $shgCode): ?int
    {
        return DB::table('shg as s')
            ->join('village as v', 'v.vill_id', '=', 's.vill_id_new')
            ->join('gp as g', 'g.gp_id', '=', 'v.gp_id')
            ->where('s.shg_code', $shgCode)->value('g.block_id');
    }

    // Ensures block/block_admin can only remap within their assigned block.
    private function assertBlockScope(Request $request, ?int $currentBlockId, ?int $newBlockId = null): ?JsonResponse
    {
        $user = $request->user();
        if (!\in_array($user->role, ['block', 'block_admin'])) {
            return null;
        }
        if ($currentBlockId !== (int) $user->block_id) {
            return response()->json(['message' => 'That record is outside your block.'], 403);
        }
        if ($newBlockId !== null && $newBlockId !== (int) $user->block_id) {
            return response()->json(['message' => 'You can only remap within your own block.'], 403);
        }
        return null;
    }

    /* ── Create ──────────────────────────────────────────────── */

    public function storeDistrict(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'state') {
            return response()->json(['message' => 'Only State admin can create districts.'], 403);
        }

        $data = $request->validate([
            'district_id'   => 'nullable|integer|unique:district,district_id',
            'district_name' => 'required|string|max:255|unique:district,district_name',
        ]);

        if (empty($data['district_id'])) {
            unset($data['district_id']);
        }

        $district = District::create($data);

        return response()->json($district, 201);
    }

    public function storeBlock(Request $request): JsonResponse
    {
        if ($err = $this->assertElevated($request)) return $err;

        $data = $request->validate([
            'block_id'    => 'nullable|integer|unique:block,block_id',
            'block_name'  => 'required|string|max:255',
            'district_id' => 'required|integer|exists:district,district_id',
        ]);

        // District admins can only add blocks to their own district.
        if ($request->user()->role === 'district') {
            $data['district_id'] = (int) $request->user()->district_id;
        }

        if (empty($data['block_id'])) {
            unset($data['block_id']);
        }

        $block = Block::create($data);

        return response()->json($block, 201);
    }

    public function storeGp(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!\in_array($user->role, ['state', 'district', 'block', 'block_admin'])) {
            return response()->json(['message' => 'Insufficient permissions to create a GP.'], 403);
        }

        $data = $request->validate([
            'gp_id'    => 'required|string|max:50|unique:gp,gp_id',
            'gp_name'  => 'required|string|max:255',
            'block_id' => 'required|integer|exists:block,block_id',
        ]);

        // Block-level roles can only add GPs to their own assigned block.
        if (\in_array($user->role, ['block', 'block_admin'])) {
            if ((int) $data['block_id'] !== (int) $user->block_id) {
                return response()->json(['message' => 'You can only add GPs to your own block.'], 403);
            }
        }

        // District-level roles are restricted to their own district.
        if ($user->role === 'district') {
            if ($err = $this->assertDistrictScope($request, $this->districtIdOfBlock((int) $data['block_id']))) return $err;
        }

        $gp = Gp::create($data);

        return response()->json($gp, 201);
    }

    public function storeVillage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vill_id'   => 'required|string|max:50',
            'vill_name' => 'required|string|max:255',
            'gp_id'     => 'required|string|exists:gp,gp_id',
        ]);

        $village = Village::create($data);

        return response()->json($village, 201);
    }

    public function storeShg(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shg_code'    => 'required|string|max:50|unique:shg,shg_code',
            'shg_name'    => 'required|string|max:255',
            'vill_id_new' => 'required|string|exists:village,vill_id',
        ]);

        $shg = Shg::create([...$data, 'shg_status' => 1]);

        return response()->json($shg, 201);
    }

    public function storeMember(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mem_code'       => 'required|string|max:50|unique:member,mem_code',
            'mem_name'       => 'required|string|max:255',
            'mem_guard_name' => 'nullable|string|max:255',
            'mem_mobile'     => 'nullable|string|max:20',
            'shg_code'       => 'required|string|exists:shg,shg_code',
        ]);

        $member = Member::create([...$data, 'mem_status' => 1]);

        return response()->json($member, 201);
    }

    /* ── Remap ───────────────────────────────────────────────── */

    public function remapBlock(Request $request, int $blockId): JsonResponse
    {
        // Moving a block to a different district is the most structurally
        // significant change — reserved for state admin.
        if ($request->user()->role !== 'state') {
            return response()->json(['message' => 'Only State admin can reassign a block to a different district.'], 403);
        }

        $data  = $request->validate(['district_id' => 'required|integer|exists:district,district_id']);
        $block = Block::findOrFail($blockId);
        $block->update(['district_id' => $data['district_id']]);

        return response()->json($block->fresh()->load('district'));
    }

    public function remapGp(Request $request, string $gpId): JsonResponse
    {
        $user = $request->user();
        if (!\in_array($user->role, ['state', 'district', 'block', 'block_admin'])) {
            return response()->json(['message' => 'Insufficient permissions.'], 403);
        }

        $data = $request->validate(['block_id' => 'required|integer|exists:block,block_id']);
        $gp   = Gp::findOrFail($gpId);

        if ($user->role === 'district') {
            if ($err = $this->assertDistrictScope(
                $request,
                $this->districtIdOfBlock((int) $gp->block_id),
                $this->districtIdOfBlock((int) $data['block_id'])
            )) return $err;
        }

        if (\in_array($user->role, ['block', 'block_admin'])) {
            if ($err = $this->assertBlockScope(
                $request,
                (int) $gp->block_id,
                (int) $data['block_id']
            )) return $err;
        }

        $gp->update(['block_id' => $data['block_id']]);

        return response()->json($gp->fresh()->load('block'));
    }

    public function remapVillage(Request $request, string $villId): JsonResponse
    {
        $user = $request->user();
        if (!\in_array($user->role, ['state', 'district', 'block', 'block_admin'])) {
            return response()->json(['message' => 'Insufficient permissions.'], 403);
        }

        $data    = $request->validate(['gp_id' => 'required|string|exists:gp,gp_id']);
        $village = Village::where('vill_id', $villId)->firstOrFail();

        if ($user->role === 'district') {
            if ($err = $this->assertDistrictScope(
                $request,
                $this->districtIdOfGp((string) $village->gp_id),
                $this->districtIdOfGp((string) $data['gp_id'])
            )) return $err;
        }

        if (\in_array($user->role, ['block', 'block_admin'])) {
            if ($err = $this->assertBlockScope(
                $request,
                $this->blockIdOfGp((string) $village->gp_id),
                $this->blockIdOfGp((string) $data['gp_id'])
            )) return $err;
        }

        $village->update(['gp_id' => $data['gp_id']]);

        return response()->json($village->fresh()->load('gp'));
    }

    public function remapShg(Request $request, string $shgCode): JsonResponse
    {
        $user = $request->user();
        if (!\in_array($user->role, ['state', 'district', 'block', 'block_admin'])) {
            return response()->json(['message' => 'Insufficient permissions.'], 403);
        }

        $data = $request->validate(['vill_id_new' => 'required|string|exists:village,vill_id']);
        $shg  = Shg::where('shg_code', $shgCode)->firstOrFail();

        if ($user->role === 'district') {
            if ($err = $this->assertDistrictScope(
                $request,
                $this->districtIdOfVillage((string) $shg->vill_id_new),
                $this->districtIdOfVillage((string) $data['vill_id_new'])
            )) return $err;
        }

        if (\in_array($user->role, ['block', 'block_admin'])) {
            if ($err = $this->assertBlockScope(
                $request,
                $this->blockIdOfVillage((string) $shg->vill_id_new),
                $this->blockIdOfVillage((string) $data['vill_id_new'])
            )) return $err;
        }

        $shg->update(['vill_id_new' => $data['vill_id_new']]);

        return response()->json($shg->fresh()->load('village'));
    }

    public function remapMember(Request $request, string $memCode): JsonResponse
    {
        $user = $request->user();
        if (!\in_array($user->role, ['state', 'district', 'block', 'block_admin'])) {
            return response()->json(['message' => 'Insufficient permissions.'], 403);
        }

        $data   = $request->validate(['shg_code' => 'required|string|exists:shg,shg_code']);
        $member = Member::where('mem_code', $memCode)->firstOrFail();

        if ($user->role === 'district') {
            if ($err = $this->assertDistrictScope(
                $request,
                $this->districtIdOfShg((string) $member->shg_code),
                $this->districtIdOfShg((string) $data['shg_code'])
            )) return $err;
        }

        if (\in_array($user->role, ['block', 'block_admin'])) {
            if ($err = $this->assertBlockScope(
                $request,
                $this->blockIdOfShg((string) $member->shg_code),
                $this->blockIdOfShg((string) $data['shg_code'])
            )) return $err;
        }

        $member->update(['shg_code' => $data['shg_code']]);

        return response()->json($member->fresh()->load('shg'));
    }
}
