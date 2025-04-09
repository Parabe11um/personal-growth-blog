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
        @props(['title'])
        <!DOCTYPE html>
        <html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="csrf-token" content="{{ csrf_token() }}">

            <title>{{ $metaTitle ?: 'Skilltogrow - Блог о персональном развитии, карьерном росте, самопознании и совершенствовании' }}</title>
            <meta name="description" content="{{ $metaDescription ?: 'Skilltogrow - Блог о персональном развитии, карьерном росте, самопознании и совершенствовании' }} ">
            <meta name="yandex-verification" content="b9b2f4c3f88c28a5" />


            <!-- Fonts -->
            <link rel="preconnect" href="https://fonts.bunny.net">
            <link href="https://fonts.bunny.net/css?family=arsenal:400,400i,700,700i" rel="stylesheet" />

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
        <!-- Yandex.Metrika counter -->
        <script type="text/javascript" >
            (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
                m[i].l=1*new Date();
                for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
                k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
            (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

            ym(100023172, "init", {
                clickmap:true,
                trackLinks:true,
                accurateTrackBounce:true,
                webvisor:true
            });
        </script>
        <noscript><div><img src="https://mc.yandex.ru/watch/100023172" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
        <!-- /Yandex.Metrika counter -->
    </body>
</html>
