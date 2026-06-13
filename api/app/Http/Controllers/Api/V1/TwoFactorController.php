<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function enable(Request $request, Google2FA $g2fa)
    {
        $user   = $request->user();
        $secret = $g2fa->generateSecretKey();
        $user->forceFill(['two_factor_secret' => $secret])->save();

        $qrUrl = $g2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );

        return response()->json([
            'secret'  => $secret,
            'qr_url'  => $qrUrl,
        ]);
    }

    public function confirm(Request $request, Google2FA $g2fa)
    {
        $data = $request->validate(['code' => 'required|digits:6']);
        $user = $request->user();
        if (! $g2fa->verifyKey($user->two_factor_secret, $data['code'])) {
            return response()->json(['title' => 'Invalid code', 'status' => 422], 422);
        }
        $recovery = collect(range(1, 8))->map(fn () => strtoupper(bin2hex(random_bytes(5))))->all();
        $user->forceFill([
            'two_factor_confirmed_at'  => now(),
            'two_factor_recovery_codes'=> json_encode($recovery),
        ])->save();
        $request->session()->put('2fa_verified_at', now()->timestamp);
        return response()->json(['recovery_codes' => $recovery]);
    }

    public function disable(Request $request)
    {
        $user = $request->user();
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();
        return response()->noContent();
    }
}
