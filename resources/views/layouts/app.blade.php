{{-- | KB @CerberRus00 - Nexus Invest Team --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=ibm-plex-sans:400,500,600|ibm-plex-mono:400,500&display=swap" rel="stylesheet" />
        <link rel="icon" href="{{ asset('images/logo-gnd-mark.png') }}" type="image/png">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-ink bg-ink-paper">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:bg-ink focus:px-3 focus:py-2 focus:text-white">{{ __('aml.skip_content') }}</a>
        <div class="min-h-screen flex flex-col">
            @include('layouts.navigation')

            @isset($header)
                <header class="border-b border-ink-line bg-ink-paper">
                    <div class="page py-6">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main id="main" class="flex-1">
                {{ $slot }}
            </main>

            <x-site-footer />
        </div>
    </body>
</html>
