<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\BlockCadre;
use App\Models\Designation;
use App\Models\District;
use App\Models\Gp;
use App\Models\Member;
use App\Models\Shg;
use App\Models\Village;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicGeoController extends Controller
{
    public function designations(Request $request): JsonResponse
    {
        $q = Designation::orderBy('designation_name');
        if ($request->filled('role')) {
            $q->where('role', $request->input('role'));
        }
        return response()->json($q->get(['id', 'designation_name']));
    }

    public function districts(): JsonResponse
    {
        return response()->json(
            District::orderBy('district_name')
                ->get(['district_id', 'district_name'])
        );
    }

    public function blocks(Request $request): JsonResponse
    {
        $districtId = $request->input('district_id');
        $q = Block::orderBy('block_name');
        if ($districtId) {
            $q->where('district_id', $districtId);
        }
        return response()->json($q->get(['block_id', 'block_name', 'district_id']));
    }

    public function gps(Request $request): JsonResponse
    {
        $blockId = $request->input('block_id');
        if (!$blockId) {
            return response()->json([]);
        }
        return response()->json(
            Gp::where('block_id', $blockId)
                ->orderBy('gp_name')
                ->get(['gp_id', 'gp_name'])
        );
    }

    public function villages(Request $request): JsonResponse
    {
        $gpId = (string) $request->input('gp_id', '');
        return response()->json(
            Village::where('gp_id', $gpId)
                ->orderBy('vill_name')
                ->get(['vill_id', 'vill_name'])
        );
    }

    public function shgs(Request $request): JsonResponse
    {
        $villId = (string) $request->input('vill_id', '');
        return response()->json(
            Shg::where('vill_id_new', $villId)
                ->orderBy('shg_name')
                ->get(['shg_code', 'shg_name'])
        );
    }

    public function members(Request $request): JsonResponse
    {
        $shgCode = (string) $request->query('shg_code', '');
        return response()->json(
            Member::where('shg_code', $shgCode)
                ->orderBy('mem_name')
                ->get(['mem_code', 'mem_name', 'mem_guard_name', 'mem_mobile'])
        );
    }

    /**
     * Used by the Block Cadre self-registration form to verify a Member Code
     * exists before letting the applicant fill in the rest of the form —
     * a cadre worker must already be represented as an SHG member.
     */
    public function lookupMember(Request $request): JsonResponse
    {
        $memCode = trim((string) $request->query('mem_code', ''));
        if ($memCode === '') {
            return response()->json(['found' => false]);
        }

        $member = Member::where('mem_code', $memCode)
            ->with('shg.village.gp.block.district')
            ->first(['mem_code', 'mem_name', 'mem_mobile', 'shg_code']);

        if (!$member) {
            return response()->json(['found' => false]);
        }

        $block    = $member->shg?->village?->gp?->block;
        $district = $block?->district;

        return response()->json([
            'found'          => true,
            'mem_code'       => $member->mem_code,
            'mem_name'       => $member->mem_name,
            'mem_mobile'     => $member->mem_mobile,
            'already_linked' => BlockCadre::where('cadre_code', $memCode)->exists(),
            'district_id'    => $district?->district_id,
            'district_name'  => $district?->district_name,
            'block_id'       => $block?->block_id,
            'block_name'     => $block?->block_name,
        ]);
    }
}
