<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_read_tracking_data(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->getJson('/dashboard/snapshot')->assertUnauthorized();
        $this->getJson('/history/data')->assertUnauthorized();
        $this->get('/register')->assertNotFound();
    }

    public function test_non_administrators_cannot_log_in_or_access_the_panel(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($user)->get('/')->assertForbidden();
        $this->actingAs($user)->getJson('/dashboard/snapshot')->assertForbidden();
    }

    public function test_administrator_can_log_in_and_logout(): void
    {
        $user = User::factory()->create(['is_admin' => true, 'password' => Hash::make('a-secure-password-123')]);
        $this->post('/login', ['email' => $user->email, 'password' => 'a-secure-password-123'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->get('/')->assertOk()->assertSee('Mapa en vivo');
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_device_token_cannot_read_any_administrator_endpoint(): void
    {
        $device = Device::factory()->create();
        $token = $device->createToken('test', ['telemetry:write'])->plainTextToken;
        foreach (['/', '/dashboard/snapshot', '/history/data', '/devices', '/settings', '/enrollments'] as $url) {
            $this->withToken($token)->getJson($url)->assertUnauthorized();
        }
        $this->withToken($token)->postJson('/broadcasting/auth', ['channel_name' => 'private-fleet', 'socket_id' => '123.456'])->assertUnauthorized();
    }

    public function test_web_session_cannot_impersonate_a_device(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->getJson('/api/v1/configuration')->assertForbidden();
    }

    public function test_private_channel_requires_an_administrator(): void
    {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key', 'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test-app']);
        $this->actingAs(User::factory()->create())->postJson('/broadcasting/auth', ['channel_name' => 'private-fleet', 'socket_id' => '123.456'])->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->postJson('/broadcasting/auth', ['channel_name' => 'private-fleet', 'socket_id' => '123.456'])->assertOk()->assertJsonStructure(['auth']);
    }

    public function test_login_attempts_are_limited(): void
    {
        for ($index = 0; $index < 5; $index++) {
            $this->post('/login', ['email' => 'blocked@example.test', 'password' => 'invalid']);
        }
        $this->post('/login', ['email' => 'blocked@example.test', 'password' => 'invalid'])->assertTooManyRequests();
    }
}
