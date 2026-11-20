<?php

// Pelican loads this file as config('mikrotik-nat-sync').
// Values are stored in .env by the plugin settings form (MIKROTIK_NAT_SYNC_* prefix).

return [
    // Router address. "host" or "host:port"; a scheme prefix is ignored.
    'host' => env('MIKROTIK_NAT_SYNC_IP'),
    'port' => env('MIKROTIK_NAT_SYNC_PORT', '9080'),

    // http (RouterOS "www" service) or https ("www-ssl").
    'scheme' => env('MIKROTIK_NAT_SYNC_SCHEME', 'http'),

    // Set to false only for a self-signed router certificate.
    'verify_tls' => env('MIKROTIK_NAT_SYNC_VERIFY_TLS', true),

    'username' => env('MIKROTIK_NAT_SYNC_USER'),
    'password' => env('MIKROTIK_NAT_SYNC_PASSWORD'),

    // WAN interface for in-interface=. Empty = match traffic addressed to the
    // router itself (dst-address-type=local): works from WAN and for hairpin NAT,
    // and never hijacks outgoing traffic to other hosts on the same ports.
    'interface' => env('MIKROTIK_NAT_SYNC_INTERFACE'),

    // Comma separated ports that must never be forwarded.
    'forbidden_ports' => env('MIKROTIK_NAT_SYNC_FORBIDDEN_PORTS', ''),

    // Also never forward ports of enabled router services (ssh, winbox, www, api...).
    'protect_router_services' => env('MIKROTIK_NAT_SYNC_PROTECT_SERVICES', true),

    // Target for allocations bound to 0.0.0.0 (Wings listening on all interfaces).
    // Empty = such allocations are skipped with a warning.
    'default_target' => env('MIKROTIK_NAT_SYNC_DEFAULT_TARGET'),

    'interval' => env('MIKROTIK_NAT_SYNC_INTERVAL', 'everyFiveMinutes'),

    'timeout' => 10,
];
