<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Services;

use App\Modules\Fleet\Repositories\MapClientRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;

class MapClientAuthService
{
    public function __construct(private readonly MapClientRepository $clients) {}

    public function authorize(?string $token): void
    {
        if ($token === null || trim($token) === '') {
            $this->reject(401);
        }

        $providers = [
            'hiraf' => (string) config('services.hiraf.profile_url'),
            'signal' => (string) config('services.signal.map_auth_url'),
        ];
        $cacheKey = 'map-client-token:'.hash('sha256', json_encode($providers).$token);

        if (Cache::get($cacheKey) === true) {
            return;
        }

        $unavailable = false;
        foreach ($providers as $provider => $url) {
            if ($url === '') {
                $unavailable = true;

                continue;
            }

            try {
                $response = $this->clients->checkToken($url, $token);
            } catch (ConnectionException) {
                $unavailable = true;

                continue;
            }

            if ($this->isAuthenticated($provider, $response)) {
                // Cache only the decision, never the token or upstream user data.
                Cache::put($cacheKey, true, now()->addMinute());

                return;
            }

            if (! $this->isRejected($provider, $response)) {
                $unavailable = true;
            }
        }

        $this->reject($unavailable ? 503 : 401);
    }

    private function isRejected(string $provider, Response $response): bool
    {
        return in_array($response->status(), [401, 403], true)
            || $response->json('status') === false
            || ($provider === 'hiraf'
                && in_array($response->json('data.is_active'), [false, 0, '0'], true));
    }

    private function isAuthenticated(string $provider, Response $response): bool
    {
        if (! $response->successful() || $response->json('status') !== true) {
            return false;
        }

        $data = $response->json('data');
        if (! is_array($data)) {
            return false;
        }

        if ($provider === 'hiraf') {
            return filter_var($data['oid'] ?? null, FILTER_VALIDATE_INT) > 0
                && (! array_key_exists('is_active', $data)
                    || in_array($data['is_active'], [true, 1, '1'], true));
        }

        // This existing, protected read endpoint is verified by the signal app.
        foreach (['signal_pending_count', 'signal_urgent_count', 'signal_complete_today_count'] as $key) {
            $count = $data[$key] ?? null;
            if ((! is_int($count) && ! is_string($count))
                || filter_var($count, FILTER_VALIDATE_INT) === false
                || (int) $count < 0) {
                return false;
            }
        }

        return true;
    }

    private function reject(int $status): never
    {
        $response = response()->json([
            'message' => $status === 401
                ? 'Unauthenticated.'
                : 'Map authentication service is temporarily unavailable.',
        ], $status)->header('Cache-Control', 'no-store');

        if ($status === 503) {
            $response->header('Retry-After', '30');
        }

        throw new HttpResponseException($response);
    }
}
