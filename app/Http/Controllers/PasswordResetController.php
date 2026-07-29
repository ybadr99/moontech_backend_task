<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\PasswordReset;
use App\Models\User;
use App\Notifications\PasswordResetOtp;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class PasswordResetController extends Controller
{
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('phone', $request->phone)->first();

        if (! $user) {
            return response()->json([
                'message' => 'No account found with this phone number.',
            ], 404);
        }

        PasswordReset::where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        PasswordReset::create([
            'user_id' => $user->id,
            'phone' => $user->phone,
            'otp' => Hash::make($otp),
            'expires_at' => now()->addMinutes(5),
        ]);

        $user->notify(new PasswordResetOtp($otp));

        return response()->json([
            'message' => 'OTP sent to your phone.',
        ]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $user = User::where('phone', $request->phone)->first();

        if (! $user) {
            return response()->json([
                'message' => 'No account found with this phone number.',
            ], 404);
        }

        $reset = PasswordReset::where('user_id', $user->id)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (! $reset) {
            return response()->json([
                'message' => 'No OTP found. Request a new one.',
            ], 404);
        }

        if ($reset->isExpired()) {
            return response()->json([
                'message' => 'OTP has expired. Request a new one.',
            ], 410);
        }

        if (! Hash::check($request->otp, $reset->otp)) {
            return response()->json([
                'message' => 'Invalid OTP.',
            ], 422);
        }

        $reset->used_at = now();
        $reset->save();

        $user->password = $request->password;
        $user->save();

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password reset successfully.',
        ]);
    }
}
