<?php
// app/Services/OutlookMailService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OutlookMailService
{
    private $authService;

    public function __construct(OutlookAuthService $authService)
    {
        $this->authService = $authService;
    }

    public function getFolderEmails($folder, $top = 5, $skip = 0, $account = null)
    {
        if (!$account) {
            $account = $this->authService->getCurrentAccount();
        }
        if (!$account || !$this->authService->ensureValidToken($account)) {
            return [
                'success' => false,
                'status' => 401,
                'data' => [],
                'error' => 'Authentication failed'
            ];
        }
        $response = Http::withToken($account->access_token)
            ->get("https://graph.microsoft.com/v1.0/me/mailfolders/{$folder}/messages?\$top={$top}&\$skip={$skip}&\$orderby=receivedDateTime desc");

        return [
            'success' => $response->successful(),
            'status' => $response->status(),
            'data' => $response->json()['value'] ?? [],
            'error' => $response->failed() ? $response->json() : null
        ];
    }

    public function getEmail($id, $folder = null, $account = null)
    {
        if (!$account) {
            $account = $this->authService->getCurrentAccount();
        }

        if (!$account || !$this->authService->ensureValidToken($account)) {
            return false;
        }

        $endpoint = $folder === 'sentitems'
            ? "https://graph.microsoft.com/v1.0/me/mailfolders/sentitems/messages/{$id}"
            : "https://graph.microsoft.com/v1.0/me/messages/{$id}";

        $response = Http::withToken($account->access_token)
            ->get($endpoint . '?$expand=attachments');

        if (!$response->successful()) {
            if ($this->authService->refreshToken($account)) {
                $account->refresh();
                $response = Http::withToken($account->access_token)
                    ->get($endpoint . '?$expand=attachments');
            }

            if (!$response->successful()) {
                return false;
            }
        }

        return $response->json();
    }

    public function downloadAttachment($emailId, $attachmentId, $account = null)
    {
        if (!$account) {
            $account = $this->authService->getCurrentAccount();
        }

        if (!$account || !$this->authService->ensureValidToken($account)) {
            return false;
        }
        $response = Http::withToken($account->access_token)
            ->get("https://graph.microsoft.com/v1.0/me/messages/{$emailId}/attachments/{$attachmentId}");

        if (!$response->successful()) {
            if ($this->authService->refreshToken($account)) {
                $account->refresh();
                $response = Http::withToken($account->access_token)
                    ->get("https://graph.microsoft.com/v1.0/me/messages/{$emailId}/attachments/{$attachmentId}");
            }

            if (!$response->successful()) {
                return false;
            }
        }

        return $response->json();
    }
}
