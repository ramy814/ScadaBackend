<?php

namespace App\Services;

use App\Models\SmsLoginCode;
use App\Repositories\MunicipalityDriverRepository;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Hash;

class SmsAuthService
{
    public function __construct(private readonly MunicipalityDriverRepository $drivers) {}

    public function sendCode(string $phone): void
    {
        $beneficiary = $this->drivers->findBeneficiaryByPhone($phone);

        if (! $beneficiary) {
            if (request()->header('X-Driver-Debug') === 'lookup') {
                throw new HttpResponseException(response()->json([
                    'message' => 'رقم الجوال غير مسجل ضمن المنتفعين.',
                    'debug' => $this->drivers->debugPhoneLookup($phone),
                ], 403));
            }

            abort(403, 'رقم الجوال غير مسجل ضمن المنتفعين.');
        }

        $code = (string) random_int(100000, 999999);

        SmsLoginCode::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        // TODO: Integrate the municipality SMS provider. In local development,
        // inspect the latest sms_login_codes row and use a test override.
    }

    public function verify(string $phone, string $code, ?string $deviceName): array
    {
        $loginCode = SmsLoginCode::query()
            ->where('phone', $phone)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->firstOrFail();

        $isLocalTestCode = app()->environment('local') && $code === config('services.sms.test_code', '000000');

        abort_unless($isLocalTestCode || Hash::check($code, $loginCode->code_hash), 422, 'رمز التحقق غير صحيح.');

        $loginCode->update(['consumed_at' => now()]);

        $beneficiary = $this->drivers->findBeneficiaryByPhone($phone);

        abort_unless($beneficiary, 403, 'رقم الجوال غير مسجل ضمن المنتفعين.');

        $token = $beneficiary->createToken($deviceName ?: 'driver-android')->plainTextToken;

        return ['token' => $token, 'user' => $beneficiary->toAuthPayload()];
    }
}
