<?php

declare(strict_types=1);

namespace App\Platform\Overview;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    public function __invoke(Request $request, ExecutiveOverviewService $overview): Response
    {
        return Inertia::render('Overview', $overview->forUser((int) $request->user('admin')->getAuthIdentifier(), app()->getLocale()));
    }
}
