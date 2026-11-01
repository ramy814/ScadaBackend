<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminIsAllowlisted;
use App\Http\Middleware\EnsureFleetTripAccess;
use App\Http\Middleware\EnsureSystemAccess;
use App\Http\Middleware\RejectWhenSystemsSuspended;
use App\Models\MogSsoUser;
use App\Repositories\TripRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TripReviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('trips', function (Blueprint $table): void {
            $table->id();
            $table->string('vehicle_plate');
            $table->string('driver_name');
            $table->string('status')->nullable();
            $table->string('review_status')->nullable();
            $table->text('review_note')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('clarification_response')->nullable();
            $table->string('entry_source')->nullable()->default('mobile');
            $table->string('oracle_vehicle_id')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('actor');
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('trips');

        parent::tearDown();
    }

    public function test_second_decision_on_approved_trip_is_rejected_and_leaves_one_audit_row(): void
    {
        $this->insertTrip(90);
        $this->actAsSupervisor();

        $this->post('/admin/trips/90/approve', ['note' => 'سليمة'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        self::assertSame('approved', DB::table('trips')->where('id', 90)->value('review_status'));

        foreach (['reject', 'clarify'] as $action) {
            $this->post("/admin/trips/90/{$action}", ['note' => 'تغيير القرار'])
                ->assertRedirect()
                ->assertSessionHasErrors('review');
        }

        $trip = DB::table('trips')->where('id', 90)->first();
        self::assertSame('approved', $trip->review_status);
        self::assertSame('سليمة', $trip->review_note);

        $audits = DB::table('audit_logs')->where('entity_type', 'trip')->where('entity_id', 90)->get();
        self::assertCount(1, $audits);
        self::assertSame('trip.reviewed', $audits[0]->event_type);
        self::assertSame('140', $audits[0]->actor);
        self::assertSame(['review_status' => 'approved', 'note' => 'سليمة'], json_decode($audits[0]->payload, true));
    }

    public function test_rejected_trip_is_final(): void
    {
        $this->insertTrip(91, ['review_status' => 'rejected']);

        $this->assertDecisionRefused(91, 'approved');
    }

    public function test_unfinished_and_draft_trips_cannot_be_decided(): void
    {
        $this->insertTrip(92, ['status' => 'uploaded']);
        $this->insertTrip(93, ['ended_at' => null]);
        $this->insertTrip(94, ['entry_source' => 'manual', 'review_status' => 'draft']);

        foreach ([92, 93, 94] as $tripId) {
            $this->assertDecisionRefused($tripId, 'approved');
        }
    }

    public function test_manual_trip_cannot_be_sent_to_clarification(): void
    {
        $this->insertTrip(95, ['entry_source' => 'manual', 'review_status' => 'pending']);
        $this->actAsSupervisor();

        $this->post('/admin/trips/95/clarify', ['note' => 'سؤال'])->assertStatus(422);
        $this->assertDecisionRefused(95, 'needs_clarification');
    }

    public function test_final_note_after_clarification_keeps_the_question(): void
    {
        $this->insertTrip(96, [
            'review_status' => 'needs_clarification',
            'review_note' => 'لماذا التوقف الطويل؟',
            'clarification_response' => 'ازدحام',
        ]);

        $trip = app(TripRepository::class)->decide(96, 'approved', 'مقبول', '140');

        self::assertSame('approved', $trip->review_status);
        self::assertSame("الإجراء النهائي: مقبول\n---\nسؤال التوضيح: لماذا التوقف الطويل؟", $trip->review_note);
        self::assertSame('مقبول', json_decode(DB::table('audit_logs')->value('payload'), true)['note']);
    }

    public function test_missing_note_leaves_stored_note_unchanged(): void
    {
        $this->insertTrip(97, ['review_status' => 'needs_clarification', 'review_note' => 'سؤال سابق']);

        $trip = app(TripRepository::class)->decide(97, 'rejected', null, '140', noteProvided: false);

        self::assertSame('rejected', $trip->review_status);
        self::assertSame('سؤال سابق', $trip->review_note);
        self::assertSame('(unchanged)', json_decode(DB::table('audit_logs')->value('payload'), true)['note']);
    }

    private function assertDecisionRefused(int $tripId, string $status): void
    {
        $before = DB::table('trips')->where('id', $tripId)->first();

        try {
            app(TripRepository::class)->decide($tripId, $status, 'ملاحظة', '140');
            self::fail("Decision {$status} on trip {$tripId} should have been refused.");
        } catch (ValidationException $e) {
            self::assertArrayHasKey('review', $e->errors());
        }

        self::assertEquals($before, DB::table('trips')->where('id', $tripId)->first());
        self::assertSame(0, DB::table('audit_logs')->where('entity_id', $tripId)->count());
    }

    private function actAsSupervisor(): void
    {
        $this->withoutMiddleware([
            EnsureAdminIsAllowlisted::class,
            RejectWhenSystemsSuspended::class,
            EnsureSystemAccess::class,
            EnsureFleetTripAccess::class,
        ]);
        $this->actingAs((new MogSsoUser)->forceFill(['id' => 140, 'full_name' => 'Supervisor']), 'admin');
    }

    /** @param array<string, mixed> $overrides */
    private function insertTrip(int $id, array $overrides = []): void
    {
        DB::table('trips')->insert(array_merge([
            'id' => $id,
            'vehicle_plate' => 'TEST',
            'driver_name' => 'Driver',
            'status' => 'confirmed',
            'review_status' => 'pending',
            'entry_source' => 'mobile',
            'oracle_vehicle_id' => '501',
            'started_at' => now()->subHour(),
            'ended_at' => now(),
        ], $overrides));
    }
}
