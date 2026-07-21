<?php

namespace App\Http\Controllers;

use App\Http\Requests\DriverAppUsageIndexRequest;
use App\Services\DriverAppUsageService;
use Inertia\Inertia;
use Inertia\Response;

class DriverAppUsageController extends Controller
{
    public function index(
        DriverAppUsageIndexRequest $request,
        DriverAppUsageService $usage,
    ): Response {
        return Inertia::render(
            'Admin/DriverApps',
            $usage->overview($request->validated(), $request->url()),
        );
    }
}
