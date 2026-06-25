<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MaintOps Internal Tools</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            align-items: center;
            background: #f4f7fb;
            color: #111827;
            display: flex;
            justify-content: center;
            margin: 0;
            min-height: 100vh;
            padding: 24px;
        }

        main {
            background: #ffffff;
            border: 1px solid #d9e2ef;
            border-radius: 8px;
            box-shadow: 0 18px 60px rgba(15, 23, 42, 0.12);
            max-width: 420px;
            padding: 32px;
            width: 100%;
        }

        h1 {
            color: #0f172a;
            font-size: 26px;
            line-height: 1.2;
            margin: 0 0 8px;
        }

        p {
            color: #475569;
            line-height: 1.5;
            margin: 0 0 24px;
        }

        label {
            color: #334155;
            display: block;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        input[type="email"],
        input[type="password"] {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            color: #0f172a;
            font: inherit;
            margin-bottom: 16px;
            padding: 12px 14px;
            width: 100%;
        }

        input:focus {
            border-color: #2563eb;
            outline: 2px solid rgba(37, 99, 235, 0.18);
        }

        .remember {
            align-items: center;
            color: #475569;
            display: flex;
            font-size: 14px;
            gap: 8px;
            margin-bottom: 20px;
        }

        .error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            color: #991b1b;
            margin-bottom: 18px;
            padding: 12px;
        }

        button {
            background: #2563eb;
            border: 0;
            border-radius: 6px;
            color: #ffffff;
            cursor: pointer;
            font: inherit;
            font-weight: 800;
            padding: 12px 18px;
            width: 100%;
        }
    </style>
</head>
<body>
    <main>
        <h1>Internal Tools</h1>
        <p>Private access for OpenAPI documentation and Telescope. Only active <strong>super_admin</strong> users can sign in.</p>

        @if ($errors->any())
            <div class="error">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('internal-tools.login.store') }}">
            @csrf

            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>

            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>

            <label class="remember">
                <input name="remember" type="checkbox" value="1">
                Remember this session
            </label>

            <button type="submit">Sign in</button>
        </form>
    </main>
</body>
</html>
