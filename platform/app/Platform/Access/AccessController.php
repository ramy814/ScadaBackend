<?php

declare(strict_types=1);

namespace App\Platform\Access;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccessController extends Controller
{
    public function __construct(
        private readonly SystemPermissionRepository $permissions,
        private readonly AccessDirectoryService $directory,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Platform/Access', [
            'users' => $this->directory->users(),
            'oracleAvailable' => $this->directory->oracleAvailable(),
            'catalog' => [
                ['application' => 'fleet', 'keys' => ['fleet']],
                ['application' => 'wells', 'keys' => ['wells', SystemPermissionRepository::DELETE_WELL_READING, SystemPermissionRepository::DELETE_DIESEL_INVENTORY]],
                ['application' => 'valves', 'keys' => ['valves']],
            ],
            'requirements' => SystemPermissionRepository::ACTION_REQUIREMENTS,
            'searchUrl' => route('platform.access.search'),
            'updateBase' => url('/platform/access'),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        return response()->json(['users' => $this->permissions->searchUsers((string) $request->query('q', ''))]);
    }

    public function update(UpdateAccessRequest $request, int $userId): RedirectResponse
    {
        $this->permissions->replaceForUser($userId, $request->validated('keys'), $request->user('admin')->displayName());

        return back()->with('success', __('access.saved'));
    }
}
