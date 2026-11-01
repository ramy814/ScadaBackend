<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Fleet\Services\FleetLiveService;
use Tests\TestCase;

class FleetLiveHealthTest extends TestCase
{
    public function test_health_follows_the_legacy_90_second_and_event_rules(): void
    {
        $live = app(FleetLiveService::class);

        $this->assertSame('live', $live->health(null, now()->subSeconds(60)->toIso8601String(), 0));
        $this->assertSame('stale', $live->health(null, now()->subSeconds(120)->toIso8601String(), 0));
        $this->assertSame('waiting', $live->health(null, null, 0));
        $this->assertSame('incident', $live->health('gps_disabled', now()->toIso8601String(), 0));
        $this->assertSame('incident', $live->health(null, now()->toIso8601String(), 2));
        $this->assertSame('stale', $live->health('tracking_error', now()->toIso8601String(), 0));
    }
}
