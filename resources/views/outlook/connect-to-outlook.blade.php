<!-- resources/views/connect-to-outlook.blade.php -->
@extends('outlook.layout.app')

@section('title', 'Connect to Outlook')

@section('content')
<div class="text-center mt-5">
    <h1>Outlook Integration</h1>
    <a href="{{ route('outlook.connect') }}" class="btn btn-primary btn-lg mt-3">Connect to Outlook</a>
</div>
@endsection