<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class OTPController extends Controller
{
    private const OTP_TTL_MINUTES = 10;

    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if ($user->otp_verified_at !== null) {
            return response()->json(['message' => 'Email already verified.'], 422);
        }

        if ($user->otp !== $request->otp) {
            return response()->json(['message' => 'Invalid OTP.'], 422);
        }

        if ($user->otp_expires_at === null || $user->otp_expires_at->isPast()) {
            return response()->json(['message' => 'OTP has expired.'], 422);
        }

        $user->update([
            'otp' => null,
            'otp_expires_at' => null,
            'otp_verified_at' => now(),
            'email_verified_at' => now(),
        ]);

        return response()->json(['message' => 'Email verified successfully.', 'user' => new UserResource($user->fresh())]);
    }

    public function resend(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->otp_verified_at !== null) {
            return response()->json(['message' => 'Email already verified.'], 422);
        }

        $key = 'otp-resend:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 3)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => "Too many requests. Try again in {$seconds} seconds.",
            ], 429);
        }

        RateLimiter::hit($key, decaySeconds: 60);

        $this->sendOtp($user);

        return response()->json(['message' => 'OTP resent successfully.']);
    }

    public static function sendOtp(User $user): void
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->update([
            'otp' => $otp,
            'otp_expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
        ]);

        Mail::raw(
            "Your verification code is: {$otp}\n\nThis code expires in ".self::OTP_TTL_MINUTES.' minutes.',
            function ($message) use ($user) {
                $message->to($user->email)->subject('Verify your email address');
            }
        );
    }
}
