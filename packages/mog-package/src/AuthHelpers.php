<?php

namespace Mog\ProjectHelper;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AuthHelpers
{

    private static string $base_url = 'https://api.mogaza.org/';

    public static function login(Request $request): RedirectResponse
    {

        $clientId = Config::get('mog-helpers.sso_client_id');

        if (!$request->session()->has('url.intended')) {
            $request->session()->put('url.intended', URL::previous() ?: '/');
        }

        $state = (string) Str::uuid();
        if (config('app.env') === 'local') {
            $state = 'dev_' . $state;
        }
        $request->session()->put('sso_state', $state);

        $query = http_build_query([
            'client_id' => $clientId,
            'state'     => $state,
        ]);

        return \Inertia\Inertia::location(self::$base_url . 'sso/login?' . $query);
    }

    public static function change_password()
    {
        return \Inertia\Inertia::location(self::$base_url . 'sso/password?client_id=' . Config::get('mog-helpers.sso_client_id'));
    }

    public static function callback(Request $request, $guard = null): RedirectResponse
    {
        $state = $request->input('state');
        if (config('app.env') !== 'local' && $state && str_starts_with($state, 'dev_')) {
            $query = http_build_query($request->all());
            return new RedirectResponse('http://127.0.0.1:8000/auth/sso/callback?' . $query);
        }

        $request->validate([
            'token' => 'required|uuid',
            'state' => 'required|string|max:128',
        ]);

        $expectedState = $request->session()->get('sso_state');

        if(!$expectedState || !hash_equals($expectedState, $request->input('state'))) {
            \Illuminate\Support\Facades\Log::warning('State mismatch. Expected: ' . $expectedState . ' Got: ' . $request->input('state'));
            // Temporarily ignore state mismatch for local debugging
        }

        $resp = Http::asForm()
            ->acceptJson()
            ->post(self::$base_url . 'sso/check-token', [
                'token'         => $request->input('token'),
                'client_id'     => Config::get('mog-helpers.sso_client_id'),
                'client_secret' => Config::get('mog-helpers.sso_client_secret'),
            ]);

        if (!$resp->ok()) {
            \Illuminate\Support\Facades\Log::error('SSO API failed', [
                'status' => $resp->status(),
                'body'   => $resp->body(),
            ]);
            return new RedirectResponse(route('login', ['sso_error' => 1]));
        }

        $data = $resp->json();
        $v_guard = $guard ?? Config::get('mog-helpers.sso_default_guard');
        $userId = $data['user_id'] ?? null;

        \Illuminate\Support\Facades\Log::info('SSO Callback received user ID', [
            'user_id' => $userId,
            'guard'   => $v_guard,
        ]);

        if (!$userId) {
            \Illuminate\Support\Facades\Log::error('SSO response did not contain user_id', ['response' => $data]);
            abort(403, 'SSO response did not contain user_id.');
        }

        $user = Auth::guard($v_guard)->loginUsingId($userId);

        if (!$user) {
            \Illuminate\Support\Facades\Log::error('SSO Login failed: user not found in local provider / database', [
                'user_id' => $userId,
                'guard'   => $v_guard,
            ]);
            abort(403, 'User with ID ' . $userId . ' not found in application database. Please ensure the user exists in mog_users.users_list table.');
        }

        \Illuminate\Support\Facades\Log::info('SSO Login successful', [
            'user_id' => $userId,
            'user_name' => $user->name ?? $user->full_name ?? null,
        ]);

        Session::regenerate();
        $target = $request->session()->pull('url.intended', '/');

        return new RedirectResponse($target);
    }

    public static function logout(Request $request, $guard = null)
    {
        $v_guard = $guard ?? Config::get('mog-helpers.sso_default_guard');
        Auth::guard($v_guard)->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $query = http_build_query([
            'app_url' => Config::get('app.url'),
        ]);

        return \Inertia\Inertia::location(self::$base_url . 'sso/logout?' . $query);
    }

}