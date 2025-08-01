<!-- resources/views/email-view.blade.php -->
<!DOCTYPE html>
<html>

<head>
    <title>Email Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .email-header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .email-body {
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            background-color: white;
        }

        .attachment-item {
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            margin-bottom: 10px;
            background-color: #f8f9fa;
        }
    </style>
</head>

<body>
    <div class="container mt-4">
        <a href="{{ route('outlook.emails') }}" class="btn btn-secondary mb-3">← Back to Emails</a>

        <div class="email-header">
            <h2>{{ $email['subject'] ?? 'No Subject' }}</h2>

            <div class="row mt-3">
                <div class="col-md-6">
                    <p><strong>From:</strong>
                        {{ $email['from']['emailAddress']['name'] ?? 'N/A' }}
                        &lt;{{ $email['from']['emailAddress']['address'] ?? 'N/A' }}&gt;
                    </p>

                    <p><strong>To:</strong>
                        @foreach($email['toRecipients'] as $recipient)
                        {{ $recipient['emailAddress']['name'] ?? $recipient['emailAddress']['address'] }};
                        @endforeach
                    </p>

                    @if(!empty($email['ccRecipients']))
                    <p><strong>CC:</strong>
                        @foreach($email['ccRecipients'] as $recipient)
                        {{ $recipient['emailAddress']['name'] ?? $recipient['emailAddress']['address'] }};
                        @endforeach
                    </p>
                    @endif
                </div>

                <div class="col-md-6">
                    <p><strong>Date:</strong>
                        {{ \Carbon\Carbon::parse($email['sentDateTime'])->format('M j, Y g:i A') }}
                    </p>

                    @if(!empty($email['hasAttachments'] && $email['hasAttachments'])
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

        @if(!empty($email['hasAttachments'] && $email['hasAttachments'])
        <div class="mt-4">
            <h4>Attachments</h4>
            @foreach($email['attachments'] as $attachment)
            <div class="attachment-item">
                <p><strong>{{ $attachment['name'] }}</strong> ({{ round($attachment['size']/1024, 2) }} KB)</p>
                <a href="{{ route('outlook.attachment.download', ['emailId' => $email['id'], 'attachmentId' => $attachment['id']]) }}"
                    class="btn btn-sm btn-primary">
                    Download
                </a>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</body>

</html>