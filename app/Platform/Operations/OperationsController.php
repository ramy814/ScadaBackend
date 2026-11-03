<?php

declare(strict_types=1);

namespace App\Platform\Operations;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class OperationsController extends Controller
{
    public function __construct(private readonly OperationalStateService $operations) {}

    public function show(): Response
    {
        return Inertia::render('Platform/Operations', ['state' => $this->operations->current()]);
    }

    public function update(UpdateOperationalStateRequest $request): RedirectResponse
    {
        try {
            $this->operations->update(
                $request->boolean('shutdown_enabled'),
                $request->validated('reason'),
                (int) $request->user('admin')->getAuthIdentifier(),
                $request->user('admin')->displayName(),
            );
        } catch (RuntimeException $exception) {
            // Never report a shutdown as active when persistence failed.
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __($request->boolean('shutdown_enabled') ? 'operations.suspended' : 'operations.reopened'));
    }
}
