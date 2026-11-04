<?php

declare(strict_types=1);

namespace App\Platform\Identity;

use App\Http\Controllers\Controller;
use Database\Fixture\DemoData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /** MOG SSO handshake through the server-only mog/project-helper package. */
    public function login(Request $request): Response
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('overview');
        }

        $sso = 'Mog\\ProjectHelper\\AuthHelpers';
        if (! $request->has('sso_error') && class_exists($sso)) {
            return $sso::login($request);
        }

        return Inertia::render('Auth/SignIn', [
            'ssoError' => $request->has('sso_error'),
            'ssoAvailable' => class_exists($sso),
            'devLoginUrl' => app()->environment('local') ? route('dev-login') : null,
            'demoIdentities' => $this->demoFixture() ? array_map(
                fn (int $id, string $scope): array => ['id' => $id, 'scope' => $scope, 'url' => route('dev-login', ['as' => $id])],
                array_keys(DemoData::IDENTITIES), DemoData::IDENTITIES,
            ) : [],
        ])->toResponse($request);
    }

    public function logout(Request $request): Response
    {
        $sso = 'Mog\\ProjectHelper\\AuthHelpers';
        if (class_exists($sso)) {
            return $this->fullPageVisit($request, $sso::logout($request, 'admin'));
        }

        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->fullPageVisit($request, redirect()->route('login'));
    }

    /**
     * The sign-out button is an Inertia (XHR) visit, but the SSO package redirects to the SSO
     * server's own logout page on another host, which an XHR cannot follow (the button did
     * nothing in production). Inertia visits get a full browser navigation to the same target,
     * as the legacy plain link did.
     */
    private function fullPageVisit(Request $request, Response $response): Response
    {
        if (! $request->header('X-Inertia')) {
            return $response;
        }

        $target = $response instanceof RedirectResponse ? $response->getTargetUrl() : route('login');

        return Inertia::location($target);
    }

    /** APP_ENV=local only (route is not registered otherwise). */
    public function devLogin(Request $request): Response
    {
        // Switching identity is limited to the demo fixture's fictional users (to check each permission scope).
        $requested = (int) $request->query('as');
        $userId = $requested > 0 && $this->demoFixture() && isset(DemoData::IDENTITIES[$requested])
            ? $requested
            : (int) config('platform.local_admin_user_id');
        abort_if($userId <= 0, 404, 'Set MOG_LOCAL_ADMIN_USER_ID to use local sign-in.');

        Auth::guard('admin')->loginUsingId($userId);
        $request->session()->regenerate();

        return redirect()->route('overview');
    }

    private function demoFixture(): bool
    {
        return app()->environment('local') && config('database.connections.oracle.driver') === 'sqlite';
    }
}
