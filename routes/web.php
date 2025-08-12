<?php
// routes/web.php

use App\Http\Controllers\OutlookController;
use Illuminate\Support\Facades\Route;

// Main landing page - shows all connected accounts
Route::get('/', [OutlookController::class, 'index'])->name('outlook.index');

// Original connect route (redirects to auth)
Route::get('/outlook/connect', [OutlookController::class, 'connect'])->name('outlook.connect');

// OAuth callback
Route::get('/outlook/callback', [OutlookController::class, 'callback']);

// Switch between accounts
Route::get('/outlook/switch/{accountId}', [OutlookController::class, 'switchAccount'])->name('outlook.switch');

// View emails for current account
Route::get('/outlook/emails', [OutlookController::class, 'emails'])->name('outlook.emails');

// AJAX endpoints
Route::get('/outlook/api/inbox', [OutlookController::class, 'getInboxEmails'])->name('outlook.api.inbox');
Route::get('/outlook/api/sent', [OutlookController::class, 'getSentEmails'])->name('outlook.api.sent');

// View individual emails
Route::get('/outlook/inbox/{id}', [OutlookController::class, 'showInboxEmail'])->name('outlook.inbox.show');
Route::get('/outlook/sent/{id}', [OutlookController::class, 'showSentEmail'])->name('outlook.sent.show');

// Download attachments
Route::get('/outlook/attachment/{emailId}/{attachmentId}', [OutlookController::class, 'downloadAttachment'])
    ->name('outlook.attachment.download');

// Disconnect accounts
Route::get('/outlook/disconnect', [OutlookController::class, 'disconnect'])->name('outlook.disconnect');
Route::get('/outlook/disconnect/{accountId}', [OutlookController::class, 'disconnect'])->name('outlook.disconnect.specific');
