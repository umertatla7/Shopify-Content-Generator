<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Opening GrowShopHigh</title>
        <meta name="shopify-api-key" content="{{ $apiKey }}">
        <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
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
                max-width: 540px;
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
            .error {
                margin-top: 12px;
                color: #be123c;
            }
        </style>
    </head>
    <body>
        <div class="wrap">
            <div class="panel">
                <h1>Opening GrowShopHigh</h1>
                <p>Confirming your Shopify session so the app can open securely inside Shopify admin.</p>
                <p id="session-error" class="error" hidden></p>
            </div>
        </div>

        <form id="session-form" method="post" action="{{ $target }}">
            @csrf
            <input type="hidden" name="shop" value="{{ $shop }}">
            <input type="hidden" name="host" value="{{ $host }}">
            <input type="hidden" name="embedded" value="1">
            <input type="hidden" name="handoff" value="{{ $handoff }}">
            <input type="hidden" name="id_token" id="id-token">
        </form>

        <script>
            (async () => {
                const error = document.getElementById('session-error');
                const form = document.getElementById('session-form');
                const tokenInput = document.getElementById('id-token');

                try {
                    if (!window.shopify || typeof window.shopify.idToken !== 'function') {
                        throw new Error('Shopify did not provide an embedded session token.');
                    }

                    tokenInput.value = await window.shopify.idToken();
                    form.submit();
                } catch (sessionError) {
                    error.textContent = sessionError.message || 'Could not confirm your Shopify session. Reopen the app from Shopify admin.';
                    error.hidden = false;
                }
            })();
        </script>
    </body>
</html>
