<?php

namespace App\Http\Controllers\Financial;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\District;
use App\Models\Gp;
use App\Models\Member;
use App\Models\Shg;
use App\Models\Village;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeoController extends Controller
{
    public function districts(): JsonResponse
    {
        return response()->json(
            District::orderBy('district_name')->get(['district_id', 'district_name'])
        );
    }

    public function blocks(Request $request): JsonResponse
    {
        $q = Block::orderBy('block_name');
        if ($request->filled('district_id')) {
            $q->where('district_id', $request->input('district_id'));
        }
        return response()->json($q->get(['block_id', 'block_name', 'district_id']));
    }

    public function gps(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->block_id) {
            return response()->json([]);
        }

        return response()->json(
            Gp::where('block_id', $user->block_id)
                ->orderBy('gp_name')
                ->get(['gp_id', 'gp_name'])
        );
    }

    public function villages(Request $request): JsonResponse
    {
        // Use string — $request->integer() truncates BIGINT on 32-bit PHP
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
}
