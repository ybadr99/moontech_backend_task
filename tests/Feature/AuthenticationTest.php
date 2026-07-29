<?php

namespace Tests\Feature;

use App\Models\PhoneVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_phone(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'phone' => '1234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => ['user' => ['id', 'name', 'phone', 'role', 'created_at'], 'token'],
            ]);

        $this->assertDatabaseHas('users', [
            'phone' => '1234567890',
            'role' => 'user',
        ]);
    }

    public function test_duplicate_phone_is_rejected(): void
    {
        Notification::fake();

        User::factory()->create(['phone' => '1234567890']);

        $response = $this->postJson('/api/register', [
            'name' => 'Jane Doe',
            'phone' => '1234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    public function test_validation_errors_for_invalid_register_input(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => '',
            'phone' => '',
            'password' => 'short',
            'password_confirmation' => 'not-matching',
        ]);

        $response->assertStatus(422);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'phone' => '1234567890',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'phone' => '1234567890',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => ['user', 'token'],
            ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'phone' => '1234567890',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'phone' => '1234567890',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_registered_user_receives_token(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/register', [
            'name' => 'Token User',
            'phone' => '9999999999',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        $token = $response->json('data.token');
        $this->assertNotNull($token);
    }

    public function test_authenticated_user_can_access_protected_route(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/user');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_receives_401(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }

    public function test_phone_verification_succeeds_with_valid_code(): void
    {
        Notification::fake();

        $user = User::factory()->create(['phone_verified_at' => null]);

        $otp = '123456';
        PhoneVerification::create([
            'user_id' => $user->id,
            'otp' => Hash::make($otp),
            'expires_at' => now()->addMinutes(5),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/phone/verify', ['otp' => $otp]);

        $response->assertStatus(200);

        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_invalid_verification_code_is_rejected(): void
    {
        $user = User::factory()->create(['phone_verified_at' => null]);

        PhoneVerification::create([
            'user_id' => $user->id,
            'otp' => Hash::make('000000'),
            'expires_at' => now()->addMinutes(5),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/phone/verify', ['otp' => '999999']);

        $response->assertStatus(422);
    }

    public function test_expired_verification_code_is_rejected(): void
    {
        $user = User::factory()->create(['phone_verified_at' => null]);

        PhoneVerification::create([
            'user_id' => $user->id,
            'otp' => Hash::make('123456'),
            'expires_at' => now()->subMinutes(5),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/phone/verify', ['otp' => '123456']);

        $response->assertStatus(410);
    }

    public function test_verification_code_can_be_resent(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'phone' => '1234567890',
            'phone_verified_at' => null,
        ]);

        $response = $this->postJson('/api/phone/resend', [
            'phone' => '1234567890',
        ]);

        $response->assertStatus(200);
    }

    public function test_already_verified_user_cannot_verify_again(): void
    {
        $user = User::factory()->create(['phone_verified_at' => now()]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/phone/verify', ['otp' => '123456']);

        $response->assertStatus(422);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/logout');

        $response->assertStatus(200);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
