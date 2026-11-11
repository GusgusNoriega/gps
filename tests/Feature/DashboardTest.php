<?php

namespace Tests\Feature;

use App\Events\FleetUpdated;
use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\Position;
use App\Models\User;
use App\Services\DemoService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([FleetUpdated::class]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    public function test_all_administrator_pages_render(): void
    {
        $device = Device::factory()->create();
        foreach (['/', '/devices', '/enrollments', '/history', '/settings', '/devices/'.$device->id] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_old_location_is_stale_even_if_communication_is_recent(): void
    {
        $device = Device::factory()->create(['last_seen_at' => now()]);
        $position = Position::factory()->create(['device_id' => $device->id, 'recorded_at' => now()->subHour()]);
        $device->update(['latest_position_id' => $position->id]);
        $this->getJson('/dashboard/snapshot')->assertOk()->assertJsonPath('devices.0.status', 'stale')->assertJsonMissingPath('devices.0.tokens')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_history_uses_colombia_time_and_separates_gaps(): void
    {
        $assignment = DeviceAssignment::factory()->create(['starts_at' => '2026-10-27 00:00:00']);
        foreach (['2026-10-28 14:00:00', '2026-10-28 14:01:00', '2026-10-28 14:20:00'] as $date) {
            Position::factory()->create(['device_id' => $assignment->device_id, 'device_assignment_id' => $assignment->id, 'recorded_at' => $date]);
        }
        $query = http_build_query(['device_id' => $assignment->device_id, 'from' => '2026-10-28T09:00', 'to' => '2026-10-28T09:30']);
        $this->getJson('/history/data?'.$query)->assertOk()->assertJsonPath('total', 3)->assertJsonCount(2, 'segments')->assertJsonPath('truncated', false);
    }

    public function test_history_is_bounded_and_asset_filter_keeps_assignments_separate(): void
    {
        $first = DeviceAssignment::factory()->create();
        $second = DeviceAssignment::factory()->create();
        Position::factory()->create(['device_id' => $first->device_id, 'device_assignment_id' => $first->id]);
        Position::factory()->create(['device_id' => $second->device_id, 'device_assignment_id' => $second->id]);
        $query = ['asset_id' => $first->tracked_asset_id, 'from' => now()->timezone('America/Bogota')->startOfDay()->format('Y-m-d\TH:i'), 'to' => now()->timezone('America/Bogota')->format('Y-m-d\TH:i')];
        $this->getJson('/history/data?'.http_build_query($query))->assertOk()->assertJsonPath('total', 1);
        $query['from'] = now()->subDays(10)->format('Y-m-d\TH:i');
        $this->getJson('/history/data?'.http_build_query($query))->assertUnprocessable();
    }

    public function test_disabling_a_device_revokes_credentials_but_preserves_history(): void
    {
        $device = Device::factory()->create();
        $device->createToken('test', ['telemetry:write']);
        Position::factory()->create(['device_id' => $device->id]);
        $this->post('/devices/'.$device->id.'/disable')->assertRedirect();
        $this->assertSame('disabled', $device->fresh()->status);
        $this->assertSame(0, $device->tokens()->count());
        $this->assertDatabaseCount('positions', 1);
        $this->get('/devices/'.$device->id)->assertOk()->assertDontSee('name="asset_name"', false);
        $this->put('/devices/'.$device->id, ['name' => 'Equipo archivado', 'group_name' => 'Histórico'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Equipo archivado', $device->fresh()->name);
    }

    public function test_simulator_cleanup_never_removes_real_devices(): void
    {
        $real = Device::factory()->create();
        Position::factory()->create(['device_id' => $real->id]);
        app(DemoService::class)->seed(4);
        $this->assertSame(5, Device::count());
        app(DemoService::class)->advance();
        app(DemoService::class)->clear();
        $this->assertDatabaseCount('devices', 1);
        $this->assertDatabaseCount('positions', 1);
        $this->assertDatabaseHas('devices', ['id' => $real->id]);
    }

    public function test_demo_endpoint_is_not_available_in_production(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->app->instance('env', 'production');
        $this->postJson('/dashboard/demo', ['action' => 'seed'])->assertNotFound();
    }
}
