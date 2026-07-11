<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Redirecting to Shopify</title>
        <style>
            body {
                margin: 0;
                font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                background: #f8fafc;
                color: #0f172a;
            }
            .wrap {
                min-height: 100vh;
                display: grid;
                place-items: center;
                padding: 24px;
            }
            .panel {
                max-width: 520px;
                width: 100%;
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                padding: 24px;
                box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            }
            h1 {
                margin: 0 0 8px;
                font-size: 20px;
            }
            p {
                margin: 0;
                color: #475569;
                line-height: 1.6;
            }
            a {
                color: #0f766e;
            }
        </style>
    </head>
    <body>
        <div class="wrap">
            <div class="panel">
                <h1>Redirecting to Shopify</h1>
                <p>{{ $message ?? 'Taking you to Shopify so the app can finish connecting securely.' }}</p>
                <p style="margin-top: 12px;">If nothing happens, <a href="{{ $target }}">continue to Shopify</a>.</p>
            </div>
        </div>

        <script>
            (() => {
                const target = @json($target);

                try {
                    if (window.top && window.top !== window.self) {
                        window.top.location.replace(target);
                        return;
                    }
                } catch (error) {
                    // Fall through to same-window redirect when cross-origin frame access is restricted.
                }

                try {
                    window.open(target, '_top');
                    return;
                } catch (error) {
                    // Fall through to same-window redirect when the browser blocks _top navigation.
                }

                window.location.replace(target);
            })();
        </script>
    </body>
</html>
