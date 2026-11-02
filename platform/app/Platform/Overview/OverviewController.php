<?php

declare(strict_types=1);

namespace App\Platform\Overview;

use App\Http\Controllers\Controller;
use App\Platform\Access\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    public function __invoke(Request $request, ExecutiveOverviewService $overview, AccessService $access): Response|RedirectResponse
    {
        $userId = (int) $request->user('admin')->getAuthIdentifier();

        // Standalone assistants go straight to their work (legacy portal behaviour).
        if ($access->applications($userId) === [] && ! $access->isPlatformAdmin($userId) && $access->assistant($userId)['create']) {
            return redirect($access->assistantHome($userId));
        }

        return Inertia::render('Overview', $overview->forUser($userId, app()->getLocale()));
    }
}
