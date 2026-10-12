<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SystemPermissionRequest;
use App\Http\Requests\SystemPermissionUserSearchRequest;
use App\Http\Requests\UpdateSystemOperationalStateRequest;
use App\Repositories\SystemPermissionRepository;
use App\Services\SystemOperationalStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class SystemPermissionController extends Controller
{
    public function __construct(
        private readonly SystemPermissionRepository $permissions,
        private readonly SystemOperationalStateService $operationalState,
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
            'operationalState' => $this->operationalState->current(),
            'operationalStateUrl' => route('portal.permissions.operational-state'),
        ]);
    }

    public function updateOperationalState(
        UpdateSystemOperationalStateRequest $request,
    ): RedirectResponse {
        $this->authorizeManager();

        $validated = $request->validated();
        $shutdownEnabled = (bool) $validated['shutdown_enabled'];

        try {
            $this->operationalState->update(
                $shutdownEnabled,
                $validated['reason'] ?? null,
                $this->currentActorId(),
                $this->currentActor(),
            );
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('portal.permissions')
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('portal.permissions')
            ->with(
                'ok',
                $shutdownEnabled
                    ? 'تم إيقاف البوابة وخدمات تطبيقات الموبايل.'
                    : 'تمت إعادة تشغيل البوابة وخدمات تطبيقات الموبايل.',
            );
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

        abort_unless(in_array($systemKey, SystemPermissionRepository::PERMISSION_KEYS, true), 404);

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
                'key' => SystemPermissionRepository::FLEET_VEHICLES_RENTED,
                'label' => 'المركبات المستأجرة',
                'description' => 'عرض ومراجعة مركبات FOLLOW_TO_OID = 125.',
                'requires' => 'fleet',
            ],
            [
                'key' => SystemPermissionRepository::FLEET_VEHICLES_MUNICIPALITY,
                'label' => 'مركبات ملك البلدية',
                'description' => 'عرض ومراجعة مركبات FOLLOW_TO_OID = 126.',
                'requires' => 'fleet',
            ],
            [
                'key' => SystemPermissionRepository::FLEET_VEHICLES_INSTITUTIONS,
                'label' => 'مركبات المؤسسات',
                'description' => 'عرض ومراجعة مركبات FOLLOW_TO_OID = 132.',
                'requires' => 'fleet',
            ],
            [
                'key' => SystemPermissionRepository::FLEET_VEHICLES_MINISTRIES,
                'label' => 'مركبات الوزارات الحكومية',
                'description' => 'عرض ومراجعة مركبات FOLLOW_TO_OID = 133.',
                'requires' => 'fleet',
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
            [
                'key' => SystemPermissionRepository::CREATE_MANUAL_TRIP,
                'label' => 'إدخال رحلة يدوية',
                'description' => 'دخول شاشة إدخال الرحلات فقط لمركبات محطات المساعد الإداري.',
            ],
            [
                'key' => SystemPermissionRepository::DELETE_WELL_READING,
                'label' => 'حذف قراءات الآبار',
                'description' => 'إخفاء قراءة مع حفظ منفذ العملية ووقتها وسببها.',
                'requires' => 'wells',
            ],
            [
                'key' => SystemPermissionRepository::DELETE_DIESEL_INVENTORY,
                'label' => 'حذف جرد السولار',
                'description' => 'إخفاء سجل جرد مع حفظ منفذ العملية ووقتها وسببها.',
                'requires' => 'wells',
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
            ?? ('admin#'.(string) ($user?->getAuthIdentifier() ?? 'unknown'));
    }

    private function currentActorId(): int
    {
        return (int) Auth::guard('admin')->id();
    }
}
