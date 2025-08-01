<!-- resources/views/outlook/emails.blade.php -->
@extends('outlook.layout.app')

@section('title', 'Outlook Emails')

@section('disconnect_button')
    <a href="{{ route('outlook.disconnect') }}" class="btn btn-danger">Disconnect Outlook</a>
@endsection

@section('content')
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
@endsection