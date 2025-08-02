<!-- resources/views/layouts/app.blade.php -->
<!DOCTYPE html>
<html>

<head>
    <title>@yield('title') | Outlook Integration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/common.css') }}">
    @stack('styles')
</head>

<body>
    <div class="container mt-4">
        @if(View::hasSection('back_button'))
        @yield('back_button')
        @endif

        @yield('content')

        @if(View::hasSection('disconnect_button'))
        <div class="mt-4">
            @yield('disconnect_button')
        </div>
        @endif
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    @stack('scripts')
</body>

</html>