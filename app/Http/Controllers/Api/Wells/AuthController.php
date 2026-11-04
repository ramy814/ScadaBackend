<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wells;

use App\Http\Controllers\Controller;
use App\Models\Wells\Inspector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $inspector = Inspector::active()->where('PHONE', $request->phone)->first();

        if (! $inspector || ! Hash::check($request->password, $inspector->password)) {
            throw ValidationException::withMessages([
                'phone' => ['بيانات الدخول غير صحيحة.'],
            ]);
        }

        $token = $inspector->createToken('wells_app', ['wells'])->plainTextToken;

        // Get the first team for the mobile app format
        $team = $inspector->teams()->first();

        return response()->json([
            'token' => $token,
            'inspector' => [
                'oid' => $inspector->oid,
                'name' => $inspector->name,
                'phone' => $inspector->phone,
                'team_oid' => $team ? $team->oid : null,
            ],
        ]);
    }
}
