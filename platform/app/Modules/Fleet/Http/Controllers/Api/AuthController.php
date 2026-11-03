<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Http\Requests\SendSmsCodeRequest;
use App\Modules\Fleet\Http\Requests\VerifySmsCodeRequest;
use App\Modules\Fleet\Services\SmsAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** /api/driver/auth/* — ported from the legacy Api\AuthController. */
class AuthController extends Controller
{
    public function sendCode(SendSmsCodeRequest $request, SmsAuthService $auth): JsonResponse
    {
        $auth->sendCode($request->string('phone')->toString());

        return response()->json(['message' => 'تم ارسال رمز التحقق.']);
    }

    public function verify(VerifySmsCodeRequest $request, SmsAuthService $auth): JsonResponse
    {
        return response()->json($auth->verify(
            $request->string('phone')->toString(),
            $request->string('code')->toString(),
            $request->input('device_name'),
        ));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'تم تسجيل الخروج.']);
    }
}
