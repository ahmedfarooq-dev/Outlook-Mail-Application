{{-- resources/views/outlook/connect.blade.php --}}
@extends('outlook.layout.app')

@section('title', 'Outlook Integration')

@section('content')
<div class="text-center mt-5">
    <h1>Outlook Integration</h1>

    @if($accounts->count() > 0)
    <div class="mb-5">
        <h3>Connected Accounts</h3>
        <div class="row justify-content-center">
            <div class="col-md-8">
                @foreach($accounts as $account)
                <div
                    class="card mb-3 {{ $currentAccount && $currentAccount->id == $account->id ? 'border-primary' : '' }}">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div class="text-start">
                            <h5 class="card-title mb-1">{{ $account->name }}</h5>
                            <p class="card-text text-muted mb-0">{{ $account->email }}</p>
                            @if($currentAccount && $currentAccount->id == $account->id)
                            <span class="badge bg-primary">Current Account</span>
                            @endif
                        </div>
                        <div>
                            @if(!$currentAccount || $currentAccount->id != $account->id)
                            <a href="{{ route('outlook.switch', $account->id) }}"
                                class="btn btn-outline-primary btn-sm me-2">
                                Switch
                            </a>
                            @else
                            <a href="{{ route('outlook.emails') }}" class="btn btn-primary btn-sm me-2">
                                View Emails
                            </a>
                            @endif
                            <a href="{{ route('outlook.disconnect', $account->id) }}"
                                class="btn btn-outline-danger btn-sm"
                                onclick="return confirm('Are you sure you want to disconnect {{ $account->email }}?')">
                                Disconnect
                            </a>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <div class="mb-4">
        <h3>{{ $accounts->count() > 0 ? 'Add Another Account' : 'Connect Your First Account' }}</h3>
        <a href="{{ route('outlook.connect') }}" class="btn btn-primary btn-lg mt-3">
            <i class="fas fa-plus"></i> Connect Outlook Account
        </a>
    </div>

    @if($accounts->count() > 0)
    <div class="text-muted">
        <small>You can connect multiple Outlook accounts and switch between them.</small>
    </div>
    @endif
</div>
@endsection