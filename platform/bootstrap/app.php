<?php

use App\Platform\Http\Middleware\EnsureApplicationAccess;
use App\Platform\Http\Middleware\EnsureFleetAction;
use App\Platform\Http\Middleware\EnsurePlatformAdmin;
use App\Platform\Http\Middleware\EnsurePlatformUser;
use App\Platform\Http\Middleware\HandleInertiaRequests;
use App\Platform\Http\Middleware\RejectWhenSuspended;
use App\Platform\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The locale cookie is written by the browser for instant switching.
        $middleware->encryptCookies(except: ['locale']);
        $middleware->web(append: [SetLocale::class, HandleInertiaRequests::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->alias([
            'platform.user' => EnsurePlatformUser::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'platform.operational' => RejectWhenSuspended::class,
            'app.access' => EnsureApplicationAccess::class,
            'fleet.action' => EnsureFleetAction::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
