<?php

declare(strict_types=1);

namespace Tests\Unit\Wells;

use App\Http\Requests\Wells\StoreReadingRequest;
use PHPUnit\Framework\TestCase;

class StoreReadingRequestTest extends TestCase
{
    public function test_pump_hours_reading_is_optional(): void
    {
        $rules = (new StoreReadingRequest)->rules();

        $this->assertContains('nullable', $rules['PUMP_HOURS_READING']);
        $this->assertNotContains('required', $rules['PUMP_HOURS_READING']);
    }
}
