<?php

/*
| Payment gateway selection. Credentials live in config/services.php.
*/

return [

    // Gateways offered at checkout, in display order.
    'enabled' => array_values(array_filter(explode(',', (string) env('PAYMENT_GATEWAYS', 'bkash,nagad,sslcommerz')))),

    // A local simulator gateway (click "pay" / "fail" / "cancel") for
    // development without sandbox credentials. Never enabled in production.
    'simulator' => (bool) env('PAYMENT_SIMULATOR', env('APP_ENV') !== 'production'),

    // Timeout (seconds) for server-to-server gateway API calls.
    'http_timeout' => (int) env('PAYMENT_HTTP_TIMEOUT', 30),

];
