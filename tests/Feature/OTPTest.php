<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class OTPTest extends TestCase
{
    use RefreshDatabase;

    private function userWithOtp(array $overrides = []): array
    {
        $user = User::factory()->create(array_merge([
            'otp'            => '123456',
            'otp_expires_at' => now()->addMinutes(10),
        ], $overrides));

        $token = $user->createToken('auth-token')->plainTextToken;

        return [$user, $token];
    }

    public function test_otp_verification_succeeds(): void
    {
        [$user, $token] = $this->userWithOtp();

        $response = $this->withToken($token)->postJson('/api/email/verify', ['otp' => '123456']);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Email verified successfully.']);

        $this->assertNotNull($user->fresh()->otp_verified_at);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_expired_otp_is_rejected(): void
    {
        [$user, $token] = $this->userWithOtp(['otp_expires_at' => now()->subMinute()]);

        $response = $this->withToken($token)->postJson('/api/email/verify', ['otp' => '123456']);

        $response->assertStatus(422)
            ->assertJson(['message' => 'OTP has expired.']);
    }

    public function test_already_verified_user_cannot_verify_again(): void
    {
        $user = User::factory()->create([
            'otp'             => '123456',
            'otp_expires_at'  => now()->addMinutes(10),
            'otp_verified_at' => now(),
        ]);
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/email/verify', ['otp' => '123456']);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Email already verified.']);
    }

    public function test_otp_resend_works(): void
    {
        [$user, $token] = $this->userWithOtp();

        $response = $this->withToken($token)->postJson('/api/email/resend-otp');

        $response->assertStatus(200)
            ->assertJson(['message' => 'OTP resent successfully.']);

        $user->refresh();
        $this->assertNotNull($user->otp);
        $this->assertTrue($user->otp_expires_at->isFuture());
    }

    public function test_otp_resend_throttling_works(): void
    {
        [$user, $token] = $this->userWithOtp();

        RateLimiter::clear('otp-resend:' . $user->id);

        for ($i = 0; $i < 3; $i++) {
            $this->withToken($token)->postJson('/api/email/resend-otp')->assertStatus(200);
        }

        $response = $this->withToken($token)->postJson('/api/email/resend-otp');

        $response->assertStatus(429);
    }
}
