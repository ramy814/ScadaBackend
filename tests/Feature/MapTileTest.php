<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\SystemOperationalStateService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MapTileTest extends TestCase
{
    private const TILE = '/api/map/tiles/13/4880/3340.png';

    private const HIRAF = 'https://apps.mogaza.org/api/account-follow-up/profile';

    private const SIGNAL = 'https://mapps.mogaza.org/api/get_signal_statistics';

    private const GEO = 'https://geo.mogaza.org/geoserver/gwc/service/wmts*';

    private const COUNTS = [
        'status' => true,
        'data' => ['signal_pending_count' => 0, 'signal_urgent_count' => 0, 'signal_complete_today_count' => 0],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
        config([
            'services.hiraf.profile_url' => self::HIRAF,
            'services.signal.map_auth_url' => self::SIGNAL,
            'geoserver.base_url' => 'https://geo.mogaza.org/geoserver',
            'geoserver.username' => 'test-geoserver-user',
            'geoserver.password' => 'test-geoserver-password',
        ]);
        $this->mock(SystemOperationalStateService::class)
            ->shouldReceive('current')->andReturn(['shutdown_enabled' => false]);
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
    }

    private function fakeSignal(): void
    {
        Http::fake([
            self::HIRAF => Http::response(['message' => 'Unauthenticated.'], 401),
            self::SIGNAL => Http::response(self::COUNTS),
            self::GEO => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
        ]);
    }

    public function test_it_requires_a_bearer_token_without_contacting_upstreams(): void
    {
        Http::fake();
        $this->get(self::TILE)->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_existing_active_hiraf_inspectors_can_still_download_png(): void
    {
        Http::fake([
            self::HIRAF => Http::response(['status' => true, 'data' => ['oid' => 1, 'is_active' => '1']]),
            self::GEO => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
        ]);

        $this->withToken('hiraf-test-token')->get(self::TILE)
            ->assertOk()->assertHeader('Content-Type', 'image/png')->assertContent($this->png());
        Http::assertSentCount(2);
        Http::assertNotSent(fn (Request $request): bool => $request->url() === self::SIGNAL);
    }

    public function test_valid_signal_tokens_use_the_same_tile_route_and_fixed_wmts_layer(): void
    {
        $this->fakeSignal();
        $this->withToken('signal-test-token')->get(self::TILE)
            ->assertOk()->assertHeader('Content-Type', 'image/png')->assertContent($this->png());

        Http::assertSent(fn (Request $request): bool => $request->url() === self::SIGNAL
            && $request->hasHeader('Authorization', 'Bearer signal-test-token')
            && $request->hasHeader('Accept', 'application/json'));
        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/gwc/service/wmts')) {
                return false;
            }
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query['LAYER'] === 'mog:gaza general map'
                && $query['TILEMATRIX'] === 'EPSG:900913:13'
                && $query['TILEMATRIXSET'] === 'EPSG:900913'
                && $query['TILECOL'] === '4880' && $query['TILEROW'] === '3340'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('test-geoserver-user:test-geoserver-password'));
        });
        Http::assertSentCount(3);
    }

    public function test_invalid_tokens_are_not_cached_and_never_reach_geoserver(): void
    {
        Http::fake([
            self::HIRAF => Http::response([], 401),
            self::SIGNAL => Http::response([], 403),
        ]);
        $this->withToken('invalid-test-token')->get(self::TILE)->assertUnauthorized();
        $this->withToken('invalid-test-token')->get(self::TILE)->assertUnauthorized();
        Http::assertSentCount(4);
    }

    public function test_success_http_with_rejected_business_status_does_not_authenticate(): void
    {
        Http::fake([
            self::HIRAF => Http::response([], 401),
            self::SIGNAL => Http::response(['status' => false, 'data' => self::COUNTS['data']]),
        ]);
        $this->withToken('rejected-test-token')->get(self::TILE)->assertUnauthorized();
        Http::assertSentCount(2);
    }

    public function test_an_inactive_hiraf_profile_cannot_authorize_a_tile(): void
    {
        Http::fake([
            self::HIRAF => Http::response(['status' => true, 'data' => ['oid' => 1, 'is_active' => '0']]),
            self::SIGNAL => Http::response([], 401),
        ]);
        $this->withToken('inactive-test-token')->get(self::TILE)->assertUnauthorized();
        Http::assertSentCount(2);
    }

    public function test_auth_redirects_are_not_followed_or_accepted_as_login_success(): void
    {
        Http::fake(function (Request $request, array $options) {
            $this->assertFalse($options['allow_redirects']);

            return $request->url() === self::HIRAF
                ? Http::response('', 302, ['Location' => 'https://untrusted.example/login'])
                : Http::response([], 401);
        });
        $this->withToken('redirect-test-token')->get(self::TILE)->assertStatus(503);
        Http::assertSentCount(2);
    }

    public function test_html_or_incomplete_json_cannot_authorize_a_tile(): void
    {
        Http::fake([
            self::HIRAF => Http::response('<html>Login</html>', 200, ['Content-Type' => 'text/html']),
            self::SIGNAL => Http::response(['status' => true, 'data' => []]),
        ]);
        $this->withToken('malformed-test-token')->get(self::TILE)->assertStatus(503);
        Http::assertSentCount(2);
    }

    public function test_signal_authentication_works_when_hiraf_is_temporarily_down(): void
    {
        Http::fake([
            self::HIRAF => Http::response([], 503),
            self::SIGNAL => Http::response(self::COUNTS),
            self::GEO => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
        ]);
        $this->withToken('signal-test-token')->get(self::TILE)->assertOk();
    }

    public function test_signal_authentication_works_after_a_hiraf_connection_failure(): void
    {
        Http::fake([
            self::HIRAF => Http::failedConnection(),
            self::SIGNAL => Http::response(self::COUNTS),
            self::GEO => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
        ]);
        $this->withToken('signal-test-token')->get(self::TILE)->assertOk();
    }

    public function test_authentication_outage_returns_retryable_503_without_a_tile(): void
    {
        Http::fake([
            self::HIRAF => Http::response([], 401),
            self::SIGNAL => Http::failedConnection(),
        ]);
        $this->withToken('signal-test-token')->get(self::TILE)
            ->assertStatus(503)->assertHeader('Retry-After', '30');
        Http::assertSentCount(2);
    }

    public function test_only_positive_auth_decisions_are_cached_for_one_minute(): void
    {
        $this->fakeSignal();
        $this->withToken('signal-test-token')->get(self::TILE)->assertOk();
        $this->withToken('signal-test-token')->get(self::TILE)->assertOk();
        Http::assertSentCount(4); // Two auth requests, two independent tile fetches.
        $this->travel(61)->seconds();
        $this->withToken('signal-test-token')->get(self::TILE)->assertOk();
        Http::assertSentCount(7);
    }

    public function test_changing_auth_endpoint_invalidates_the_cached_decision(): void
    {
        $this->fakeSignal();
        $this->withToken('signal-test-token')->get(self::TILE)->assertOk();
        config(['services.signal.map_auth_url' => 'https://mapps.mogaza.org/changed-auth-endpoint']);
        Http::fake([
            self::HIRAF => Http::response([], 401),
            'https://mapps.mogaza.org/changed-auth-endpoint' => Http::response([], 401),
        ]);
        $this->withToken('signal-test-token')->get(self::TILE)->assertUnauthorized();
    }

    public function test_zoom_and_gaza_coverage_limits_remain_enforced_for_signal_users(): void
    {
        $this->fakeSignal();
        foreach (['18/0/0', '10/0/0', '13/0/0'] as $tile) {
            $this->withToken('signal-test-token')->get('/api/map/tiles/'.$tile.'.png')->assertNotFound();
        }
        Http::assertSentCount(2); // Auth only, no GeoServer request.
    }

    public function test_geoserver_errors_are_not_returned_as_successful_tiles(): void
    {
        Http::fake([
            self::HIRAF => Http::response([], 401),
            self::SIGNAL => Http::response(self::COUNTS),
            self::GEO => Http::response('<html>Unavailable</html>', 200, ['Content-Type' => 'text/html']),
        ]);
        $this->withToken('signal-test-token')->get(self::TILE)->assertStatus(502);
    }

    public function test_global_operational_suspension_also_protects_the_map_gateway(): void
    {
        Http::fake();
        $this->mock(SystemOperationalStateService::class)->shouldReceive('current')
            ->andReturn(['shutdown_enabled' => true, 'reason' => 'صيانة تجريبية']);
        $this->withToken('signal-test-token')->get(self::TILE)
            ->assertStatus(503)->assertJsonPath('code', 'systems_temporarily_suspended');
        Http::assertNothingSent();
    }

    public function test_rate_limit_is_applied_before_external_authentication(): void
    {
        Http::fake();
        for ($i = 0; $i < 600; $i++) {
            $this->get(self::TILE)->assertUnauthorized();
        }
        $this->get(self::TILE)->assertStatus(429)->assertHeader('Retry-After');
        Http::assertNothingSent();
    }
}
