<?php
// app/Http/Controllers/OutlookController.php
namespace App\Http\Controllers;

use App\Services\OutlookAuthService;
use App\Services\OutlookMailService;
use Illuminate\Http\Request;

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
        $emails = $this->mailService->getEmails();

        if (!$emails) {
            return redirect()->route('outlook.connect')
                ->with('error', 'Session expired. Please reconnect to Outlook.');
        }

        return view('outlook.emails', $emails);
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
