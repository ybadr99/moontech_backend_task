<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResendOtpRequest;
use App\Http\Requests\VerifyPhoneRequest;
use App\Models\PhoneVerification;
use App\Models\User;
use App\Notifications\PhoneVerificationOtp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PhoneVerificationController extends Controller
{
    public function verify(VerifyPhoneRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->phone_verified_at !== null) {
            return response()->json([
                'message' => 'Phone number is already verified.',
            ], 422);
        }

        $verification = PhoneVerification::where('user_id', $user->id)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (! $verification) {
            return response()->json([
                'message' => 'No OTP found. Request a new one.',
            ], 404);
        }

        if ($verification->isExpired()) {
            return response()->json([
                'message' => 'OTP has expired. Request a new one.',
            ], 410);
        }

        if (! Hash::check($request->otp, $verification->otp)) {
            return response()->json([
                'message' => 'Invalid OTP.',
            ], 422);
        }

        $verification->used_at = now();
        $verification->save();

        $user->phone_verified_at = now();
        $user->save();

        return response()->json([
            'message' => 'Phone number verified successfully.',
        ]);
    }

    public function resend(ResendOtpRequest $request): JsonResponse
    {
        $user = User::where('phone', $request->phone)->first();

        if (! $user) {
            return response()->json([
                'message' => 'No account found with this phone number.',
            ], 404);
        }

        if ($user->phone_verified_at !== null) {
            return response()->json([
                'message' => 'Phone number is already verified.',
            ], 422);
        }

        PhoneVerification::where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        PhoneVerification::create([
            'user_id' => $user->id,
            'otp' => Hash::make($otp),
            'expires_at' => now()->addMinutes(5),
        ]);

        $user->notify(new PhoneVerificationOtp($otp));

        return response()->json([
            'message' => 'A new OTP has been sent to your phone.',
        ]);
    }
}
