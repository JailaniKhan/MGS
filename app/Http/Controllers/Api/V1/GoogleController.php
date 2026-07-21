<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    /**
     * Web: redirect the browser to Google's OAuth consent screen.
     */
    public function redirect()
    {
        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    /**
     * Web: handle Google's callback, log the user in via session, return a token.
     */
    public function callback()
    {
        $googleUser = Socialite::driver('google')->user();

        $user = User::findOrCreateFromGoogle([
            'id' => $googleUser->getId(),
            'name' => $googleUser->getName(),
            'email' => $googleUser->getEmail(),
            'avatar' => $googleUser->getAvatar(),
        ]);

        $token = $user->createToken('google-web')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Mobile: the app performs Google login in a browser/webview (PKCE) and
     * sends the resulting authorization code here. We exchange it server-side
     * and return a Sanctum token, matching the existing login response shape.
     */
    public function exchange(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'redirect_uri' => 'required|string',
            'device_name' => 'required|string|max:255',
        ]);

        $googleUser = Socialite::driver('google')
            ->redirectUri($request->redirect_uri)
            ->stateless()
            ->userFromCode($request->code);

        $user = User::findOrCreateFromGoogle([
            'id' => $googleUser->getId(),
            'name' => $googleUser->getName(),
            'email' => $googleUser->getEmail(),
            'avatar' => $googleUser->getAvatar(),
        ]);

        $token = $user->createToken($request->device_name)->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }
}
