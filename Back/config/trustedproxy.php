<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Configure this in production to the IP/CIDR of the trusted Nginx or load
    | balancer that terminates TLS. Leave null locally so arbitrary clients
    | cannot spoof HTTPS using X-Forwarded-* headers.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),
];
