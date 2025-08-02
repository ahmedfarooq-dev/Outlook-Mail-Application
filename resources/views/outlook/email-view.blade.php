<!-- resources/views/outlook/email-view.blade.php -->
@extends('outlook.layout.app')

@section('title', 'Email Details')

@section('back_button')
<a href="{{ route('outlook.emails') }}" class="btn btn-secondary mb-3">← Back to Emails</a>
@endsection

@section('content')
<div class="email-header">
    <h2>{{ $email['subject'] ?? 'No Subject' }}</h2>

    <div class="row mt-3">
        <div class="col-md-6">
            <p><strong>From:</strong>
                {{ $email['from']['emailAddress']['name'] ?? 'N/A' }}
                &lt;{{ $email['from']['emailAddress']['address'] ?? 'N/A' }}&gt;
            </p>

            <p><strong>To:</strong>
                @forelse($email['toRecipients'] as $recipient)
                {{ $recipient['emailAddress']['name'] ?? $recipient['emailAddress']['address'] }};
                @empty
                No recipients
                @endforelse
            </p>

            @if(!empty($email['ccRecipients']))
            <p><strong>CC:</strong>
                @forelse($email['toRecipients'] ?? [] as $recipient)
                {{ $recipient['emailAddress']['name'] ?? $recipient['emailAddress']['address'] }};
                @empty
                No recipients
                @endforelse
            </p>
            @endif
            @if(!empty($email['bccRecipients']))
            <p><strong>BCC:</strong>
                @foreach($email['bccRecipients'] as $recipient)
                {{ $recipient['emailAddress']['name'] ?? $recipient['emailAddress']['address'] }};
                @endforeach
            </p>
            @endif
        </div>

        <div class="col-md-6">
            <p><strong>Date:</strong>
                {{ \Carbon\Carbon::parse($email['sentDateTime'])->format('M j, Y g:i A') }}
            </p>

            @if(!empty($email['hasAttachments']) && $email['hasAttachments'])
            <p><strong>Attachments:</strong> {{ count($email['attachments'] ?? []) }}</p>
            @endif
        </div>
    </div>
</div>

<div class="email-body">
    @if($email['body']['contentType'] === 'html')
    {!! $email['body']['content'] !!}
    @else
    <pre>{{ $email['body']['content'] }}</pre>
    @endif
</div>

@if(!empty($email['hasAttachments']) && $email['hasAttachments'])
<div class="mt-4">
    <h4>Attachments</h4>
    @forelse($email['attachments'] as $attachment)
    <div class="attachment-item">
        <p><strong>{{ $attachment['name'] ?? 'Unmamed Attachment' }}</strong>
            @if(isset($attachment['size']))
            ({{ round($attachment['size']/1024, 2) }} KB)
            @endif
        </p>
        @if(isset($attachment['id']))
        <a href="{{ route('outlook.attachment.download', ['emailId' => $email['id'], 'attachmentId' => $attachment['id']]) }}"
            class="btn btn-sm btn-primary">
            Download
        </a>
        @endif
    </div>
    @empty
    <div class="alert alert-info">No attachments available</div>
    @endforelse
</div>
@endif
@endsection