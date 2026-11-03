<?php

declare(strict_types=1);

namespace App\Modules\Valves\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Valves\Http\Requests\LoginValveInspectorRequest;
use App\Modules\Valves\Services\ValveInspectorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/** POST /api/valves/auth/login — ported from the legacy Api\Valves\AuthController. */
class AuthController extends Controller
{
    public function __construct(private readonly ValveInspectorService $inspectorService) {}

    public function login(LoginValveInspectorRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $inspector = $this->inspectorService->authenticate($validated['phone'], $validated['password']);

        if (! $inspector) {
            throw ValidationException::withMessages(['phone' => ['بيانات الدخول غير صحيحة أو الحساب غير فعال.']]);
        }

        return response()->json($this->inspectorService->issueMobileSession($inspector));
    }
}
