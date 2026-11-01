<?php

declare(strict_types=1);

namespace App\Platform\Http\Middleware;

use App\Platform\Access\AccessService;
use App\Platform\Applications\ApplicationRegistry;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Shell contract shared with every page: who the viewer is, which applications
 * they may switch to, the contextual navigation of the current workspace, and
 * locale/direction. URLs come from Laravel (no Ziggy), as in the legacy project.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(
        private readonly AccessService $access,
        private readonly ApplicationRegistry $registry,
    ) {}

    public function share(Request $request): array
    {
        $user = $request->user('admin');
        $userId = (int) $user?->getAuthIdentifier();
        $locale = app()->getLocale();
        $workspace = $this->workspace($request);

        return [
            ...parent::share($request),
            'locale' => $locale,
            'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'auth' => fn () => $user === null ? null : [
                'id' => $userId,
                'name' => method_exists($user, 'displayName') ? $user->displayName() : (string) $userId,
                'isPlatformAdmin' => $this->access->isPlatformAdmin($userId),
            ],
            'shell' => fn () => $user === null ? null : [
                'workspace' => $workspace,
                'applications' => array_map(
                    fn (string $key): array => $this->registry->present($key, $locale),
                    $this->access->applications($userId),
                ),
                'navigation' => $this->navigation($workspace, $userId),
                'urls' => [
                    'overview' => route('overview'),
                    'platform' => $this->access->isPlatformAdmin($userId) ? route('platform.access') : route('platform.applications'),
                    'operations' => $this->access->isPlatformAdmin($userId) ? route('platform.operations') : null,
                    'logout' => route('logout'),
                    'locale' => route('locale'),
                    'tripLookup' => $this->access->canAccess($userId, 'fleet') ? url('/fleet/trips') : null,
                ],
            ],
        ];
    }

    private function workspace(Request $request): string
    {
        $segment = (string) $request->segment(1);

        return match (true) {
            $this->registry->has($segment) => $segment,
            $segment === 'platform' => 'platform',
            default => 'overview',
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function navigation(string $workspace, int $userId): array
    {
        if ($this->registry->has($workspace)) {
            return $this->access->canAccess($userId, $workspace)
                ? $this->registry->module($workspace)->navigation($userId)
                : [];
        }

        if ($workspace !== 'platform') {
            return [];
        }

        $admin = $this->access->isPlatformAdmin($userId);
        $items = array_values(array_filter([
            $admin ? ['key' => 'access', 'label' => 'nav.platform.access', 'route' => route('platform.access'), 'icon' => 'users'] : null,
            $admin ? ['key' => 'operations', 'label' => 'nav.platform.operations', 'route' => route('platform.operations'), 'icon' => 'power'] : null,
            ['key' => 'applications', 'label' => 'nav.platform.applications', 'route' => route('platform.applications'), 'icon' => 'smartphone'],
            $this->access->canAccess($userId, 'fleet') ? ['key' => 'audit', 'label' => 'nav.platform.audit', 'route' => route('platform.audit'), 'icon' => 'history'] : null,
        ]));

        return [['label' => 'nav.group.platform', 'items' => $items]];
    }
}
