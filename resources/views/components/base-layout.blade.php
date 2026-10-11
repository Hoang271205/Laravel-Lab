@php
    use Illuminate\Support\Facades\Session;
    use Illuminate\Support\Facades\App;
    
    $theme = Session::get('theme', 'theme-light');
    
    $locale = App::currentLocale();
    
    $bg_background = 'bg-background';
    
    if ($theme == 'theme-light') {
        $bg_background = 'bg-gradient-to-r from-rose-100 to-teal-100';
    }
@endphp

<!DOCTYPE html>
<html lang="{{ $locale }}" class="{{ $theme }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="manifest" href="/site.webmanifest">

    <title>{{ $page_title ?? 'Coffee Shops' }}</title>

    @vite('resources/css/app.css')
    @livewireStyles
    @stack('styles')
</head>

<body class="{{ $bg_background }} relative">

@yield('content')

@livewireScripts
<script>window.theme = '{{ $theme }}'</script>
@vite('resources/js/app.js')
@stack('scripts')
@yield('components')
</body>

</html>
