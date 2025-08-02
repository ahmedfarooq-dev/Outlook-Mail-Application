<!-- resources/views/outlook/emails.blade.php -->
@extends('outlook.layout.app')

@section('title', 'Outlook Emails')

@section('disconnect_button')
<a href="{{ route('outlook.disconnect') }}" class="btn btn-danger">Disconnect Outlook</a>
@endsection

@section('content')
<div class="container">
    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#inbox">Inbox</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#sent">Sent Items</a>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Inbox Tab -->
        <div class="tab-pane fade show active" id="inbox">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2>Inbox</h2>
                <span class="badge bg-primary">{{ count($inbox) }} emails</span>
            </div>

            @if(count($inbox) > 0)
            <div class="list-group">
                @foreach($inbox as $email)
                <a href="{{ route('outlook.inbox.show', $email['id']) }}"
                    class="list-group-item list-group-item-action">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">{{ $email['subject'] }}</h6>
                        <small>{{ \Carbon\Carbon::parse($email['receivedDateTime'])->diffForHumans() }}</small>
                    </div>
                    <div class="d-flex w-100 justify-content-between">
                        <p class="mb-1">
                            <strong>From:</strong>
                            {{ $email['from']['emailAddress']['name'] ?? $email['from']['emailAddress']['address'] }}
                        </p>
                        @if($email['hasAttachments'])
                        <span class="badge bg-secondary">
                            <i class="fas fa-paperclip"></i> Attachment
                        </span>
                        @endif
                    </div>
                    <small class="text-muted">{{ Str::limit($email['bodyPreview'], 100) }}</small>
                </a>
                @endforeach
            </div>
            @else
            <div class="alert alert-info">No emails in your inbox</div>
            @endif
        </div>

        <!-- Sent Items Tab -->
        <div class="tab-pane fade" id="sent">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2>Sent Items</h2>
                <span class="badge bg-primary">{{ count($sent) }} emails</span>
            </div>

            @if(count($sent) > 0)
            <div class="list-group">
                @foreach($sent as $email)
                <a href="{{ route('outlook.sent.show', $email['id']) }}" class="list-group-item list-group-item-action">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">{{ $email['subject'] }}</h6>
                        <small>{{ \Carbon\Carbon::parse($email['sentDateTime'])->diffForHumans() }}</small>
                    </div>
                    <div class="d-flex w-100 justify-content-between">
                        <p class="mb-1">
                            <strong>To:</strong>
                            {{ $email['toRecipients'][0]['emailAddress']['name'] ??
                            $email['toRecipients'][0]['emailAddress']['address'] ?? 'N/A' }}
                        </p>
                        @if($email['hasAttachments'])
                        <span class="badge bg-secondary">
                            <i class="fas fa-paperclip"></i> Attachment
                        </span>
                        @endif
                    </div>
                    <small class="text-muted">{{ Str::limit($email['bodyPreview'], 100) }}</small>
                </a>
                @endforeach
            </div>
            @else
            <div class="alert alert-info">No sent emails</div>
            @endif
        </div>
    </div>
</div>
@endsection