<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SpotifyAuthController extends Controller
{
    /**
     * Redirect pengguna ke halaman otorisasi Spotify OAuth.
     */
    public function login(Request $request): RedirectResponse
    {
        $returnUrl = $request->input('return_url') ?: url()->previous() ?: route('home');
        $state = Str::random(32);

        $request->session()->put('spotify_return_url', $returnUrl);
        $request->session()->put('spotify_auth_state', $state);

        $clientId = config('services.spotify.client_id');
        $redirectUri = config('services.spotify.redirect_uri');

        // Scopes yang dibutuhkan Spotify Web Playback SDK untuk kontrol penuh
        $scopes = [
            'streaming',
            'user-read-email',
            'user-read-private',
            'user-modify-playback-state',
            'user-read-playback-state',
        ];

        $query = http_build_query([
            'client_id' => $clientId,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => implode(' ', $scopes),
            'show_dialog' => 'true',
        ]);

        return redirect('https://accounts.spotify.com/authorize?'.$query);
    }

    /**
     * Tangani callback dari Spotify OAuth setelah persetujuan pengguna.
     */
    public function callback(Request $request): RedirectResponse
    {
        $returnUrl = $request->session()->pull('spotify_return_url', route('home'));
        $storedState = $request->session()->pull('spotify_auth_state');

        if (! $storedState || $storedState !== $request->input('state')) {
            return redirect($returnUrl)
                ->with('warning', 'Otorisasi Spotify gagal: State keamanan tidak valid.');
        }

        if ($request->has('error')) {
            return redirect($returnUrl)
                ->with('warning', 'Otorisasi Spotify dibatalkan oleh pengguna.');
        }

        $code = $request->input('code');
        $clientId = config('services.spotify.client_id');
        $clientSecret = config('services.spotify.client_secret');
        $redirectUri = config('services.spotify.redirect_uri');

        try {
            $response = Http::asForm()
                ->withOptions(['force_ip_resolve' => 'v4'])
                ->withHeaders([
                    'Authorization' => 'Basic '.base64_encode($clientId.':'.$clientSecret),
                ])
                ->timeout(10)
                ->post('https://accounts.spotify.com/api/token', [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                ]);

            if (! $response->successful()) {
                return redirect($returnUrl)
                    ->with('warning', 'Gagal menukarkan token Spotify: '.$response->body());
            }

            $tokenData = $response->json();
            $accessToken = $tokenData['access_token'] ?? null;
            $refreshToken = $tokenData['refresh_token'] ?? null;
            $expiresIn = $tokenData['expires_in'] ?? 3600;

            // Ambil data profil akun Spotify pengguna
            $userRes = Http::withToken($accessToken)
                ->withOptions(['force_ip_resolve' => 'v4'])
                ->timeout(5)
                ->get('https://api.spotify.com/v1/me');

            $user = $userRes->successful() ? $userRes->json() : [];

            $displayName = $user['display_name'] ?? 'Pengguna Spotify';
            $product = $user['product'] ?? 'unknown';
            $avatar = $user['images'][0]['url'] ?? null;
            $isPremium = $product === 'premium';

            $request->session()->put([
                'spotify_user_access_token' => $accessToken,
                'spotify_user_refresh_token' => $refreshToken,
                'spotify_user_token_expires_at' => now()->addSeconds($expiresIn - 60)->timestamp,
                'spotify_user_id' => $user['id'] ?? null,
                'spotify_user_name' => $displayName,
                'spotify_user_product' => $product,
                'spotify_user_avatar' => $avatar,
            ]);

            $msg = $isPremium
                ? "Berhasil terhubung ke akun Spotify Premium ({$displayName})! Pemutar web kini dapat memutar lagu secara penuh."
                : "Berhasil terhubung ke akun Spotify ({$displayName}). Status akun: {$product}. Catatan: Spotify membatasi fitur Web Playback SDK untuk akun Premium.";

            return redirect($returnUrl)->with('success', $msg);
        } catch (\Throwable $e) {
            return redirect($returnUrl)->with('warning', 'Terjadi kesalahan saat otorisasi Spotify: '.$e->getMessage());
        }
    }

    /**
     * Putuskan sesi akun Spotify.
     */
    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget([
            'spotify_user_access_token',
            'spotify_user_refresh_token',
            'spotify_user_token_expires_at',
            'spotify_user_id',
            'spotify_user_name',
            'spotify_user_product',
            'spotify_user_avatar',
        ]);

        return back()->with('info', 'Akun Spotify Anda telah diputuskan.');
    }

    /**
     * Endpoint API untuk menyediakan token aktif ke frontend Spotify Web Playback SDK.
     */
    public function token(Request $request): JsonResponse
    {
        $token = $request->session()->get('spotify_user_access_token');
        $expiresAt = $request->session()->get('spotify_user_token_expires_at', 0);
        $refreshToken = $request->session()->get('spotify_user_refresh_token');

        if (! $token) {
            return response()->json([
                'connected' => false,
                'error' => 'not_connected',
            ], 401);
        }

        // Segarkan token jika sudah/hampir kedaluwarsa
        if (time() >= $expiresAt && $refreshToken) {
            $clientId = config('services.spotify.client_id');
            $clientSecret = config('services.spotify.client_secret');

            try {
                $res = Http::asForm()
                    ->withOptions(['force_ip_resolve' => 'v4'])
                    ->withHeaders([
                        'Authorization' => 'Basic '.base64_encode($clientId.':'.$clientSecret),
                    ])
                    ->timeout(8)
                    ->post('https://accounts.spotify.com/api/token', [
                        'grant_type' => 'refresh_token',
                        'refresh_token' => $refreshToken,
                    ]);

                if ($res->successful()) {
                    $data = $res->json();
                    $token = $data['access_token'] ?? $token;
                    $expiresIn = $data['expires_in'] ?? 3600;

                    if (! empty($data['refresh_token'])) {
                        $request->session()->put('spotify_user_refresh_token', $data['refresh_token']);
                    }

                    $request->session()->put([
                        'spotify_user_access_token' => $token,
                        'spotify_user_token_expires_at' => now()->addSeconds($expiresIn - 60)->timestamp,
                    ]);
                }
            } catch (\Throwable $e) {
                // Biarkan token lama jika gagal refresh sesaat
            }
        }

        $product = $request->session()->get('spotify_user_product');

        return response()->json([
            'connected' => true,
            'token' => $token,
            'user' => [
                'id' => $request->session()->get('spotify_user_id'),
                'name' => $request->session()->get('spotify_user_name'),
                'product' => $product,
                'avatar' => $request->session()->get('spotify_user_avatar'),
                'is_premium' => $product === 'premium',
            ],
        ]);
    }

    /**
     * Endpoint API untuk mengontrol pemutaran via Spotify Web API.
     */
    public function play(Request $request): JsonResponse
    {
        $token = $request->session()->get('spotify_user_access_token');
        if (! $token) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $deviceId = $request->input('device_id');
        $trackUri = $request->input('track_uri');
        $positionMs = (int) $request->input('position_ms', 0);

        $url = 'https://api.spotify.com/v1/me/player/play'.($deviceId ? '?device_id='.$deviceId : '');
        $body = ['uris' => [$trackUri]];
        if ($positionMs > 0) {
            $body['position_ms'] = $positionMs;
        }

        try {
            $res = Http::withToken($token)
                ->withOptions(['force_ip_resolve' => 'v4'])
                ->timeout(8)
                ->put($url, $body);

            return response()->json([
                'success' => $res->successful() || $res->status() === 204,
                'status' => $res->status(),
                'data' => $res->json(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
