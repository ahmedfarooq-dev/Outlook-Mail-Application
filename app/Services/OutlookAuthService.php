<?php

namespace App\Services;

use App\Models\OutlookAccount;
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
        $this->scopes = 'openid offline_access User.Read Mail.Read Mail.ReadWrite';
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
    public function storeTokens($tokens)
    {
        if (!isset($tokens['access_token'])) {
            return false;
        }

        // Get user info to identify the account
        $userInfo = $this->getUserInfo($tokens['access_token']);
        if (!$userInfo) {
            return false;
        }
        // Store or update the account
        $account = OutlookAccount::updateOrCreate(
            ['email' => $userInfo['mail'] ?? $userInfo['userPrincipalName']],
            [
                'name' => $userInfo['displayName'] ?? 'Unknown',
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'token_expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
                'is_active' => true
            ]
        );

        // Store current account ID in session
        Session::put('current_outlook_account_id', $account->id);
        return $account;
    }
    private function getUserInfo($accessToken)
    {
        $response = Http::withToken($accessToken)
            ->get('https://graph.microsoft.com/v1.0/me');
        Log::info($response);
        return $response->successful() ? $response->json() : null;
    }


    public function getCurrentAccount()
    {
        $accountId = Session::get('current_outlook_account_id');
        if (!$accountId) {
            return null;
        }

        return OutlookAccount::active()->find($accountId);
    }

    public function switchAccount($accountId)
    {
        $account = OutlookAccount::active()->find($accountId);
        if (!$account) {
            return false;
        }

        Session::put('current_outlook_account_id', $accountId);
        return $account;
    }

    public function refreshToken($account = null)
    {
        if (!$account) {
            $account = $this->getCurrentAccount();
        }
        if (!$account || !$account->refresh_token) {
            Log::error('No account or refresh token available');
            return false;
        }
        try {
            $response = Http::asForm()->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $account->refresh_token,
                'grant_type' => 'refresh_token',
                'redirect_uri' => $this->redirectUri,
                'scope' => $this->scopes
            ]);

            if ($response->successful()) {
                $tokens = $response->json();

                if (isset($tokens['access_token'])) {
                    $account->update([
                        'access_token' => $tokens['access_token'],
                        'refresh_token' => $tokens['refresh_token'] ?? $account->refresh_token,
                        'token_expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600)
                    ]);

                    Log::info('Token refreshed successfully for account: ' . $account->email);
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

        // Mark account as inactive if refresh fails
        $account->update(['is_active' => false]);
        return false;
    }
    public function ensureValidToken($account = null)
    {
        if (!$account) {
            $account = $this->getCurrentAccount();
        }

        if (!$account) {
            Log::info('No current account found');
            return false;
        }

        if ($account->isTokenExpired()) {
            Log::info('Token expired for account: ' . $account->email . ', refreshing');
            return $this->refreshToken($account);
        }

        return true;
    }

    public function getAllAccounts()
    {
        return OutlookAccount::active()->orderBy('created_at', 'desc')->get();
    }
    public function disconnectAccount($accountId = null)
    {
        if ($accountId) {
            $account = OutlookAccount::find($accountId);
            if ($account) {
                $account->update(['is_active' => false]);
            }
        } else {
            // Disconnect current account
            $account = $this->getCurrentAccount();
            if ($account) {
                $account->update(['is_active' => false]);
            }
            Session::forget('current_outlook_account_id');
        }

        return true;
    }
    public function disconnect()
    {
        return $this->disconnectAccount();
    }
}
