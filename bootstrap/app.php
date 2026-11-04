<?php

use App\Http\Middleware\AuthenticateMapClient;
use App\Http\Middleware\EnsureActionPermission;
use App\Http\Middleware\EnsureAdminIsAllowlisted;
use App\Http\Middleware\EnsureFleetTripAccess;
use App\Http\Middleware\EnsureMobilePrincipal;
use App\Http\Middleware\EnsureSystemAccess;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RejectWhenSystemsSuspended;
use App\Support\AutoDeployCacheClearer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

$basePath = dirname(__DIR__);

require_once $basePath.'/app/Support/AutoDeployCacheClearer.php';

AutoDeployCacheClearer::clearWhenGitHeadChanges($basePath);

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            require __DIR__.'/../routes/wells.php';
            require __DIR__.'/../routes/valves.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'admin.allowlisted' => EnsureAdminIsAllowlisted::class,
            'action.permission' => EnsureActionPermission::class,
            'fleet.trip.access' => EnsureFleetTripAccess::class,
            'map.client' => AuthenticateMapClient::class,
            'abilities' => CheckAbilities::class,
            'mobile.principal' => EnsureMobilePrincipal::class,
            'system.access' => EnsureSystemAccess::class,
            'systems.operational' => RejectWhenSystemsSuspended::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
