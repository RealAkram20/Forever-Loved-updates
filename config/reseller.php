<?php

use App\Support\ResellerDomain;

/*
|--------------------------------------------------------------------------
| Reseller program
|--------------------------------------------------------------------------
|
| Both values derive from APP_URL, so an install is already correct for the
| domain it is actually served from. Nothing here names a domain we might not
| own — the previous hardcoded fallback meant every install that had not set
| RESELLER_APP_DOMAIN minted addresses on a domain answering for nobody.
|
| RESELLER_APP_DOMAIN only needs setting when the reseller base domain is
| genuinely different from the app's own host — e.g. the dashboard runs on
| app.example.com but reseller sites are minted under sites.example.com.
|
| See App\Support\ResellerDomain for the derivation rules.
|
*/

$appUrl = env('APP_URL', 'http://localhost');

return [
    // Base domain reseller subdomains are minted under (e.g. acme.<domain>).
    // Kept separate from APP_URL, which mixes scheme/host/path.
    'domain' => env('RESELLER_APP_DOMAIN') ?: ResellerDomain::fromAppUrl($appUrl),

    // Host prefix for the custom-domain ownership challenge: <prefix>.<their domain>.
    'verification_prefix' => env('RESELLER_VERIFY_PREFIX') ?: ResellerDomain::verificationPrefix($appUrl),
];
