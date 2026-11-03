<?php

declare(strict_types=1);

namespace App\Modules\Wells\Services;

use App\Modules\Wells\Models\Inspector;
use App\Modules\Wells\Repositories\WellsMobileRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Wells inspector app API, moved out of the legacy Api\Wells controllers with the same payloads. */
class WellsMobileService
{
    public function __construct(
        private readonly WellsMobileRepository $mobile,
        private readonly WellService $wells,
        private readonly ActivityMediaService $media,
    ) {}

    /** POST /api/wells/auth/login (token name `wells_app`, ability `wells`). */
    public function login(string $phone, string $password): array
    {
        $inspector = $this->mobile->activeInspectorByPhone($phone);

        if (! $inspector || ! Hash::check($password, $inspector->password)) {
            throw ValidationException::withMessages(['phone' => ['بيانات الدخول غير صحيحة.']]);
        }

        $token = $inspector->createToken('wells_app', ['wells'])->plainTextToken;
        // First team, as the mobile app expects.
        $team = $inspector->teams()->first();

        return [
            'token' => $token,
            'inspector' => [
                'oid' => $inspector->oid,
                'name' => $inspector->name,
                'phone' => $inspector->phone,
                'team_oid' => $team ? $team->oid : null,
            ],
        ];
    }

    public function wells(Inspector $inspector): array
    {
        return ['wells' => $this->wells->getSyncWellsForInspector((int) $inspector->oid)];
    }

    public function notificationTypes(): array
    {
        return [
            'notification_types' => $this->mobile->activeNotificationTypes()->map(fn ($type): array => [
                'oid' => $type->oid,
                'name' => $type->name,
                'description' => $type->description,
                'is_active' => $type->is_active,
            ]),
        ];
    }

    /** Last 3 readings, diesel inventories and notifications per accessible well. */
    public function history(Inspector $inspector): array
    {
        $readings = [];
        $dieselInventories = [];
        $notifications = [];

        foreach ($this->mobile->accessibleWellIds($inspector) as $wellOid) {
            $readings = array_merge($readings, $this->mobile->latestReadings((int) $wellOid, 3)->toArray());
            $dieselInventories = array_merge($dieselInventories, $this->mobile->latestDieselInventories((int) $wellOid, 3)->toArray());
            $notifications = array_merge($notifications, $this->mobile->latestNotifications((int) $wellOid, 3)->toArray());
        }

        return ['readings' => $readings, 'diesel_inventories' => $dieselInventories, 'notifications' => $notifications];
    }

    /** POST /api/wells/activity-media/sync */
    public function storeActivityMedia(Inspector $inspector, array $validated): array
    {
        abort_unless(count($validated['photos']) === count($validated['photo_local_ids']), 422, 'عدد الصور لا يطابق عدد معرفاتها.');
        abort_unless($this->mobile->activityBelongsTo($validated['activity_type'], (int) $validated['activity_oid'], (int) $inspector->oid), 404);

        $media = $this->media->storeMany($validated['activity_type'], (int) $validated['activity_oid'], $validated['photos'], $validated['photo_local_ids']);

        return [
            'uploaded_ids' => collect($media)
                ->map(fn ($item) => $item->getAttribute('client_local_id') ?? $item->getAttribute('CLIENT_LOCAL_ID'))
                ->values()
                ->all(),
        ];
    }
}
