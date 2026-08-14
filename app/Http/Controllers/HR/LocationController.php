<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\District;

class LocationController extends Controller
{
    public function districts()
    {
        return response()->json(District::query()->ordered()->get());
    }

    public function blocks()
    {
        return response()->json(Block::query()->ordered()->get());
    }
}
