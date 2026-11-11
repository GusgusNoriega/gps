<?php

namespace Tests\Feature;

use App\Events\FleetUpdated;
use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([FleetUpdated::class]);
    }

    private function enrollment(): array
    {
        return $this->postJson('/api/v1/enrollments', ['installation_id' => Str::uuid()->toString(), 'name' => 'Teléfono de prueba', 'platform' => 'android', 'model' => 'Pixel de prueba'])->assertCreated()->json();
    }

    public function test_an_installation_must_be_approved_before_getting_a_device_token(): void
    {
        $request = $this->enrollment();
        $url = '/api/v1/enrollments/'.$request['enrollment_id'];
        $this->getJson($url)->assertUnauthorized();
        $this->withToken($request['enrollment_secret'])->getJson($url)->assertOk()->assertJsonPath('status', 'pending')->assertJsonMissingPath('secret_hash');
        $this->withToken($request['enrollment_secret'])->postJson($url.'/exchange')->assertConflict();
        $device = app(EnrollmentService::class)->approve(Enrollment::findOrFail($request['enrollment_id']));
        $token = $this->withToken($request['enrollment_secret'])->postJson($url.'/exchange')->assertOk()->json('access_token');
        $this->withToken($request['enrollment_secret'])->postJson($url.'/exchange')->assertConflict();
        $this->withToken($token)->getJson('/api/v1/configuration')->assertOk()->assertJsonPath('name', $device->name)->assertJsonMissingPath('position');
        $this->assertDatabaseCount('devices', 1);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_closed_registration_rejects_new_installations(): void
    {
        Setting::current()->update(['enrollment_open' => false]);
        $this->postJson('/api/v1/enrollments', ['installation_id' => Str::uuid(), 'name' => 'Test', 'platform' => 'android'])->assertForbidden();
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_polling_limits_do_not_block_other_installations_on_the_same_network(): void
    {
        $first = $this->enrollment();
        $second = $this->enrollment();
        for ($index = 0; $index < 30; $index++) {
            $this->withToken($first['enrollment_secret'])->getJson('/api/v1/enrollments/'.$first['enrollment_id'])->assertOk();
        }
        $this->withToken($first['enrollment_secret'])->getJson('/api/v1/enrollments/'.$first['enrollment_id'])->assertTooManyRequests();
        $this->withToken($second['enrollment_secret'])->getJson('/api/v1/enrollments/'.$second['enrollment_id'])->assertOk();
    }

    public function test_expired_or_rejected_installations_cannot_exchange_credentials(): void
    {
        $enrollment = Enrollment::factory()->create(['secret_hash' => hash('sha256', 'secret'), 'expires_at' => now()->subMinute()]);
        $this->withToken('secret')->postJson('/api/v1/enrollments/'.$enrollment->id.'/exchange')->assertGone();
        $enrollment->update(['status' => 'rejected', 'expires_at' => now()->addHour()]);
        $this->withToken('secret')->postJson('/api/v1/enrollments/'.$enrollment->id.'/exchange')->assertConflict();
    }

    public function test_pending_secret_cannot_send_locations(): void
    {
        $enrollment = $this->enrollment();
        $this->withToken($enrollment['enrollment_secret'])->postJson('/api/v1/positions', ['positions' => []])->assertUnauthorized();
    }

    public function test_duplicate_installation_ids_do_not_overwrite_an_existing_request(): void
    {
        $installationId = Str::uuid()->toString();
        $data = ['installation_id' => $installationId, 'name' => 'Original', 'platform' => 'android'];
        $this->postJson('/api/v1/enrollments', $data)->assertCreated();
        $this->postJson('/api/v1/enrollments', array_merge($data, ['name' => 'Impostor']))->assertUnprocessable();
        $this->assertDatabaseHas('enrollments', ['installation_id' => $installationId, 'name' => 'Dispositivo '.strtoupper(substr(str_replace('-', '', $installationId), 0, 8))]);
    }

    public function test_mobile_installations_receive_a_server_assigned_name(): void
    {
        $installationId = '12345678-1234-4234-8234-123456789abc';
        $response = $this->postJson('/api/v1/enrollments', ['installation_id' => $installationId, 'platform' => 'ios', 'model' => 'iPhone'])->assertCreated();
        $this->assertDatabaseHas('enrollments', ['id' => $response->json('enrollment_id'), 'name' => 'Dispositivo 12345678']);

        $this->postJson('/api/v1/enrollments', ['installation_id' => '87654321-1234-4234-8234-123456789abc', 'platform' => 'android', 'name' => 'Nombre elegido en el teléfono'])->assertCreated();
        $this->assertDatabaseHas('enrollments', ['installation_id' => '87654321-1234-4234-8234-123456789abc', 'name' => 'Dispositivo 87654321']);
    }

    public function test_administrator_assigns_the_device_name_when_approving(): void
    {
        $enrollment = Enrollment::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->post('/enrollments/'.$enrollment->id.'/approve', ['name' => 'Teléfono de reparto'])->assertRedirect();

        $device = Device::findOrFail($enrollment->fresh()->device_id);
        $this->assertSame('Teléfono de reparto', $device->name);
        $this->assertSame('Teléfono de reparto', $device->currentAssignment->asset->name);
        $this->assertSame('Teléfono de reparto', $enrollment->fresh()->name);
    }

    public function test_mobile_configuration_reflects_name_changes_from_the_system(): void
    {
        $device = Device::factory()->create(['name' => 'Teléfono de reparto']);
        $token = $device->createToken('installation', ['telemetry:write'])->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/configuration')->assertOk()->assertJsonPath('name', 'Teléfono de reparto');

        $device->update(['name' => 'Teléfono de bodega']);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/configuration')->assertOk()->assertJsonPath('name', 'Teléfono de bodega');
    }

    public function test_replacement_preserves_assignments_and_revokes_the_previous_device(): void
    {
        $assignment = DeviceAssignment::factory()->create();
        $oldDevice = $assignment->device;
        $oldDevice->createToken('test', ['telemetry:write']);
        $enrollment = Enrollment::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/enrollments/'.$enrollment->id.'/approve', ['asset_id' => $assignment->tracked_asset_id])->assertRedirect();
        $this->assertSame('disabled', $oldDevice->fresh()->status);
        $this->assertNotNull($assignment->fresh()->ends_at);
        $this->assertSame(0, $oldDevice->tokens()->count());
        $this->assertSame(2, DeviceAssignment::where('tracked_asset_id', $assignment->tracked_asset_id)->count());
        $this->assertSame(2, Device::count());
    }
}
