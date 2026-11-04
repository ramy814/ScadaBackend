<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Middleware\EnsureActionPermission;
use App\Repositories\SystemPermissionRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureActionPermissionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_denies_manual_trip_entry_without_the_exact_permission(): void
    {
        $permissions = Mockery::mock(SystemPermissionRepository::class);
        $permissions->shouldReceive('hasActionPermission')
            ->once()
            ->with(140, SystemPermissionRepository::CREATE_MANUAL_TRIP)
            ->andReturnFalse();
        Auth::shouldReceive('guard')->with('admin')->andReturn(new class
        {
            public function id(): int
            {
                return 140;
            }
        });

        $this->expectException(HttpException::class);

        (new EnsureActionPermission($permissions))->handle(
            Request::create('/admin/manual-trips/create'),
            fn (): Response => new Response('ok'),
            SystemPermissionRepository::CREATE_MANUAL_TRIP,
        );
    }

    public function test_it_allows_manual_trip_entry_with_the_exact_permission(): void
    {
        $permissions = Mockery::mock(SystemPermissionRepository::class);
        $permissions->shouldReceive('hasActionPermission')
            ->once()
            ->with(140, SystemPermissionRepository::CREATE_MANUAL_TRIP)
            ->andReturnTrue();
        Auth::shouldReceive('guard')->with('admin')->andReturn(new class
        {
            public function id(): int
            {
                return 140;
            }
        });

        $response = (new EnsureActionPermission($permissions))->handle(
            Request::create('/admin/manual-trips/create'),
            fn (): Response => new Response('ok'),
            SystemPermissionRepository::CREATE_MANUAL_TRIP,
        );

        self::assertSame('ok', $response->getContent());
    }
}
