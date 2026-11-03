<?php

declare(strict_types=1);

namespace Tests\Unit\Wells;

use App\Modules\Wells\Http\Requests\UpdateNotificationTypeRequest;
use App\Modules\Wells\Models\NotificationType;
use App\Modules\Wells\Repositories\NotificationTypeRepository;
use App\Modules\Wells\Services\NotificationTypeService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Mockery;
use Tests\TestCase;

class NotificationTypeServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_used_notification_type_can_still_be_updated(): void
    {
        $updatedType = new NotificationType;
        $repository = Mockery::mock(NotificationTypeRepository::class);
        $repository->shouldReceive('update')
            ->once()
            ->with(12, [
                'NAME' => 'عطل بحاجة إلى متابعة',
                'ICON' => 'repair',
                'EMOJI' => 'repair',
                'COLOR' => '#ef4444',
                'PRIORITY' => 2,
                'IS_ACTIVE' => 1,
            ])
            ->andReturn($updatedType);
        $repository->shouldNotReceive('hasNotifications');

        $result = (new NotificationTypeService($repository))->updateType(12, [
            'NAME' => 'عطل بحاجة إلى متابعة',
            'ICON' => 'repair',
            'EMOJI' => 'repair',
            'COLOR' => '#ef4444',
            'PRIORITY' => 2,
            'IS_ACTIVE' => 1,
        ]);

        self::assertSame($updatedType, $result);
    }

    public function test_paginated_types_only_disable_deletion_when_used(): void
    {
        $usedType = Mockery::mock(NotificationType::class)->makePartial();
        $usedType->shouldReceive('toArray')
            ->once()
            ->andReturn(['OID' => 12, 'NAME' => 'عطل']);
        $usedType->shouldReceive('hasBeenUsed')->once()->andReturnTrue();

        $freeType = Mockery::mock(NotificationType::class)->makePartial();
        $freeType->shouldReceive('toArray')
            ->once()
            ->andReturn(['OID' => 13, 'NAME' => 'متابعة']);
        $freeType->shouldReceive('hasBeenUsed')->once()->andReturnFalse();

        $repository = Mockery::mock(NotificationTypeRepository::class);
        $repository->shouldReceive('paginate')
            ->once()
            ->with(20)
            ->andReturn(new LengthAwarePaginator(
                [$usedType, $freeType],
                2,
                20,
            ));

        $types = (new NotificationTypeService($repository))
            ->getPaginatedTypes(20)
            ->items();

        self::assertFalse($types[0]['CAN_DELETE']);
        self::assertTrue($types[1]['CAN_DELETE']);
    }

    public function test_update_validation_accepts_safe_icon_codes_only(): void
    {
        $request = new UpdateNotificationTypeRequest;
        $payload = [
            'NAME' => 'عطل بحاجة إلى متابعة',
            'ICON' => 'repair',
            'EMOJI' => 'repair',
            'COLOR' => '#ef4444',
            'PRIORITY' => 2,
            'IS_ACTIVE' => 1,
        ];

        self::assertFalse(Validator::make($payload, $request->rules())->fails());

        $payload['ICON'] = '🔔';
        self::assertTrue(Validator::make($payload, $request->rules())->fails());
    }
}
