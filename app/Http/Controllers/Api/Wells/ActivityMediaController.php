<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wells;

use App\Http\Controllers\Controller;
use App\Models\Wells\WellNotification;
use App\Models\Wells\WellReading;
use App\Services\Wells\ActivityMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivityMediaController extends Controller
{
    public function __construct(private readonly ActivityMediaService $service) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'activity_type' => [
                'required',
                Rule::in([
                    ActivityMediaService::TYPE_READING,
                    ActivityMediaService::TYPE_NOTIFICATION,
                ]),
            ],
            'activity_oid' => ['required', 'integer'],
            'photos' => ['required', 'array', 'min:1', 'max:4'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'photo_local_ids' => ['required', 'array', 'min:1', 'max:4'],
            'photo_local_ids.*' => ['required', 'uuid', 'distinct'],
        ]);

        abort_unless(
            count($validated['photos']) === count($validated['photo_local_ids']),
            422,
            'عدد الصور لا يطابق عدد معرفاتها.',
        );

        $this->authorizeActivity(
            $validated['activity_type'],
            (int) $validated['activity_oid'],
            (int) $request->user()->oid,
        );

        $media = $this->service->storeMany(
            $validated['activity_type'],
            (int) $validated['activity_oid'],
            $validated['photos'],
            $validated['photo_local_ids'],
        );

        return response()->json([
            'uploaded_ids' => collect($media)
                ->map(fn ($item) => $item->getAttribute('client_local_id')
                    ?? $item->getAttribute('CLIENT_LOCAL_ID'))
                ->values()
                ->all(),
        ]);
    }

    private function authorizeActivity(
        string $activityType,
        int $activityId,
        int $inspectorId,
    ): void {
        $query = match ($activityType) {
            ActivityMediaService::TYPE_READING => WellReading::query(),
            ActivityMediaService::TYPE_NOTIFICATION => WellNotification::query(),
        };

        abort_unless(
            $query
                ->where('OID', $activityId)
                ->where('INSPECTOR_OID', $inspectorId)
                ->exists(),
            404,
        );
    }
}
