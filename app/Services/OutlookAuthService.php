<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class OutlookAuthService
{
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $scopes;

    public function __construct()
    {
        $this->clientId = config('services.outlook.client_id');
        $this->clientSecret = config('services.outlook.client_secret');
        $this->redirectUri = config('services.outlook.redirect_uri');
        $this->scopes = 'openid offline_access https://graph.microsoft.com/Mail.Read';
    }

    public function getAuthUrl()
    {
        return "https://login.microsoftonline.com/common/oauth2/v2.0/authorize?" . http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'response_mode' => 'query',
            'scope' => $this->scopes,
            'prompt' => 'select_account'
        ]);
    }

    public function getTokensFromAuthCode($code)
    {
        $response = Http::asForm()->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
            'scope' => $this->scopes
        ]);

        return $response->json();
    }

    public function refreshToken()
    {
        if (!Session::has('outlook_refresh_token')) {
            Log::error('No refresh token available');
            return false;
        }

        try {
            $response = Http::asForm()->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => Session::get('outlook_refresh_token'),
                'grant_type' => 'refresh_token',
                'redirect_uri' => $this->redirectUri,
                'scope' => $this->scopes
            ]);

            if ($response->successful()) {
                $tokens = $response->json();

                if (isset($tokens['access_token'])) {
                    Session::put('outlook_token', $tokens['access_token']);

                    if (isset($tokens['refresh_token'])) {
                        Session::put('outlook_refresh_token', $tokens['refresh_token']);
                    }

                    Session::put('outlook_token_expires_at', now()->addSeconds($tokens['expires_in'] ?? 3600));

                    Log::info('Token refreshed successfully');
                    return true;
                }
            }

            Log::error('Token refresh failed', [
                'status' => $response->status(),
                'response' => $response->json()
            ]);
        } catch (\Exception $e) {
            Log::error('Exception during token refresh', ['error' => $e->getMessage()]);
        }

        Session::forget(['outlook_token', 'outlook_refresh_token', 'outlook_token_expires_at']);
        return false;
    }

    public function ensureValidToken()
    {
        if (!Session::has('outlook_token') || !Session::has('outlook_refresh_token')) {
            Log::info('No token or refresh token found');
            return false;
        }

        $expiresAt = Session::get('outlook_token_expires_at');
        if ($expiresAt && now()->addMinutes(5)->greaterThan($expiresAt)) {
            Log::info('Token expired or about to expire, refreshing', [
                'expires_at' => $expiresAt,
                'current_time' => now()
            ]);
            return $this->refreshToken();
        }

        if (!$expiresAt) {
            Log::debug('No expiry time set, assuming token is valid');
            return true;
        }

        if (now()->second % 30 == 0) {
            Log::debug('Token is still valid', [
                'expires_at' => $expiresAt,
                'current_time' => now(),
                'minutes_until_expiry' => now()->diffInMinutes($expiresAt, false)
            ]);
        }
        return true;
    }
    public function storeTokens($tokens)
    {
        if (isset($tokens['access_token'])) {
            Session::put('outlook_token', $tokens['access_token']);
            Session::put('outlook_refresh_token', $tokens['refresh_token']);
            Session::put('outlook_token_expires_at', now()->addSeconds($tokens['expires_in'] ?? 3600));
            return true;
        }
        return false;
    }

    public function disconnect()
    {
        Session::forget(['outlook_token', 'outlook_refresh_token', 'outlook_token_expires_at']);
        return true;
    }
}
