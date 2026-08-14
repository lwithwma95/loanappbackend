<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ASRLM API Server</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
            background: #f6f7f9;
            color: #1a1a1a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 1rem;
            padding: 2.5rem 2rem;
            max-width: 460px;
            width: 100%;
            box-shadow: 0 1px 6px rgba(0,0,0,.06);
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .07em;
            text-transform: uppercase;
            color: #15803d;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 999px;
            padding: .2rem .75rem;
            margin-bottom: 1.25rem;
        }
        .dot {
            width: .42rem; height: .42rem;
            border-radius: 50%;
            background: #22c55e;
            animation: pulse 2s cubic-bezier(.4,0,.6,1) infinite;
        }
        @keyframes pulse { 50% { opacity: .35; } }
        h1 {
            font-size: 1.3rem;
            font-weight: 800;
            color: #111827;
            margin-bottom: .3rem;
        }
        .subtitle {
            font-size: .78rem;
            color: #6b7280;
            margin-bottom: 1.75rem;
            line-height: 1.55;
        }
        .notice {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-left: 3px solid #f59e0b;
            border-radius: .5rem;
            padding: .8rem 1rem;
            font-size: .77rem;
            color: #78350f;
            line-height: 1.6;
            margin-bottom: 1.75rem;
        }
        .notice strong { font-weight: 700; display: block; margin-bottom: .2rem; }
        .meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .55rem;
            margin-bottom: 1.75rem;
        }
        .meta-item {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: .5rem;
            padding: .55rem .85rem;
        }
        .meta-label {
            font-size: .62rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #9ca3af;
            margin-bottom: .12rem;
        }
        .meta-value {
            font-size: .78rem;
            font-weight: 600;
            color: #374151;
            font-family: ui-monospace, 'Cascadia Code', Menlo, monospace;
        }
        .env-local       { color: #b45309; }
        .env-production  { color: #15803d; }
        .env-staging     { color: #6d28d9; }
        hr {
            border: none;
            border-top: 1px solid #f3f4f6;
            margin-bottom: 1.2rem;
        }
        .footer {
            font-size: .68rem;
            color: #9ca3af;
            text-align: center;
            line-height: 1.7;
        }
    </style>
</head>
<body>
    <div class="card">

        <div class="badge">
            <span class="dot"></span>
            Server Online
        </div>

        <h1>ASRLM — API Server</h1>
        <p class="subtitle">
            Assam State Rural Livelihoods Mission &mdash; DAY-NRLM<br>
            Backend REST API &amp; Data Services
        </p>

        <div class="notice">
            <strong>This is a backend API server.</strong>
            This server cannot be used directly from a browser. All data requests
            must be made from the authorised frontend application using valid
            API credentials over <code>/api</code>.
        </div>

        <div class="meta">
            <div class="meta-item">
                <div class="meta-label">Environment</div>
                <div class="meta-value env-{{ app()->environment() }}">{{ app()->environment() }}</div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Laravel</div>
                <div class="meta-value">v{{ app()->version() }}</div>
            </div>
            <div class="meta-item">
                <div class="meta-label">PHP</div>
                <div class="meta-value">v{{ PHP_MAJOR_VERSION }}.{{ PHP_MINOR_VERSION }}.{{ PHP_RELEASE_VERSION }}</div>
            </div>
            <div class="meta-item">
                <div class="meta-label">API Base</div>
                <div class="meta-value">/api</div>
            </div>
        </div>

        <hr>

        <p class="footer">
            &copy; {{ date('Y') }} Assam State Rural Livelihoods Mission &nbsp;&middot;&nbsp;
            Government of Assam &nbsp;&middot;&nbsp; DAY-NRLM
        </p>

    </div>
</body>
</html>
