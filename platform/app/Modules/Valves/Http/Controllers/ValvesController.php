<?php

declare(strict_types=1);

namespace App\Modules\Valves\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Valves\Http\Requests\ValvesListRequest;
use App\Modules\Valves\Services\ValvesService;
use Inertia\Inertia;
use Inertia\Response;

class ValvesController extends Controller
{
    public function __construct(private readonly ValvesService $valves) {}

    public function today(): Response
    {
        return Inertia::render('Valves/Today', $this->valves->today());
    }

    public function maintenance(ValvesListRequest $request): Response
    {
        return Inertia::render('Valves/Maintenance', $this->valves->maintenance($request->validated('status')));
    }

    public function actions(ValvesListRequest $request): Response
    {
        return Inertia::render('Valves/Actions', $this->valves->actions($request->safe()->except('status')));
    }

    public function field(): Response
    {
        return Inertia::render('Valves/Field', $this->valves->field());
    }
}
