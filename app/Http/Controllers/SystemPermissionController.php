<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SystemPermissionRequest;
use App\Http\Requests\SystemPermissionUserSearchRequest;
use App\Repositories\SystemPermissionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SystemPermissionController extends Controller
{
    public function __construct(
        private readonly SystemPermissionRepository $permissions
    ) {}

    public function index(): Response
    {
        $this->authorizeManager();

        return Inertia::render('Portal/Permissions', [
            'users' => $this->permissions->groupedByUser(),
            'systems' => $this->systems(),
            'storeUrl' => route('portal.permissions.store'),
            'permissionsUrl' => route('portal.permissions'),
            'searchUsersUrl' => route('portal.permissions.search-users'),
            'portalUrl' => route('portal'),
        ]);
    }

    public function store(SystemPermissionRequest $request): RedirectResponse
    {
        $this->authorizeManager();

        $validated = $request->validated();

        $this->permissions->replaceForUser(
            (int) $validated['user_id'],
            array_values($validated['systems'] ?? []),
            $this->currentActor()
        );

        return redirect()
            ->route('portal.permissions')
            ->with('ok', 'تم تحديث صلاحيات المستخدم بنجاح.');
    }

    public function destroy(int $userId, string $systemKey): RedirectResponse
    {
        $this->authorizeManager();

        abort_unless(in_array($systemKey, SystemPermissionRepository::SYSTEM_KEYS, true), 404);

        $this->permissions->revoke($userId, $systemKey);

        return redirect()
            ->route('portal.permissions')
            ->with('ok', 'تم إلغاء الصلاحية المحددة.');
    }

    public function searchUsers(SystemPermissionUserSearchRequest $request): JsonResponse
    {
        $this->authorizeManager();

        return response()->json(
            $this->permissions->searchUsers($request->validated()['q'])
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function systems(): array
    {
        return [
            [
                'key' => 'fleet',
                'label' => 'تتبع المركبات',
                'description' => 'لوحات الرحلات والتقارير ومتابعة المركبات.',
            ],
            [
                'key' => 'wells',
                'label' => 'إدارة الآبار',
                'description' => 'متابعة الآبار والقراءات وفرق الفحص.',
            ],
            [
                'key' => 'valves',
                'label' => 'متابعة المحابس',
                'description' => 'خرائط ومتابعة محابس الشبكة البلدية.',
            ],
        ];
    }

    private function authorizeManager(): void
    {
        $allowedIds = array_map('intval', config('mog-helpers.permissions_admin_user_ids', []));

        if (app()->environment('local')) {
            return;
        }

        $userId = (int) Auth::guard('admin')->id();

        abort_unless(
            in_array($userId, $allowedIds, true),
            403,
            'ليست لديك صلاحية إدارة صلاحيات الأنظمة.'
        );
    }

    private function currentActor(): string
    {
        $user = Auth::guard('admin')->user();

        return $user?->name
            ?? $user?->email
            ?? ('admin#' . (string) ($user?->getAuthIdentifier() ?? 'unknown'));
    }
}
