<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MissionLocation;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

class LocationLookupController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $all = MissionLocation::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'category']);

            $origins = $all
                ->whereIn('category', ['origin', 'both'])
                ->values();
            $destinations = $all
                ->whereIn('category', ['destination', 'both'])
                ->values();
        } catch (QueryException) {
            $fallback = collect([
                ['id' => 1, 'name' => 'مجمّع البلدية الرئيس', 'category' => 'both'],
                ['id' => 2, 'name' => 'المبنى الأثري', 'category' => 'both'],
                ['id' => 3, 'name' => 'مبنى السرايا', 'category' => 'both'],
                ['id' => 4, 'name' => 'مبنى أبو أيوب الأنصاري', 'category' => 'both'],
                ['id' => 5, 'name' => 'مبنى الكهرباء والمخازن', 'category' => 'both'],
                ['id' => 6, 'name' => 'كراج البلدية والطوارئ', 'category' => 'both'],
                ['id' => 7, 'name' => 'مقر التفتيش والمتابعة', 'category' => 'both'],
                ['id' => 8, 'name' => 'محكمة صلح بلدية غزة', 'category' => 'both'],
                ['id' => 9, 'name' => 'المسلخ البلدي', 'category' => 'both'],
                ['id' => 10, 'name' => 'المراكز المجتمعية الفرعية', 'category' => 'both'],
                ['id' => 11, 'name' => 'مكتب الصحة والبيئة – الشجاعية', 'category' => 'both'],
            ]);

            $origins = $fallback;
            $destinations = $fallback;
        }

        return response()->json([
            'origins' => $origins,
            'destinations' => $destinations,
        ]);
    }
}
