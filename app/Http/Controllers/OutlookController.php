<?php
// app/Http/Controllers/OutlookController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class OutlookController extends Controller
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

    public function connect()
    {
        $authUrl = "https://login.microsoftonline.com/common/oauth2/v2.0/authorize?" . http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'response_mode' => 'query',
            'scope' => $this->scopes,
            'prompt' => 'select_account'
        ]);

        return redirect()->away($authUrl);
    }

    public function callback(Request $request)
    {
        $code = $request->query('code');

        $response = Http::asForm()->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
            'scope' => $this->scopes
        ]);

        $tokens = $response->json();

        if (isset($tokens['access_token'])) {
            Session::put('outlook_token', $tokens['access_token']);
            Session::put('outlook_refresh_token', $tokens['refresh_token']);
            // Store token expiry time (typically 1 hour)
            Session::put('outlook_token_expires_at', now()->addSeconds($tokens['expires_in'] ?? 3600));
            return redirect()->route('outlook.emails');
        }

        return redirect('/')->with('error', 'Failed to connect to Outlook');
    }

    public function emails()
    {
        if (!$this->ensureValidToken()) {
            return redirect()->route('outlook.connect');
        }

        try {
            // Get inbox emails
            $inbox = Http::withToken(Session::get('outlook_token'))
                ->get('https://graph.microsoft.com/v1.0/me/mailfolders/inbox/messages?$top=50');

            // Get sent items
            $sent = Http::withToken(Session::get('outlook_token'))
                ->get('https://graph.microsoft.com/v1.0/me/mailfolders/sentitems/messages?$top=50');

            // Check if requests were successful
            if (!$inbox->successful() || !$sent->successful()) {
                Log::error('Failed to fetch emails', [
                    'inbox_status' => $inbox->status(),
                    'sent_status' => $sent->status(),
                    'inbox_error' => $inbox->json(),
                    'sent_error' => $sent->json()
                ]);

                // Try to refresh token and retry once
                if ($this->refreshToken()) {
                    return $this->emails(); // Retry after refresh
                }

                return redirect()->route('outlook.connect')
                    ->with('error', 'Session expired. Please reconnect to Outlook.');
            }

            Log::info('Successfully fetched emails');

            return view('outlook.emails', [
                'inbox' => $inbox->json()['value'] ?? [],
                'sent' => $sent->json()['value'] ?? []
            ]);
        } catch (\Exception $e) {
            Log::error('Exception in emails method', ['error' => $e->getMessage()]);
            return redirect()->route('outlook.connect')
                ->with('error', 'Failed to load emails. Please reconnect to Outlook.');
        }
    }

    public function showEmail($id, $folder = null)
    {
        if (!$this->ensureValidToken()) {
            return redirect()->route('outlook.connect');
        }

        // Determine the API endpoint based on folder
        $endpoint = $folder === 'sentitems'
            ? "https://graph.microsoft.com/v1.0/me/mailfolders/sentitems/messages/{$id}"
            : "https://graph.microsoft.com/v1.0/me/messages/{$id}";

        $response = Http::withToken(Session::get('outlook_token'))
            ->get($endpoint . '?$expand=attachments');

        if (!$response->successful()) {
            // Try to refresh token and retry once
            if ($this->refreshToken()) {
                $response = Http::withToken(Session::get('outlook_token'))
                    ->get($endpoint . '?$expand=attachments');
            }

            if (!$response->successful()) {
                return redirect()->route('outlook.emails')->with('error', 'Failed to load email');
            }
        }
        Log::info($response);

        $email = $response->json();
        return view('outlook.email-view', compact('email', 'folder'));
    }

    public function showInboxEmail($id)
    {
        return $this->showEmail($id);
    }

    public function showSentEmail($id)
    {
        return $this->showEmail($id, 'sentitems');
    }

    public function downloadAttachment($emailId, $attachmentId)
    {
        if (!$this->ensureValidToken()) {
            return redirect()->route('outlook.connect');
        }

        $response = Http::withToken(Session::get('outlook_token'))
            ->get("https://graph.microsoft.com/v1.0/me/messages/{$emailId}/attachments/{$attachmentId}");

        if (!$response->successful()) {
            // Try to refresh token and retry once
            if ($this->refreshToken()) {
                $response = Http::withToken(Session::get('outlook_token'))
                    ->get("https://graph.microsoft.com/v1.0/me/messages/{$emailId}/attachments/{$attachmentId}");
            }

            if (!$response->successful()) {
                return back()->with('error', 'Failed to download attachment');
            }
        }

        $attachment = $response->json();

        // For base64 encoded content
        if (isset($attachment['contentBytes'])) {
            $content = base64_decode($attachment['contentBytes']);
            $headers = [
                'Content-Type' => $attachment['contentType'],
                'Content-Disposition' => 'attachment; filename="' . $attachment['name'] . '"'
            ];

            return response()->make($content, 200, $headers);
        }

        return back()->with('error', 'Unsupported attachment type');
    }

    public function disconnect()
    {
        Session::forget(['outlook_token', 'outlook_refresh_token', 'outlook_token_expires_at']);
        return redirect('/')->with('success', 'Disconnected from Outlook');
    }

    /**
     * Ensure we have a valid token, refresh if necessary
     */
    protected function ensureValidToken()
    {
        // Check if we have any token at all
        if (!Session::has('outlook_token') || !Session::has('outlook_refresh_token')) {
            Log::info('No token or refresh token found');
            return false;
        }

        // Check if token is expired or about to expire (refresh 5 minutes early)
        $expiresAt = Session::get('outlook_token_expires_at');
        if ($expiresAt && now()->addMinutes(5)->greaterThan($expiresAt)) {
            Log::info('Token expired or about to expire, refreshing', [
                'expires_at' => $expiresAt,
                'current_time' => now()
            ]);
            return $this->refreshToken();
        }

        // If no expiry time is set, don't validate with API call every time
        // This prevents excessive refreshes
        if (!$expiresAt) {
            Log::debug('No expiry time set, assuming token is valid');
            return true;
        }

        // Only log token validity occasionally to reduce log noise
        if (now()->second % 30 == 0) { // Log every 30 seconds at most
            Log::debug('Token is still valid', [
                'expires_at' => $expiresAt,
                'current_time' => now(),
                'minutes_until_expiry' => now()->diffInMinutes($expiresAt, false)
            ]);
        }
        return true;
    }

    /**
     * Refresh the access token using refresh token
     */
    protected function refreshToken()
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

                    // Update refresh token if provided (some implementations provide a new one)
                    if (isset($tokens['refresh_token'])) {
                        Session::put('outlook_refresh_token', $tokens['refresh_token']);
                    }

                    // Update expiry time
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

        // Clear invalid tokens
        Session::forget(['outlook_token', 'outlook_refresh_token', 'outlook_token_expires_at']);
        return false;
    }
}
