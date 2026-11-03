<?php

declare(strict_types=1);

namespace App\Modules\Wells\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Wells\Http\Requests\WellsLoginRequest;
use App\Modules\Wells\Services\WellsMobileService;
use Illuminate\Http\JsonResponse;

/** POST /api/wells/auth/login — ported from the legacy Api\Wells\AuthController. */
class AuthController extends Controller
{
    public function login(WellsLoginRequest $request, WellsMobileService $wells): JsonResponse
    {
        return response()->json($wells->login($request->validated('phone'), $request->validated('password')));
    }
}
