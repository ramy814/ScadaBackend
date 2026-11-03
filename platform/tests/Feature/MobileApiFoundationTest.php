<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Fleet\Models\Beneficiary;
use App\Modules\Valves\Models\ValveInspector;
use App\Modules\Wells\Models\Inspector;
use App\Platform\Http\Middleware\EnsureMobilePrincipal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** Mobile auth contract shared by the three apps (legacy MobileTokenAbilityTest / SystemOperationalStateTest). */
class MobileApiFoundationTest extends TestCase
{
    public function test_mobile_routes_require_their_own_ability_and_principal(): void
    {
        foreach ([
            ['/api/driver/trips', 'GET', 'driver'],
            ['/api/driver/trips', 'POST', 'driver'],
            ['/api/driver/me', 'GET', 'driver'],
            ['/api/driver/app-presence', 'POST', 'driver'],
            ['/api/driver/trips/reconcile', 'GET', 'driver'],
            ['/api/driver/auth/logout', 'POST', 'driver'],
            ['/api/wells/sync/wells', 'GET', 'wells'],
            ['/api/wells/sync/notification-types', 'GET', 'wells'],
            ['/api/wells/readings/sync', 'POST', 'wells'],
            ['/api/wells/diesel-inventories/sync', 'POST', 'wells'],
            ['/api/wells/notifications/sync', 'POST', 'wells'],
            ['/api/wells/activity-media/sync', 'POST', 'wells'],
            ['/api/wells/sync/history', 'GET', 'wells'],
            ['/api/dashboard/stats', 'GET', 'scada'],
            ['/api/valves', 'GET', 'scada'],
            ['/api/valve-maintenance', 'GET', 'scada'],
        ] as [$uri, $method, $ability]) {
            $middleware = Route::getRoutes()->match(Request::create($uri, $method))->gatherMiddleware();

            self::assertContains('auth:sanctum', $middleware, $uri);
            self::assertContains("abilities:{$ability}", $middleware, $uri);
            self::assertContains("mobile.principal:{$ability}", $middleware, $uri);
            self::assertContains('systems.operational', $middleware, $uri);
        }
    }

    public function test_login_routes_remain_public_and_honor_the_emergency_switch(): void
    {
        foreach ([['/api/valves/auth/login', 'POST'], ['/api/wells/auth/login', 'POST'], ['/api/driver/auth/send-code', 'POST'], ['/api/driver/auth/verify', 'POST']] as [$uri, $method]) {
            $middleware = Route::getRoutes()->match(Request::create($uri, $method))->gatherMiddleware();

            self::assertNotContains('auth:sanctum', $middleware, $uri);
            self::assertContains('systems.operational', $middleware, $uri);
            self::assertFalse(collect($middleware)->contains(fn (string $name): bool => str_starts_with($name, 'abilities:') || str_starts_with($name, 'mobile.principal:')), $uri);
        }
    }

    #[DataProvider('principalProvider')]
    public function test_principal_guard_rejects_cross_system_tokens(object $user, string $ability, bool $allowed): void
    {
        $request = Request::create('/api/test');
        $request->setUserResolver(fn (): object => $user);

        if (! $allowed) {
            try {
                (new EnsureMobilePrincipal)->handle($request, fn (): Response => new Response('ok'), $ability);
                self::fail('Cross-system principal was accepted.');
            } catch (HttpException $exception) {
                self::assertSame(403, $exception->getStatusCode());
            }

            return;
        }

        self::assertSame('ok', (new EnsureMobilePrincipal)->handle($request, fn (): Response => new Response('ok'), $ability)->getContent());
    }

    public static function principalProvider(): array
    {
        return [
            'driver accepted by driver API' => [new Beneficiary, 'driver', true],
            'driver rejected by wells API' => [new Beneficiary, 'wells', false],
            'driver rejected by SCADA API' => [new Beneficiary, 'scada', false],
            'wells rejected by driver API' => [new Inspector, 'driver', false],
            'SCADA rejected by driver API' => [new ValveInspector, 'driver', false],
            'wells accepted by wells API' => [new Inspector, 'wells', true],
            'wells rejected by SCADA API' => [new Inspector, 'scada', false],
            'SCADA accepted by SCADA API' => [new ValveInspector, 'scada', true],
            'SCADA rejected by wells API' => [new ValveInspector, 'wells', false],
            'unknown ability rejected' => [new ValveInspector, 'nope', false],
        ];
    }

    public function test_suspended_mobile_requests_receive_the_legacy_retryable_503(): void
    {
        $this->db()->table('system_operational_states')->insert(['scope' => 'global', 'is_suspended' => 1, 'reason' => 'صيانة شاملة']);

        $this->postJson('/api/driver/auth/send-code', [])->assertStatus(503)->assertJsonPath('code', 'systems_temporarily_suspended');
        $this->postJson('/api/wells/auth/login', [])->assertStatus(503)->assertJsonPath('code', 'systems_temporarily_suspended');
        $this->postJson('/api/valves/auth/login', [])
            ->assertStatus(503)
            ->assertHeader('Retry-After', '300')
            ->assertExactJson(['message' => 'صيانة شاملة', 'code' => 'systems_temporarily_suspended', 'retryable' => true]);
    }

    public function test_tokens_issued_by_the_legacy_backend_keep_working_and_new_tokens_use_legacy_types(): void
    {
        $inspector = $this->db()->table('valve_inspectors')->insertGetId(['name' => 'Legacy', 'phone' => '0599', 'password' => Hash::make('secret'), 'is_active' => 1]);
        // Row exactly as the legacy backend stores it.
        $this->db()->table('personal_access_tokens')->insert([
            'tokenable_type' => 'App\\Models\\Valves\\ValveInspector', 'tokenable_id' => (string) $inspector,
            'name' => 'valves_app', 'token' => hash('sha256', 'legacy-plain'), 'abilities' => '["scada"]',
        ]);
        $id = $this->db()->table('personal_access_tokens')->value('id');

        $this->getJson('/api/valves', ['Authorization' => "Bearer {$id}|legacy-plain"])->assertOk();

        $this->postJson('/api/valves/auth/login', ['phone' => '0599', 'password' => 'secret'])->assertOk();
        $this->assertSame(
            ['App\\Models\\Valves\\ValveInspector'],
            $this->db()->table('personal_access_tokens')->distinct()->pluck('tokenable_type')->all(),
        );
    }

    public function test_a_wells_token_cannot_reach_the_valves_api(): void
    {
        $wells = $this->db()->table('inspectors')->insertGetId(['name' => 'Wells inspector', 'phone' => '0598', 'is_active' => 1]);
        $this->db()->table('personal_access_tokens')->insert([
            'tokenable_type' => 'App\\Models\\Wells\\Inspector', 'tokenable_id' => (string) $wells,
            'name' => 'wells_app', 'token' => hash('sha256', 'wells-plain'), 'abilities' => '["wells"]',
        ]);
        $id = $this->db()->table('personal_access_tokens')->value('id');

        $this->getJson('/api/valves', ['Authorization' => "Bearer {$id}|wells-plain"])->assertForbidden();
        $this->app['auth']->forgetGuards(); // the test kernel keeps the resolved user between requests
        $this->getJson('/api/valves')->assertUnauthorized();
    }
}
