<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\EnsureMobilePrincipal;
use App\Models\Beneficiary;
use App\Models\Valves\ValveInspector;
use App\Models\Wells\Inspector;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Exceptions\MissingAbilityException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MobileTokenAbilityTest extends TestCase
{
    public function test_mobile_routes_require_their_own_ability_and_principal(): void
    {
        foreach ([
            ['/api/driver/trips', 'GET', 'driver'],
            ['/api/wells/sync/wells', 'GET', 'wells'],
            ['/api/dashboard/stats', 'GET', 'scada'],
            ['/api/valves', 'GET', 'scada'],
            ['/api/valve-maintenance', 'GET', 'scada'],
        ] as [$uri, $method, $ability]) {
            $middleware = Route::getRoutes()
                ->match(Request::create($uri, $method))
                ->gatherMiddleware();

            self::assertContains('auth:sanctum', $middleware, $uri);
            self::assertContains("abilities:{$ability}", $middleware, $uri);
            self::assertContains("mobile.principal:{$ability}", $middleware, $uri);
        }
    }

    public function test_login_routes_remain_public(): void
    {
        foreach ([
            ['/api/driver/auth/verify', 'POST'],
            ['/api/wells/auth/login', 'POST'],
            ['/api/valves/auth/login', 'POST'],
        ] as [$uri, $method]) {
            $middleware = Route::getRoutes()
                ->match(Request::create($uri, $method))
                ->gatherMiddleware();

            self::assertNotContains('auth:sanctum', $middleware, $uri);
            self::assertFalse(collect($middleware)->contains(
                fn (string $name): bool => str_starts_with($name, 'abilities:')
                    || str_starts_with($name, 'mobile.principal:'),
            ), $uri);
        }
    }

    public function test_scoped_token_can_only_satisfy_its_own_ability(): void
    {
        $driver = (new Beneficiary)->withAccessToken($this->token(['driver']));
        $request = Request::create('/api/driver/trips');
        $request->setUserResolver(fn (): Beneficiary => $driver);

        $response = (new CheckAbilities)->handle(
            $request,
            fn (): Response => new Response('ok'),
            'driver',
        );

        self::assertSame('ok', $response->getContent());

        $this->expectException(MissingAbilityException::class);

        (new CheckAbilities)->handle(
            $request,
            fn (): Response => new Response('should not run'),
            'wells',
        );
    }

    #[DataProvider('principalProvider')]
    public function test_principal_guard_rejects_cross_system_tokens(
        object $user,
        string $ability,
        bool $allowed,
    ): void {
        $request = Request::create('/api/test');
        $request->setUserResolver(fn (): object => $user);
        $middleware = new EnsureMobilePrincipal;

        if (! $allowed) {
            try {
                $middleware->handle($request, fn (): Response => new Response('ok'), $ability);
                self::fail('Cross-system principal was accepted.');
            } catch (HttpException $exception) {
                self::assertSame(403, $exception->getStatusCode());
            }

            return;
        }

        self::assertSame(
            'ok',
            $middleware->handle($request, fn (): Response => new Response('ok'), $ability)->getContent(),
        );
    }

    public static function principalProvider(): array
    {
        return [
            'driver accepted by driver API' => [new Beneficiary, 'driver', true],
            'driver rejected by wells API' => [new Beneficiary, 'wells', false],
            'driver rejected by SCADA API' => [new Beneficiary, 'scada', false],
            'wells accepted by wells API' => [new Inspector, 'wells', true],
            'wells rejected by driver API' => [new Inspector, 'driver', false],
            'wells rejected by SCADA API' => [new Inspector, 'scada', false],
            'SCADA accepted by SCADA API' => [new ValveInspector, 'scada', true],
            'SCADA rejected by driver API' => [new ValveInspector, 'driver', false],
            'SCADA rejected by wells API' => [new ValveInspector, 'wells', false],
        ];
    }

    public function test_migration_scopes_current_and_legacy_mobile_tokens(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('tokenable_type');
            $table->text('abilities')->nullable();
        });

        $types = [
            'App\\Models\\Beneficiary' => '["driver"]',
            'App\\Models\\User' => '["driver"]',
            'App\\Models\\DriverProfile' => '["driver"]',
            'App\\Models\\Wells\\Inspector' => '["wells"]',
            'App\\Models\\Inspector' => '["wells"]',
            'App\\Models\\Valves\\ValveInspector' => '["scada"]',
            'App\\Models\\Unknown' => '["*"]',
        ];

        foreach (array_keys($types) as $type) {
            DB::table('personal_access_tokens')->insert([
                'tokenable_type' => $type,
                'abilities' => '["*"]',
            ]);
        }

        $migration = require database_path('migrations/2026_08_26_000025_scope_mobile_access_tokens.php');
        $migration->up();

        foreach ($types as $type => $abilities) {
            self::assertSame(
                $abilities,
                DB::table('personal_access_tokens')->where('tokenable_type', $type)->value('abilities'),
                $type,
            );
        }

        $migration->down();

        self::assertSame(
            7,
            DB::table('personal_access_tokens')->where('abilities', '["*"]')->count(),
        );

        Schema::drop('personal_access_tokens');
    }

    private function token(array $abilities): PersonalAccessToken
    {
        return (new PersonalAccessToken)->forceFill([
            'name' => 'test',
            'token' => str_repeat('a', 64),
            'abilities' => $abilities,
        ]);
    }
}
