{{-- | KB @CerberRus00 - Nexus Invest Team --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-900 antialiased bg-white">
        <div class="min-h-screen flex flex-col">
            <div class="flex-1 flex flex-col items-center justify-center px-4 py-10">
                <div class="w-full max-w-md">
                    <div class="flex items-center justify-between gap-4">
                        <a href="{{ route('login') }}" class="text-slate-800" aria-label="GANIMED AML">
                            <x-application-logo class="h-10 w-[13.75rem]" />
                        </a>
                        <div class="locale-switch" role="group" aria-label="{{ __('aml.language') }}">
                            <a href="{{ route('locale.switch', 'ru') }}" @aria-current="app()->getLocale() === 'ru' ? 'true' : 'false'">RU</a>
                            <a href="{{ route('locale.switch', 'en') }}" @aria-current="app()->getLocale() === 'en' ? 'true' : 'false'">EN</a>
                        </div>
                    </div>
                    <div class="mt-6 border border-slate-200 p-6">
                        {{ $slot }}
                    </div>
                </div>
            </div>
            <x-site-footer />
        </div>
    </body>
</html>
