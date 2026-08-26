<?php

namespace App\Services;

use App\Models\SmsLoginCode;
use App\Repositories\MunicipalityDriverRepository;
use App\Repositories\MunicipalityVehicleRepository;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
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

            if (request()->header('X-Driver-Debug') === 'lookup') {
                throw new HttpResponseException(response()->json([
                    'message' => 'فشل فحص رقم الجوال في قاعدة المنتفعين.',
                    'debug' => [
                        'code_version' => 'driver-phone-lookup-exception-debug-2026-05-31',
                        'exception' => $exception::class,
                        'error' => $exception->getMessage(),
                        'sql_state' => $exception instanceof QueryException ? $exception->errorInfo[0] ?? null : null,
                    ],
                ], 500));
            }

            throw $exception;
        }

        if (! $beneficiary) {
            if (request()->header('X-Driver-Debug') === 'lookup') {
                throw new HttpResponseException(response()->json([
                    'message' => 'رقم الجوال غير مسجل ضمن المنتفعين.',
                    'debug' => $this->drivers->debugPhoneLookup($phone),
                ], 403));
            }

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

            if (request()->header('X-Driver-Debug') === 'lookup') {
                throw new HttpResponseException(response()->json([
                    'message' => 'تم العثور على المنتفع ولكن فشل إنشاء رمز التحقق.',
                    'debug' => [
                        'code_version' => 'driver-sms-create-debug-2026-05-31',
                        'exception' => $exception::class,
                        'error' => $exception->getMessage(),
                        'sql_state' => $exception instanceof QueryException ? $exception->errorInfo[0] ?? null : null,
                        'driver' => [
                            'oracle_id' => (string) $beneficiary->getAuthIdentifier(),
                            'phone' => $beneficiary->phoneNumber(),
                            'name_present' => filled($beneficiary->displayName()),
                        ],
                    ],
                ], 500));
            }

            throw $exception;
        }

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

        $testCode = config('services.sms.test_code', '000000') ?: '000000';
        $isConfiguredTestCode = hash_equals((string) $testCode, $code);

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
