<?php

declare(strict_types=1);

namespace App\Modules\Wells\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Wells\Http\Requests\WellsListRequest;
use App\Modules\Wells\Services\WellsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WellsController extends Controller
{
    public function __construct(private readonly WellsService $wells) {}

    public function today(): Response
    {
        return Inertia::render('Wells/Today', [...$this->wells->today(), 'refreshSeconds' => 30]);
    }

    public function readings(WellsListRequest $request): Response
    {
        return Inertia::render('Wells/Readings', $this->wells->readings($request->safe()->only(['search', 'date_from', 'date_to'])));
    }

    public function signals(WellsListRequest $request): Response
    {
        return Inertia::render('Wells/Signals', $this->wells->signals($request->validated('state')));
    }

    public function markRead(?int $signal = null): RedirectResponse
    {
        $this->wells->markRead($signal);

        return back()->with('success', __('wells.signals.marked'));
    }

    public function assets(WellsListRequest $request): Response
    {
        return Inertia::render('Wells/Assets', $this->wells->assets($request->safe()->only(['search', 'damage_status', 'is_active'])));
    }

    public function field(): Response
    {
        return Inertia::render('Wells/Field', $this->wells->field());
    }

    public function media(int $media): StreamedResponse
    {
        return $this->wells->media($media);
    }
}
