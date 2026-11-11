<?php

namespace Tests\Feature;

use App\Events\FleetUpdated;
use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class TelemetryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([FleetUpdated::class]);
    }

    private function deviceToken(Device $device): string
    {
        return $device->createToken('test', ['telemetry:write'])->plainTextToken;
    }

    private function point(array $overrides = []): array
    {
        return array_merge(['event_id' => Str::uuid()->toString(), 'recorded_at' => now()->toIso8601String(), 'latitude' => 4.65, 'longitude' => -74.08, 'accuracy' => 10, 'battery' => 80], $overrides);
    }

    public function test_locations_require_authentication(): void
    {
        $this->postJson('/api/v1/positions', ['positions' => [$this->point()]])->assertUnauthorized();
    }

    public function test_retries_are_idempotent_and_delayed_points_do_not_replace_latest_position(): void
    {
        $assignment = DeviceAssignment::factory()->create();
        $device = $assignment->device;
        $token = $this->deviceToken($device);
        $new = $this->point();
        $old = $this->point(['recorded_at' => now()->subHour()->toIso8601String(), 'latitude' => 4.7]);
        $this->withToken($token)->postJson('/api/v1/positions', ['positions' => [$new, $old]])->assertOk()->assertJsonPath('accepted', 2);
        $this->withToken($token)->postJson('/api/v1/positions', ['positions' => [$new, $old]])->assertOk()->assertJsonPath('accepted', 0)->assertJsonPath('results.0.status', 'duplicate');
        $this->assertDatabaseCount('positions', 2);
        $this->assertSame($new['event_id'], $device->fresh()->latestPosition->event_id);
        $this->assertSame($assignment->id, Position::first()->device_assignment_id);
        Event::assertDispatched(FleetUpdated::class);
    }

    public function test_a_device_cannot_attribute_its_point_to_another_device(): void
    {
        $device = Device::factory()->create();
        $other = Device::factory()->create();
        $this->withToken($this->deviceToken($device))->postJson('/api/v1/positions', ['device_id' => $other->id, 'positions' => [$this->point(['device_id' => $other->id])]])->assertOk();
        $this->assertSame($device->id, Position::first()->device_id);
        $this->assertSame(0, $other->positions()->count());
    }

    public function test_invalid_coordinates_future_dates_and_missing_timezone_are_rejected_per_point(): void
    {
        $device = Device::factory()->create();
        $this->withToken($this->deviceToken($device))->postJson('/api/v1/positions', ['positions' => [$this->point(['latitude' => 100]), $this->point(['recorded_at' => now()->addHour()->toIso8601String()]), $this->point(['recorded_at' => now()->format('Y-m-d H:i:s')]), $this->point()]])->assertOk()->assertJsonPath('accepted', 1)->assertJsonPath('results.0.status', 'rejected')->assertJsonPath('results.1.status', 'rejected')->assertJsonPath('results.2.status', 'rejected');
        $this->assertDatabaseCount('positions', 1);
    }

    public function test_poor_accuracy_is_saved_but_not_used_as_latest_position(): void
    {
        $device = Device::factory()->create();
        $point = $this->point(['accuracy' => 1000, 'battery' => null]);
        $this->withToken($this->deviceToken($device))->postJson('/api/v1/positions', ['positions' => [$point]])->assertOk()->assertJsonPath('results.0.quality', 'low_accuracy');
        $this->assertNull($device->fresh()->latest_position_id);
        $this->assertNull(Position::first()->battery);
        $this->assertFalse(Position::first()->is_usable);
    }

    public function test_a_conflicting_duplicate_is_rejected(): void
    {
        $device = Device::factory()->create();
        $point = $this->point();
        $this->withToken($this->deviceToken($device))->postJson('/api/v1/positions', ['positions' => [$point]])->assertOk();
        $point['latitude'] = 4.9;
        $this->postJson('/api/v1/positions', ['positions' => [$point]])->assertOk()->assertJsonPath('results.0.reason', 'event_conflict');
        $this->assertSame(4.65, Position::first()->latitude);
    }

    public function test_disabled_device_and_incorrect_token_scope_are_rejected(): void
    {
        $disabled = Device::factory()->create(['status' => 'disabled']);
        $this->withToken($this->deviceToken($disabled))->postJson('/api/v1/positions', ['positions' => [$this->point()]])->assertForbidden();
        auth()->forgetGuards();
        $device = Device::factory()->create();
        $token = $device->createToken('wrong', ['nothing'])->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/positions', ['positions' => [$this->point()]])->assertForbidden();
        $this->assertDatabaseCount('positions', 0);
    }

    public function test_batch_size_is_bounded(): void
    {
        $device = Device::factory()->create();
        $this->withToken($this->deviceToken($device))->postJson('/api/v1/positions', ['positions' => array_fill(0, 201, $this->point())])->assertUnprocessable();
    }

    public function test_delayed_heartbeat_does_not_overwrite_the_newer_state_or_create_a_location(): void
    {
        $device = Device::factory()->create();
        $this->withToken($this->deviceToken($device))->postJson('/api/v1/heartbeat', ['tracking_state' => 'stopped', 'recorded_at' => now()->toIso8601String()])->assertOk();
        $this->postJson('/api/v1/heartbeat', ['tracking_state' => 'active', 'recorded_at' => now()->subHour()->toIso8601String()])->assertOk();
        $this->assertSame('stopped', $device->fresh()->tracking_state);
        $this->assertNotNull($device->fresh()->last_seen_at);
        $this->assertDatabaseCount('positions', 0);
    }
}
