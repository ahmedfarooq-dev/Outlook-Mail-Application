<?php
// app/Http/Controllers/OutlookController.php
namespace App\Http\Controllers;

use App\Services\OutlookAuthService;
use App\Services\OutlookMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OutlookController extends Controller
{
    private $authService;
    private $mailService;

    public function __construct(OutlookAuthService $authService, OutlookMailService $mailService)
    {
        $this->authService = $authService;
        $this->mailService = $mailService;
    }

    public function connect()
    {
        return redirect()->away($this->authService->getAuthUrl());
    }

    public function callback(Request $request)
    {
        $code = $request->query('code');
        $tokens = $this->authService->getTokensFromAuthCode($code);

        if ($this->authService->storeTokens($tokens)) {
            return redirect()->route('outlook.emails');
        }

        return redirect('/')->with('error', 'Failed to connect to Outlook');
    }

    public function emails()
    {
        // Only check if user is authenticated, don't load any emails yet
        if (!$this->authService->ensureValidToken()) {
            return redirect()->route('outlook.connect')
                ->with('error', 'Session expired. Please reconnect to Outlook.');
        }

        return view('outlook.emails');
    }

    // NEW METHOD: Get inbox emails via AJAX
    public function getInboxEmails()
    {
        // Check if user is authenticated
        if (!$this->authService->ensureValidToken()) {
            return response()->json(['error' => 'Session expired. Please reconnect to Outlook.'], 401);
        }
        $cacheKey = 'outlook_inbox_' . session()->getId();
        $forceRefresh = request()->input('forceRefresh');

        // Check cache first (5 minutes)
        if (!$forceRefresh && cache()->has($cacheKey)) {
            return response()->json(cache($cacheKey));
        }
        Log::info('Fetching emails from folder inbox');

        $inbox = $this->mailService->getFolderEmails('inbox');

        if (!$inbox['success']) {
            return response()->json(['error' => 'Failed to load inbox'], 500);
        }

        $data = ['emails' => $inbox['data']];

        // Cache for 5 minutes
        cache()->put($cacheKey, $data, now()->addMinutes(5));

        return response()->json($data);
    }

    // NEW METHOD: Get sent emails via AJAX
    public function getSentEmails()
    {
        // Check if user is authenticated
        if (!$this->authService->ensureValidToken()) {
            return response()->json(['error' => 'Session expired. Please reconnect to Outlook.'], 401);
        }
        $cacheKey = 'outlook_sent_' . session()->getId();
        $forceRefresh = request()->input('forceRefresh');

        // Check cache first (5 minutes)
        if (!$forceRefresh && cache()->has($cacheKey)) {
            return response()->json(cache($cacheKey));
        }
        Log::info('Fetching emails from folder sent');

        $sent = $this->mailService->getFolderEmails('sentitems');

        if (!$sent['success']) {
            return response()->json(['error' => 'Failed to load sent items'], 500);
        }

        $data = ['emails' => $sent['data']];

        // Cache for 5 minutes
        cache()->put($cacheKey, $data, now()->addMinutes(5));

        return response()->json($data);
    }

    public function showEmail($id, $folder = null)
    {
        $email = $this->mailService->getEmail($id, $folder);

        if (!$email) {
            return redirect()->route('outlook.emails')->with('error', 'Failed to load email');
        }

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
        $attachment = $this->mailService->downloadAttachment($emailId, $attachmentId);

        if (!$attachment) {
            return back()->with('error', 'Failed to download attachment');
        }

        $content = base64_decode($attachment['contentBytes']);
        $headers = [
            'Content-Type' => $attachment['contentType'],
            'Content-Disposition' => 'attachment; filename="' . $attachment['name'] . '"'
        ];

        return response()->make($content, 200, $headers);
    }

    public function disconnect()
    {
        $this->authService->disconnect();
        return redirect('/')->with('success', 'Disconnected from Outlook');
    }
}
