@php
    // Self-contained on purpose: no Vite assets, no database — so a 500 or the
    // maintenance page still renders when the rest of the app can't.
    $home = request()->is('admin', 'admin/*') ? url('/admin') : url('/');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ config('app.name', 'Binary Business') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <style>
        :root { --bg: #f7f7f5; --card: #fff; --text: #1b1b18; --muted: #62605b; --border: #e3e3e0; --accent: #2a78d6; }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #0a0a0a; --card: #161615; --text: #ededec; --muted: #a1a09a; --border: #3e3e3a; --accent: #3987e5; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px;
               background: var(--bg); color: var(--text);
               font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans Bengali", "Noto Sans", sans-serif; }
        main { width: 100%; max-width: 480px; background: var(--card); border: 1px solid var(--border);
               border-radius: 12px; padding: 32px; }
        .mark { width: 40px; height: 40px; }
        .code { margin: 24px 0 0; color: var(--muted); font-size: 14px; font-variant-numeric: tabular-nums; }
        h1 { margin: 4px 0 0; font-size: 24px; line-height: 1.3; }
        .bn { margin: 2px 0 0; font-size: 18px; color: var(--muted); }
        p.msg { margin: 16px 0 0; color: var(--muted); }
        a.btn { display: inline-block; margin-top: 24px; padding: 8px 16px; border-radius: 8px;
                background: var(--accent); color: #fff; text-decoration: none; font-weight: 600; }
        a.btn:focus-visible { outline: 3px solid var(--text); outline-offset: 2px; }
    </style>
</head>
<body>
    <main>
        <svg class="mark" viewBox="0 0 40 40" aria-hidden="true">
            <rect width="40" height="40" rx="9" fill="var(--accent)"/>
            <g transform="translate(6 6) scale(0.7)">
                <path d="M20 11 9.5 29M20 11l10.5 18" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round"/>
                <circle cx="20" cy="9" r="6" fill="#fff"/><circle cx="9" cy="30" r="5.5" fill="#fff"/><circle cx="31" cy="30" r="5.5" fill="#fff"/>
            </g>
        </svg>
        <p class="code">Error @yield('code') · ত্রুটি</p>
        <h1>@yield('title')</h1>
        <p class="bn" lang="bn">@yield('title_bn')</p>
        <p class="msg">@yield('message')</p>
        <p class="msg" lang="bn">@yield('message_bn')</p>
        @unless (View::hasSection('no_home'))
            <a class="btn" href="{{ $home }}">Go back home · হোমে ফিরুন</a>
        @endunless
    </main>
</body>
</html>
