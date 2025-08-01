<!-- resources/views/emails.blade.php -->
<!DOCTYPE html>
<html>

<head>
    <title>Outlook Emails</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <h1>Your Outlook Emails</h1>

        <div class="row">
            <div class="col-md-6">
                <h2>Inbox</h2>
                <ul class="list-group">
                    @foreach($inbox as $email)
                    <li class="list-group-item" style="cursor: pointer;"
                        onclick="window.location.href='{{ route('outlook.email.show', $email['id']) }}'">
                        <strong>{{ $email['from']['emailAddress']['name'] }}</strong>
                        <p>{{ $email['subject'] }}</p>
                        <small>{{ \Carbon\Carbon::parse($email['receivedDateTime'])->diffForHumans() }}</small>
                    </li>
                    @endforeach
                </ul>
            </div>

            <div class="col-md-6">
                <h2>Sent Items</h2>
                <ul class="list-group">
                    @foreach($sent as $email)
                    <li class="list-group-item">
                        <strong>To: {{ $email['toRecipients'][0]['emailAddress']['name'] ?? 'N/A' }}</strong>
                        <p>{{ $email['subject'] }}</p>
                        <small>{{ \Carbon\Carbon::parse($email['sentDateTime'])->diffForHumans() }}</small>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="mt-4">
            <a href="{{ route('outlook.disconnect') }}" class="btn btn-danger">Disconnect Outlook</a>
        </div>
    </div>
</body>

</html>