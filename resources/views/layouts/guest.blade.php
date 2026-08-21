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
    <body class="font-sans text-ink antialiased bg-ink-paper">
        <div class="min-h-screen flex flex-col">
            <div class="flex-1 flex flex-col items-center justify-center px-4 py-10">
                <div class="w-full max-w-md">
                    <div class="flex justify-end">
                        <div class="locale-switch" role="group" aria-label="{{ __('aml.language') }}">
                            <a href="{{ route('locale.switch', 'ru') }}" @aria-current="app()->getLocale() === 'ru' ? 'true' : 'false'">RU</a>
                            <a href="{{ route('locale.switch', 'en') }}" @aria-current="app()->getLocale() === 'en' ? 'true' : 'false'">EN</a>
                        </div>
                    </div>
                    <a href="{{ route('login') }}" class="mt-6 flex justify-center" aria-label="GANIMED AML">
                        <x-application-logo variant="hero" />
                    </a>
                    <div class="mt-6 ui-panel p-6">
                        {{ $slot }}
                    </div>
                </div>
            </div>
            <x-site-footer />
        </div>
    </body>
</html>
