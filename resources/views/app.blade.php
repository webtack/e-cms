<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        {{-- Apply the stored (or system) theme before first paint to avoid a light-theme flash. --}}
        <script>
            (function () {
                try {
                    var stored = localStorage.getItem('theme');
                    var dark = stored === 'dark'
                        || ((stored === null || stored === 'system') && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    document.documentElement.classList.toggle('dark', dark);
                } catch (e) {}
            })();
        </script>

        {{-- Fonts, React refresh and the admin entries, from the CMS's own Vite instance (see AdminAssets). --}}
        @if ($ecmsAssets)
            {!! $ecmsAssets !!}
            @inertiaHead
        @endif
    </head>
    <body class="font-sans antialiased">
        @if ($ecmsAssets)
            @inertia
        @else
            <main style="max-width: 40rem; margin: 4rem auto; padding: 0 1rem; font-family: ui-sans-serif, system-ui, sans-serif; line-height: 1.5;">
                <h1 style="font-size: 1.25rem;">The admin assets are not published</h1>
                <p>The compiled admin frontend was not found in <code>public/vendor/ecms/build</code>. Publish it with:</p>
                <pre style="padding: 0.75rem 1rem; background: #f4f4f5; color: #18181b; overflow-x: auto;">php artisan vendor:publish --tag=ecms-assets --force</pre>
                <p>or run <code>php artisan ecms:install</code>.</p>
            </main>
        @endif
    </body>
</html>
