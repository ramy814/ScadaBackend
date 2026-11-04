<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendSmsCodeRequest;
use App\Http\Requests\VerifySmsCodeRequest;
use App\Services\SmsAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function sendCode(SendSmsCodeRequest $request, SmsAuthService $auth): JsonResponse
    {
        $auth->sendCode($request->string('phone')->toString());

        return response()->json(['message' => 'تم ارسال رمز التحقق.']);
    }

    public function verify(VerifySmsCodeRequest $request, SmsAuthService $auth): JsonResponse
    {
        $result = $auth->verify(
            $request->string('phone')->toString(),
            $request->string('code')->toString(),
            $request->input('device_name'),
        );

        return response()->json($result);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'تم تسجيل الخروج.']);
    }
}

