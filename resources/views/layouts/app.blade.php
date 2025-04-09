@props(['title'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $metaTitle ?: 'Skilltogrow - Блог о персональном развитии, карьерном росте, самопознании и совершенствовании' }}</title>
        <meta name="description" content="{{ $metaDescription ?: 'Skilltogrow - Блог о персональном развитии, карьерном росте, самопознании и совершенствовании' }} ">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=arsenal:400,400i,700,700i" rel="stylesheet" />

        <!-- Favicons -->
        <link rel="apple-touch-icon" sizes="180x180" href="/storage/app/public/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/storage/app/public/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/storage/app/public/favicon-16x16.png">
        <link rel="manifest" href="/storage/app/public/site.webmanifest">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Styles -->
        @livewireStyles
    </head>
    <body class="font-sans antialiased">
        <x-banner />

        @include('layouts.partials.header')

        @yield('hero')

        <main class="container mx-auto px-5 flex flex-grow">
            {{ $slot }}
        </main>

        @include('layouts.partials.footer')

        @stack('modals')
        @livewireScripts
    </body>
</html>
