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

    public function getEmails()
    {
        if (!$this->authService->ensureValidToken()) {
            return false;
        }

        try {
            $inbox = $this->getFolderEmails('inbox');
            $sent = $this->getFolderEmails('sentitems');

            if (!$inbox['success'] || !$sent['success']) {
                Log::error('Failed to fetch emails', [
                    'inbox_status' => $inbox['status'],
                    'sent_status' => $sent['status'],
                    'inbox_error' => $inbox['error'],
                    'sent_error' => $sent['error']
                ]);

                if ($this->authService->refreshToken()) {
                    return $this->getEmails(); // Retry after refresh
                }

                return false;
            }

            return [
                'inbox' => $inbox['data'] ?? [],
                'sent' => $sent['data'] ?? []
            ];
        } catch (\Exception $e) {
            Log::error('Exception in getEmails method', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function getFolderEmails($folder, $top = 50)
    {
        $response = Http::withToken(session('outlook_token'))
            ->get("https://graph.microsoft.com/v1.0/me/mailfolders/{$folder}/messages?$top={$top}");

        return [
            'success' => $response->successful(),
            'status' => $response->status(),
            'data' => $response->json()['value'] ?? [],
            'error' => $response->failed() ? $response->json() : null
        ];
    }

    public function getEmail($id, $folder = null)
    {
        if (!$this->authService->ensureValidToken()) {
            return false;
        }

        $endpoint = $folder === 'sentitems'
            ? "https://graph.microsoft.com/v1.0/me/mailfolders/sentitems/messages/{$id}"
            : "https://graph.microsoft.com/v1.0/me/messages/{$id}";

        $response = Http::withToken(session('outlook_token'))
            ->get($endpoint . '?$expand=attachments');

        if (!$response->successful()) {
            if ($this->authService->refreshToken()) {
                $response = Http::withToken(session('outlook_token'))
                    ->get($endpoint . '?$expand=attachments');
            }

            if (!$response->successful()) {
                return false;
            }
        }

        return $response->json();
    }

    public function downloadAttachment($emailId, $attachmentId)
    {
        if (!$this->authService->ensureValidToken()) {
            return false;
        }

        $response = Http::withToken(session('outlook_token'))
            ->get("https://graph.microsoft.com/v1.0/me/messages/{$emailId}/attachments/{$attachmentId}");

        if (!$response->successful()) {
            if ($this->authService->refreshToken()) {
                $response = Http::withToken(session('outlook_token'))
                    ->get("https://graph.microsoft.com/v1.0/me/messages/{$emailId}/attachments/{$attachmentId}");
            }

            if (!$response->successful()) {
                return false;
            }
        }

        return $response->json();
    }
}
