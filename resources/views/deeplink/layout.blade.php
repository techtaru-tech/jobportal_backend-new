{{--
    Shared shell for the two share-link pages.

    Deliberately one self-contained file with inline CSS: this page is the
    first thing a stranger sees after tapping a link a friend sent, often on a
    slow connection, and a stylesheet request would be a second round trip
    before anything renders.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>

    @yield('meta')

    <style>
        :root {
            --ink: #16181d;
            --muted: #6b7280;
            --line: #e5e7eb;
            --brand: #0f766e;
            --brand-soft: #ecfdf5;
            --bg: #f7f8fa;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font: 16px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .wrap { max-width: 560px; margin: 0 auto; padding: 24px 20px 48px; }

        .card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 24px;
        }

        .kicker {
            font-size: 11px;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--muted);
            font-weight: 700;
        }

        h1 { font-size: 24px; line-height: 1.25; margin: 8px 0 4px; }

        .org { color: var(--muted); margin: 0 0 16px; }

        .facts { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 20px; padding: 0; list-style: none; }

        .facts li {
            background: var(--brand-soft);
            color: #0b5f59;
            border-radius: 999px;
            padding: 5px 12px;
            font-size: 13px;
            font-weight: 600;
        }

        h2 { font-size: 13px; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin: 24px 0 8px; }

        p, li { overflow-wrap: anywhere; }

        .btn {
            display: block;
            text-align: center;
            text-decoration: none;
            font-weight: 700;
            border-radius: 12px;
            padding: 15px 18px;
            margin-top: 10px;
        }

        .btn-primary { background: var(--brand); color: #fff; }

        .btn-secondary { background: #fff; color: var(--ink); border: 1px solid var(--line); }

        .foot { color: var(--muted); font-size: 13px; text-align: center; margin-top: 24px; }
    </style>
</head>
<body>
    <div class="wrap">
        @yield('body')
        <p class="foot">{{ config('app.name') }}</p>
    </div>

    @yield('scripts')
</body>
</html>
