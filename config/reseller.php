<?php

return [
    // Base domain reseller subdomains are minted under (e.g. acme.<domain>).
    // Kept separate from APP_URL, which mixes scheme/host/path.
    'domain' => env('RESELLER_APP_DOMAIN', 'foreverloved.com'),
];
