<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ServerConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_prepare_hosting_settings_without_changing_the_local_installation(): void
    {
        config(['app.url' => 'http://gps.test', 'broadcasting.default' => 'reverb', 'queue.default' => 'database']);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->post('/settings/server/preview', ['server_url' => 'https://GPS.Example.com/'])
            ->assertRedirect(route('settings').'#server-configuration')
            ->assertSessionHas('server_setup.url', 'https://gps.example.com')
            ->assertSessionHas('server_setup.api_url', 'https://gps.example.com/api/v1')
            ->assertSessionHas('server_setup.environment', "APP_ENV=production\nAPP_DEBUG=false\nAPP_URL=https://gps.example.com\nSESSION_SECURE_COOKIE=true\nBROADCAST_CONNECTION=null\nQUEUE_CONNECTION=sync");

        $this->get('/settings')->assertOk()
            ->assertSee('https://gps.example.com/api/v1')
            ->assertSee('Pendiente de aplicar en el hosting.')
            ->assertSee('http://gps.test/api/v1');
        $this->assertSame('http://gps.test', config('app.url'));
        $this->assertSame('reverb', config('broadcasting.default'));
        $this->assertSame('database', config('queue.default'));
    }

    #[DataProvider('invalidServerUrls')]
    public function test_server_address_must_be_an_https_domain_without_extra_components(string $serverUrl): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->post('/settings/server/preview', ['server_url' => $serverUrl])
            ->assertSessionHasErrors('server_url')
            ->assertSessionMissing('server_setup');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidServerUrls(): array
    {
        return [
            'empty' => [''],
            'http' => ['http://gps.example.com'],
            'missing scheme' => ['gps.example.com'],
            'path' => ['https://gps.example.com/api/v1'],
            'query' => ['https://gps.example.com?debug=true'],
            'fragment' => ['https://gps.example.com#map'],
            'credentials' => ['https://user:password@gps.example.com'],
            'port' => ['https://gps.example.com:8086'],
            'local host' => ['https://localhost'],
            'environment injection' => ["https://gps.example.com\nAPP_DEBUG=true"],
            'markup' => ['https://gps.example.com/<script>alert(1)</script>'],
        ];
    }

    public function test_only_administrators_can_prepare_server_settings(): void
    {
        $payload = ['server_url' => 'https://gps.example.com'];
        $this->postJson('/settings/server/preview', $payload)->assertUnauthorized();
        $device = Device::factory()->create();
        $this->withToken($device->createToken('test', ['telemetry:write'])->plainTextToken)
            ->postJson('/settings/server/preview', $payload)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson('/settings/server/preview', $payload)->assertForbidden();
    }

    public function test_browser_receives_current_connection_settings_without_server_secrets(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'public-key',
            'broadcasting.connections.reverb.secret' => 'private-server-secret',
            'reverb.client.host' => 'gps.example.com',
            'reverb.client.port' => 443,
            'reverb.client.scheme' => 'https',
        ]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get('/')->assertOk()
            ->assertSee('"host":"gps.example.com"', false)
            ->assertSee('"key":"public-key"', false)
            ->assertSee('"port":443', false)
            ->assertSee('"scheme":"https"', false)
            ->assertDontSee('private-server-secret');

        config(['reverb.client.host' => 'otro.example.com']);
        $this->get('/')->assertOk()->assertSee('"host":"otro.example.com"', false);

        config(['broadcasting.default' => 'null']);
        $this->get('/')->assertOk()->assertSee('"reverb":null', false)->assertDontSee('public-key');
    }

    public function test_hosting_mode_receives_locations_and_exposes_them_to_the_admin_without_reverb(): void
    {
        config(['broadcasting.default' => null, 'queue.default' => 'sync']);
        $device = DeviceAssignment::factory()->create()->device;
        $token = $device->createToken('test', ['telemetry:write'])->plainTextToken;
        $eventId = Str::uuid()->toString();
        $this->withToken($token)->postJson('/api/v1/positions', ['positions' => [[
            'event_id' => $eventId,
            'recorded_at' => now()->toIso8601String(),
            'latitude' => 4.65,
            'longitude' => -74.08,
            'accuracy' => 10,
        ]]])->assertOk()->assertJsonPath('accepted', 1);

        $this->assertDatabaseHas('positions', ['event_id' => $eventId, 'device_id' => $device->id]);
        $this->assertDatabaseCount('jobs', 0);
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'web')->getJson('/dashboard/snapshot')
            ->assertOk()->assertJsonPath('devices.0.position.latitude', 4.65);
    }
}
