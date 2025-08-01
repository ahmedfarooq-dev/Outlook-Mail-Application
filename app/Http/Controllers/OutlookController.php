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
            return redirect()->route('outlook.emails');
        }

        return redirect('/')->with('error', 'Failed to connect to Outlook');
    }

    public function emails()
    {
        if (!Session::has('outlook_token')) {
            return redirect()->route('outlook.connect');
        }

        // Get inbox emails
        $inbox = Http::withToken(Session::get('outlook_token'))
            ->get('https://graph.microsoft.com/v1.0/me/mailfolders/inbox/messages?$top=50');

        // Get sent items
        $sent = Http::withToken(Session::get('outlook_token'))
            ->get('https://graph.microsoft.com/v1.0/me/mailfolders/sentitems/messages?$top=50');
        Log::info($inbox);
        Log::info($sent);
        return view('outlook.emails', [
            'inbox' => $inbox->json()['value'] ?? [],
            'sent' => $sent->json()['value'] ?? []
        ]);
    }
    // app/Http/Controllers/OutlookController.php
    public function showEmail($id)
    {
        if (!Session::has('outlook_token')) {
            return redirect()->route('outlook.connect');
        }

        // Get the specific email
        $response = Http::withToken(Session::get('outlook_token'))
            ->get("https://graph.microsoft.com/v1.0/me/messages/{$id}");

        if (!$response->successful()) {
            return redirect()->route('outlook.emails')->with('error', 'Failed to load email');
        }

        $email = $response->json();

        return view('outlook.email-view', compact('email'));
    }
    public function downloadAttachment($emailId, $attachmentId)
    {
        if (!Session::has('outlook_token')) {
            return redirect()->route('outlook.connect');
        }

        $response = Http::withToken(Session::get('outlook_token'))
            ->get("https://graph.microsoft.com/v1.0/me/messages/{$emailId}/attachments/{$attachmentId}");

        if (!$response->successful()) {
            return back()->with('error', 'Failed to download attachment');
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
        Session::forget(['outlook_token', 'outlook_refresh_token']);
        return redirect('/')->with('success', 'Disconnected from Outlook');
    }

    // Optional: Refresh token if expired
    protected function refreshToken()
    {
        $response = Http::asForm()->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => Session::get('outlook_refresh_token'),
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'refresh_token',
            'scope' => $this->scopes
        ]);

        $tokens = $response->json();

        if (isset($tokens['access_token'])) {
            Session::put('outlook_token', $tokens['access_token']);
            Session::put('outlook_refresh_token', $tokens['refresh_token']);
            return true;
        }

        return false;
    }
}
