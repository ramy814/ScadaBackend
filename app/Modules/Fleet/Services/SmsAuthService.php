<?php

namespace App\Modules\Fleet\Services;

use App\Modules\Fleet\Models\SmsLoginCode;
use App\Modules\Fleet\Repositories\MunicipalityDriverRepository;
use App\Modules\Fleet\Repositories\MunicipalityVehicleRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class SmsAuthService
{
    public function __construct(
        private readonly MunicipalityDriverRepository $drivers,
        private readonly MunicipalityVehicleRepository $vehicles
    ) {}

    public function sendCode(string $phone): void
    {
        try {
            $beneficiary = $this->drivers->findBeneficiaryByPhone($phone);
        } catch (\Throwable $exception) {
            Log::error('Failed to lookup driver beneficiary phone.', [
                'phone_suffix' => substr(preg_replace('/\D+/', '', $phone) ?? '', -4),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        if (! $beneficiary) {
            abort(403, 'رقم الجوال غير مسجل ضمن المنتفعين.');
        }

        $driverVehicles = collect()
            ->merge($this->vehicles->findByDriverOracleId($beneficiary->getAuthIdentifier()))
            ->merge($this->vehicles->findByDriverMobile($phone))
            ->unique('plate_number');

        abort_unless($driverVehicles->isNotEmpty(), 403, 'لا يوجد مركبات لهذا الرقم');

        $code = (string) random_int(100000, 999999);

        try {
            SmsLoginCode::create([
                'phone' => $phone,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(10),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Failed to create driver SMS login code.', [
                'phone_suffix' => substr(preg_replace('/\D+/', '', $phone) ?? '', -4),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        // No municipality SMS provider is integrated yet (legacy has the same gap): the code is
        // stored hashed but not sent. See docs/05-cutover-plan.md §3.1 and SMS_TEST_CODE.
    }

    public function verify(string $phone, string $code, ?string $deviceName): array
    {
        $loginCode = SmsLoginCode::query()
            ->where('phone', $phone)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->firstOrFail();

        // Legacy accepted a hard-coded '000000' when SMS_TEST_CODE was empty. Here the fixed
        // code works only when explicitly configured on the server (no SMS provider yet).
        $testCode = (string) config('services.sms.test_code', '');
        $isConfiguredTestCode = $testCode !== '' && hash_equals($testCode, $code);

        abort_unless($isConfiguredTestCode || Hash::check($code, $loginCode->code_hash), 422, 'رمز التحقق غير صحيح.');

        $loginCode->update(['consumed_at' => now()]);

        $beneficiary = $this->drivers->findBeneficiaryByPhone($phone);

        abort_unless($beneficiary, 403, 'رقم الجوال غير مسجل ضمن المنتفعين.');

        $driverVehicles = collect()
            ->merge($this->vehicles->findByDriverOracleId($beneficiary->getAuthIdentifier()))
            ->merge($this->vehicles->findByDriverMobile($phone))
            ->unique('plate_number');

        abort_unless($driverVehicles->isNotEmpty(), 403, 'لا يوجد مركبات لهذا الرقم');

        $token = $beneficiary->createToken($deviceName ?: 'driver-android', ['driver'])->plainTextToken;

        return ['token' => $token, 'user' => $beneficiary->toAuthPayload()];
    }
}
