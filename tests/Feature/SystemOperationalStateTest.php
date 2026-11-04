<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RejectWhenSystemsSuspended;
use App\Http\Requests\UpdateSystemOperationalStateRequest;
use App\Repositories\OracleReadiness;
use App\Repositories\SystemOperationalStateRepository;
use App\Services\SystemOperationalStateService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Mockery;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SystemOperationalStateTest extends TestCase
{
    protected function tearDown(): void
    {
        Cache::store('array')->flush();
        Mockery::close();

        parent::tearDown();
    }

    public function test_operational_requests_continue_normally(): void
    {
        $service = Mockery::mock(SystemOperationalStateService::class);
        $service->shouldReceive('current')->once()->andReturn($this->state(false));

        $response = (new RejectWhenSystemsSuspended($service))->handle(
            Request::create('/api/driver/trips', 'POST'),
            fn (): Response => new Response('ok'),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', $response->getContent());
    }

    public function test_suspended_mobile_requests_receive_a_retryable_503(): void
    {
        $service = Mockery::mock(SystemOperationalStateService::class);
        $service->shouldReceive('current')->once()->andReturn(
            $this->state(true, 'صيانة طارئة'),
        );

        $request = Request::create('/api/driver/trips', 'POST');
        $request->headers->set('Accept', 'application/json');

        $response = (new RejectWhenSystemsSuspended($service))->handle(
            $request,
            fn (): Response => new Response('should not run'),
        );
        $payload = json_decode((string) $response->getContent(), true);

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('300', $response->headers->get('Retry-After'));
        self::assertSame('systems_temporarily_suspended', $payload['code']);
        self::assertSame('صيانة طارئة', $payload['message']);
        self::assertTrue($payload['retryable']);
    }

    public function test_suspended_web_requests_receive_the_arabic_maintenance_page(): void
    {
        $service = Mockery::mock(SystemOperationalStateService::class);
        $service->shouldReceive('current')->once()->andReturn(
            $this->state(true, 'تحديث الخوادم'),
        );

        $response = (new RejectWhenSystemsSuspended($service))->handle(
            Request::create('/admin/dashboard'),
            fn (): Response => new Response('should not run'),
        );

        self::assertSame(503, $response->getStatusCode());
        self::assertStringContainsString('أنظمة بلدية غزة متوقفة مؤقتاً', (string) $response->getContent());
        self::assertStringContainsString('تحديث الخوادم', (string) $response->getContent());
    }

    public function test_all_mobile_entry_points_use_the_operational_middleware(): void
    {
        foreach ([
            ['/api/driver/auth/send-code', 'POST'],
            ['/api/wells/auth/login', 'POST'],
            ['/api/valves/auth/login', 'POST'],
        ] as [$uri, $method]) {
            $route = Route::getRoutes()->match(Request::create($uri, $method));

            self::assertContains('systems.operational', $route->gatherMiddleware(), $uri);
        }
    }

    public function test_mobile_login_routes_are_short_circuited_before_their_controllers(): void
    {
        $service = Mockery::mock(SystemOperationalStateService::class);
        $service->shouldReceive('current')->times(3)->andReturn(
            $this->state(true, 'صيانة شاملة'),
        );
        $this->app->instance(SystemOperationalStateService::class, $service);

        foreach ([
            '/api/driver/auth/send-code',
            '/api/wells/auth/login',
            '/api/valves/auth/login',
        ] as $uri) {
            $this->postJson($uri, [])->assertStatus(503)->assertJson([
                'code' => 'systems_temporarily_suspended',
                'retryable' => true,
                'message' => 'صيانة شاملة',
            ]);
        }
    }

    public function test_permissions_remain_available_as_the_recovery_control_plane(): void
    {
        $permissionsRoute = Route::getRoutes()->getByName('portal.permissions');
        $portalRoute = Route::getRoutes()->getByName('portal');

        self::assertNotNull($permissionsRoute);
        self::assertNotNull($portalRoute);
        self::assertNotContains('systems.operational', $permissionsRoute->gatherMiddleware());
        self::assertContains('systems.operational', $portalRoute->gatherMiddleware());
    }

    public function test_missing_oracle_connection_fails_open_for_reads_but_blocks_updates(): void
    {
        $readiness = Mockery::mock(OracleReadiness::class);
        $readiness->shouldReceive('available')->twice()->andReturnFalse();
        $repository = new SystemOperationalStateRepository($readiness);

        self::assertFalse($repository->current()['shutdown_enabled']);

        $this->expectException(RuntimeException::class);
        $repository->update(true, 'صيانة', 140, 'مسؤول النظام');
    }

    public function test_repository_persists_and_reads_the_global_state(): void
    {
        config([
            'database.connections.oracle' => array_merge(
                config('database.connections.sqlite'),
                ['database' => ':memory:'],
            ),
        ]);
        DB::purge('oracle');

        Schema::connection('oracle')->create('system_operational_states', function (Blueprint $table): void {
            $table->id();
            $table->string('scope')->unique();
            $table->boolean('is_suspended')->default(false);
            $table->string('reason', 1000)->nullable();
            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->string('changed_by_name')->nullable();
            $table->timestamp('changed_at')->nullable();
        });

        $readiness = Mockery::mock(OracleReadiness::class);
        $readiness->shouldReceive('available')->once()->andReturnTrue();
        $repository = new SystemOperationalStateRepository($readiness);

        $suspended = $repository->update(true, 'صيانة قاعدة البيانات', 140, 'مسؤول النظام');

        self::assertTrue($suspended['shutdown_enabled']);
        self::assertSame('صيانة قاعدة البيانات', $suspended['reason']);
        self::assertSame(140, $suspended['changed_by_user_id']);
        self::assertTrue($repository->current()['shutdown_enabled']);
    }

    public function test_repeated_operational_reads_use_the_short_non_database_cache(): void
    {
        config([
            'database.connections.oracle' => array_merge(
                config('database.connections.sqlite'),
                ['database' => ':memory:'],
            ),
        ]);
        DB::purge('oracle');

        Schema::connection('oracle')->create('system_operational_states', function (Blueprint $table): void {
            $table->id();
            $table->string('scope')->unique();
            $table->boolean('is_suspended')->default(false);
            $table->string('reason', 1000)->nullable();
            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->string('changed_by_name')->nullable();
            $table->timestamp('changed_at')->nullable();
        });
        DB::connection('oracle')->table('system_operational_states')->insert([
            'scope' => 'global',
            'is_suspended' => false,
        ]);

        $readiness = Mockery::mock(OracleReadiness::class);
        $readiness->shouldReceive('available')->once()->andReturnTrue();
        $repository = new SystemOperationalStateRepository($readiness);

        self::assertFalse($repository->current()['shutdown_enabled']);
        self::assertFalse($repository->current()['shutdown_enabled']);
    }

    public function test_shutdown_requires_a_reason_but_restart_does_not(): void
    {
        $rules = (new UpdateSystemOperationalStateRequest)->rules();

        self::assertTrue(Validator::make([
            'shutdown_enabled' => true,
            'reason' => '',
        ], $rules)->fails());

        self::assertTrue(Validator::make([
            'shutdown_enabled' => false,
            'reason' => null,
        ], $rules)->passes());
    }

    /**
     * @return array{
     *     shutdown_enabled: bool,
     *     reason: ?string,
     *     changed_by_user_id: int,
     *     changed_by_name: string,
     *     changed_at: string
     * }
     */
    private function state(bool $shutdownEnabled, ?string $reason = null): array
    {
        return [
            'shutdown_enabled' => $shutdownEnabled,
            'reason' => $reason,
            'changed_by_user_id' => 140,
            'changed_by_name' => 'مسؤول النظام',
            'changed_at' => '2026-08-24T08:00:00+00:00',
        ];
    }
}
