<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MissionType;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

class MissionTypeLookupController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $items = MissionType::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']);
        } catch (QueryException) {
            $items = collect([
                ['id' => 1, 'name' => 'نقل موظفين'],
                ['id' => 2, 'name' => 'نقل معدات'],
                ['id' => 3, 'name' => 'مهمة ميدانية'],
                ['id' => 4, 'name' => 'طوارئ'],
                ['id' => 5, 'name' => 'صيانة'],
            ]);
        }

        return response()->json(['mission_types' => $items]);
    }
}

