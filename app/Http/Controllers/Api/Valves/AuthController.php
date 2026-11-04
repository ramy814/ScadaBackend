<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Valves;

use App\Http\Controllers\Controller;
use App\Http\Requests\Valves\LoginValveInspectorRequest;
use App\Services\Valves\ValveInspectorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly ValveInspectorService $inspectorService,
    ) {}

    public function login(LoginValveInspectorRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $inspector = $this->inspectorService->authenticate(
            $validated['phone'],
            $validated['password'],
        );

        if (! $inspector) {
            throw ValidationException::withMessages([
                'phone' => ['بيانات الدخول غير صحيحة أو الحساب غير فعال.'],
            ]);
        }

        $token = $inspector->createToken('valves_app', ['scada'])->plainTextToken;
        $teams = $inspector->teams()->get(['valve_teams.oid', 'valve_teams.NAME', 'valve_teams.ASSIGN_ALL_VALVES']);

        return response()->json([
            'token' => $token,
            'inspector' => [
                'oid' => $inspector->oid,
                'name' => $inspector->name,
                'phone' => $inspector->phone,
                'teams' => $teams->map(fn ($team): array => [
                    'oid' => $team->oid,
                    'name' => $team->name,
                    'assign_all_valves' => (bool) ($team->assign_all_valves ?? $team->ASSIGN_ALL_VALVES ?? false),
                ])->values(),
            ],
        ]);
    }
}
