<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OutlookController;

Route::get('/', function () {
    return view('outlook.connect-to-outlook');
});

Route::get('/outlook/connect', [OutlookController::class, 'connect'])->name('outlook.connect');
Route::get('/outlook/callback', [OutlookController::class, 'callback']);
Route::get('/outlook/emails', [OutlookController::class, 'emails'])->name('outlook.emails');
Route::get('/outlook/disconnect', [OutlookController::class, 'disconnect'])->name('outlook.disconnect');
Route::get('/outlook/sent/{id}', [OutlookController::class, 'showSentEmail'])->name('outlook.sent.show');
Route::get('/outlook/inbox/{id}', [OutlookController::class, 'showInboxEmail'])->name('outlook.inbox.show');
Route::get('/outlook/email/{emailId}/attachment/{attachmentId}', [OutlookController::class, 'downloadAttachment'])
    ->name('outlook.attachment.download');
