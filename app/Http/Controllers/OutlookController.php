<?php
// app/Http/Controllers/OutlookController.php
namespace App\Http\Controllers;

use App\Models\OutlookAccount;
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
    public function index()
    {
        $accounts = $this->authService->getAllAccounts();
        $currentAccount = $this->authService->getCurrentAccount();

        return view('outlook.connect-to-outlook', compact('accounts', 'currentAccount'));
    }
    public function connect()
    {
        return redirect()->away($this->authService->getAuthUrl());
    }

    public function callback(Request $request)
    {
        $code = $request->query('code');
        $tokens = $this->authService->getTokensFromAuthCode($code);
        $account = $this->authService->storeTokens($tokens);
        if ($account) {
            return redirect()->route('outlook.emails')
                ->with('success', 'Successfully connected ' . $account->email);
        }

        return redirect()->route('outlook.index')
            ->with('error', 'Failed to connect to Outlook');
    }
    public function switchAccount($accountId)
    {
        $account = $this->authService->switchAccount($accountId);

        if ($account) {
            return redirect()->route('outlook.emails')
                ->with('success', 'Switched to ' . $account->email);
        }

        return redirect()->route('outlook.index')
            ->with('error', 'Failed to switch account');
    }
    public function emails()
    {
        $currentAccount = $this->authService->getCurrentAccount();

        if (!$currentAccount) {
            return redirect()->route('outlook.index')
                ->with('error', 'No active Outlook account. Please connect an account.');
        }

        if (!$this->authService->ensureValidToken($currentAccount)) {
            return redirect()->route('outlook.index')
                ->with('error', 'Session expired for ' . $currentAccount->email . '. Please reconnect.');
        }

        $accounts = $this->authService->getAllAccounts();

        return view('outlook.emails', compact('currentAccount', 'accounts'));
    }

    // NEW METHOD: Get inbox emails via AJAX
    public function getInboxEmails(Request $request)
    {
        $currentAccount = $this->authService->getCurrentAccount();
        // Check if user is authenticated
        if (!$currentAccount || !$this->authService->ensureValidToken($currentAccount)) {
            return response()->json(['error' => 'Session expired. Please reconnect to Outlook.'], 401);
        }

        $page = $request->query('page', 1);
        $perPage = 5;
        $forceRefresh = $request->query('refresh', false);
        $cacheKey = 'outlook_inbox_page_' . $page . '_' . $currentAccount->id;

        // Check cache first unless force refresh
        if (!$forceRefresh && cache()->has($cacheKey)) {
            return response()->json(cache($cacheKey));
        }
        Log::info('Getting inbox emails for page ' . $page);
        $skip = ($page - 1) * $perPage;
        $inbox = $this->mailService->getFolderEmails('inbox', $perPage, $skip, $currentAccount);

        if (!$inbox['success']) {
            return response()->json(['error' => 'Failed to load inbox'], 500);
        }

        $data = [
            'emails' => $inbox['data'],
            'currentPage' => $page,
            'perPage' => $perPage,
            'hasMore' => count($inbox['data']) >= $perPage,
            'account' => $currentAccount->email
        ];

        // Cache
        cache()->put($cacheKey, $data, now()->addMinutes(10));

        return response()->json($data);
    }

    // NEW METHOD: Get sent emails via AJAX
    public function getSentEmails(Request $request)
    {
        $currentAccount = $this->authService->getCurrentAccount();

        if (!$currentAccount || !$this->authService->ensureValidToken($currentAccount)) {
            return response()->json(['error' => 'Session expired. Please reconnect to Outlook.'], 401);
        }

        $page = $request->query('page', 1);
        $perPage = 5;
        $forceRefresh = $request->query('refresh', false);
        $cacheKey = 'outlook_sent_page_' . $page . '_' . $currentAccount->id;

        // Check cache first unless force refresh
        if (!$forceRefresh && cache()->has($cacheKey)) {
            return response()->json(cache($cacheKey));
        }
        Log::info('Getting sent emails for page ' . $page);
        $skip = ($page - 1) * $perPage;
        $sent = $this->mailService->getFolderEmails('sentitems', $perPage, $skip, $currentAccount);

        if (!$sent['success']) {
            return response()->json(['error' => 'Failed to load sent items'], 500);
        }

        $data = [
            'emails' => $sent['data'],
            'currentPage' => $page,
            'perPage' => $perPage,
            'hasMore' => count($sent['data']) >= $perPage,
            'account' => $currentAccount->email
        ];

        // Cache
        cache()->put($cacheKey, $data, now()->addMinutes(10));

        return response()->json($data);
    }

    public function showEmail($id, $folder = null)
    {
        $currentAccount = $this->authService->getCurrentAccount();

        if (!$currentAccount) {
            return redirect()->route('outlook.index')
                ->with('error', 'No active account');
        }
        $email = $this->mailService->getEmail($id, $folder, $currentAccount);

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
        $currentAccount = $this->authService->getCurrentAccount();

        if (!$currentAccount) {
            return back()->with('error', 'No active account');
        }
        $attachment = $this->mailService->downloadAttachment($emailId, $attachmentId, $currentAccount);

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

    public function disconnect($accountId = null)
    {
        if ($accountId) {
            $account = OutlookAccount::find($accountId);
            $this->authService->disconnectAccount($accountId);
            $message = $account ? 'Disconnected ' . $account->email : 'Account disconnected';
        } else {
            $currentAccount = $this->authService->getCurrentAccount();
            $this->authService->disconnect();
            $message = $currentAccount ? 'Disconnected ' . $currentAccount->email : 'Account disconnected';
        }

        return redirect()->route('outlook.index')->with('success', $message);
    }
}
