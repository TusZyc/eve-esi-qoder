<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Hardcoded cookies from host curl (they expire, re-run if needed)
$gbf = 'CfDJ8L_TqTRJk6VMhB6rNwSa6n0TlEJoIzyDK8JkGDSsu5cGcIuI_hDozwfrCQzf5pUn20cbosRQ44c3g1M7C3bCkNxFu0KZVEIrGaE5ry4J-wlhjxdSDCZEckbdzeORRT1dAC0dnEuGYhWz-QoghrYEwS4';
$rd  = 'CfDJ8L_TqTRJk6VMhB6rNwSa6n0z18Nlr-x-a2bFR2JDTjA9hZbGfrN1c8QpOONL0obLG8Z5y1PO_9Yo7aNjtaCOkwUrEXQ1o-OvWVAsQZ1C_Sa40SsggPBw3fSfruLvJEFnASdTS-q5tv-vfFRMk6Emtic';

Illuminate\Support\Facades\Cache::put('kb:xsrf_cookies', [$rd, $gbf], 1800);
Illuminate\Support\Facades\Cache::forget('kb:xsrf_cookies_fail');

$check = Illuminate\Support\Facades\Cache::get('kb:xsrf_cookies');
if (!empty($check[0]) && !empty($check[1])) {
    echo "OK: cookies injected (ReDive=" . strlen($check[0]) . "b, GBF=" . strlen($check[1]) . "b)\n";
} else {
    echo "FAIL: cache is empty\n";
    exit(1);
}
