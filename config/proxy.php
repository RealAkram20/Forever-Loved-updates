<?php

return [
    /*
    | Where Traefik's file provider watches for per-custom-domain router files.
    |
    | In production this is a bind mount of /data/coolify/proxy/dynamic/custom-domains
    | (see docker-compose.yaml); Traefik reloads the folder on every change, so a file
    | written here routes a reseller's domain and triggers its certificate without any
    | restart. Unset (the default everywhere else), the sync command is a no-op.
    */
    'custom_domains_dir' => env('PROXY_CUSTOM_DOMAINS_DIR'),
];
