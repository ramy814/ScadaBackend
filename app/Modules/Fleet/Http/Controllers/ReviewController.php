<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Http\Requests\DecisionRequest;
use App\Modules\Fleet\Http\Requests\ReviewQueueRequest;
use App\Modules\Fleet\Models\Trip;
use App\Modules\Fleet\Services\FleetAccessService;
use App\Modules\Fleet\Services\TripDetailService;
use App\Modules\Fleet\Services\TripReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function __construct(
        private readonly TripReviewService $reviews,
        private readonly TripDetailService $details,
        private readonly FleetAccessService $access,
    ) {}

    public function index(ReviewQueueRequest $request): Response
    {
        return Inertia::render('Fleet/Reviews', [
            ...$this->reviews->queue($this->userId($request), $request->validated()),
            'tripBase' => url('/fleet/trips'),
        ]);
    }

    /** Full page, or JSON for the review drawer (same payload). */
    public function show(Request $request, Trip $trip): Response|JsonResponse
    {
        abort_unless($this->access->canView($this->userId($request), $trip), 403, __('fleet.errors.scope'));
        $payload = $this->details->present($trip, $this->userId($request));

        return $request->wantsJson() && ! $request->header('X-Inertia')
            ? response()->json($payload)
            : Inertia::render('Fleet/Trip', $payload);
    }

    public function decide(DecisionRequest $request, Trip $trip, string $decision): RedirectResponse
    {
        $this->reviews->decide($this->userId($request), $request->user('admin')->displayName(), $trip, $decision, $request->validated('note'));

        return back()->with('success', __("fleet.decided.{$decision}", ['id' => $trip->getKey()]));
    }

    private function userId(Request $request): int
    {
        return (int) $request->user('admin')->getAuthIdentifier();
    }
}
