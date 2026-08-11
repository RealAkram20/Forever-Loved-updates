<?php

namespace App\Console\Commands;

use App\Services\CustomDomainProxySync;
use Illuminate\Console\Command;

class SyncCustomDomainProxyCommand extends Command
{
    protected $signature = 'domains:sync-proxy';

    protected $description = 'Write Traefik router files for every verified reseller custom domain';

    public function handle(CustomDomainProxySync $sync): int
    {
        $result = $sync->sync();

        if ($result === null) {
            $this->info('No proxy directory configured (PROXY_CUSTOM_DOMAINS_DIR) — nothing to do.');

            return self::SUCCESS;
        }

        $this->info("Proxy custom domains: {$result['written']} written, {$result['kept']} unchanged, {$result['removed']} removed.");

        return self::SUCCESS;
    }
}
