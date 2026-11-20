# MikroTik NAT Sync for Pelican Panel

Keeps the dst-nat (port forwarding) rules on a MikroTik router (RouterOS 7, REST API)
in sync with the server allocations in [Pelican Panel](https://pelican.dev).

## How it works

Every few minutes (`php artisan mikrotik:sync`, run by the Laravel scheduler):

* for every allocation that belongs to a server, a TCP and a UDP `dst-nat` rule is ensured on the router;
* rules for allocations that no longer exist are removed;
* rules that drifted (other target IP, other matcher, disabled) are replaced — the new rule is
  created **before** the old one is removed, so the port is never left unforwarded;
* only rules whose comment starts with `Pelican:` are ever touched.

Safety:

* **Forbidden ports** — a comma separated list of ports that are never forwarded.
* **Router services are protected** (on by default) — ports of enabled services in `/ip service`
  (SSH, WinBox, API, www…) are never forwarded, even if a server is allocated one of them.
* **Mass-delete guard** — if Pelican suddenly returns no allocations while the router has managed
  rules, nothing is deleted unless you run with `--force`.
* Without a WAN interface set, rules use `dst-address-type=local`: they match only traffic addressed
  to the router itself (works from the internet and for hairpin NAT) and never redirect your LAN's
  outgoing connections to other hosts on the same ports.

## Router setup

Enable the REST API (RouterOS 7):

```routeros
# HTTPS (recommended)
/ip service set www-ssl disabled=no certificate=<your-cert> address=<panel-ip>/32
# or plain HTTP inside a trusted LAN
/ip service set www disabled=no address=<panel-ip>/32
```

Create a dedicated user, limited to the panel's address:

```routeros
/user group add name=pelican-nat policy=read,write,rest-api
/user add name=pelican group=pelican-nat address=<panel-ip>/32 password=<strong-password>
```

For hairpin NAT (reaching game servers by the public IP from your LAN) you also need a
`srcnat masquerade` rule for LAN → LAN traffic.

## Installation

1. Pelican admin → **Plugins** → **Import**, paste
   `https://github.com/avalon0077/mikrotik-nat-sync/archive/refs/heads/main.zip`
   (or upload that ZIP).
2. Install, then open the plugin settings (gear icon) and fill in the router address, port,
   protocol, credentials and options.
3. Preview the changes before the first real run:

```bash
php artisan mikrotik:sync --dry-run
```

## Settings

| Setting | `.env` key | Default |
|---|---|---|
| Router address (`host` or `host:port`) | `MIKROTIK_NAT_SYNC_IP` | — |
| REST port | `MIKROTIK_NAT_SYNC_PORT` | `9080` |
| Protocol `http` / `https` | `MIKROTIK_NAT_SYNC_SCHEME` | `http` |
| Verify HTTPS certificate | `MIKROTIK_NAT_SYNC_VERIFY_TLS` | `true` |
| Username / password | `MIKROTIK_NAT_SYNC_USER` / `_PASSWORD` | — |
| WAN interface (optional) | `MIKROTIK_NAT_SYNC_INTERFACE` | empty → `dst-address-type=local` |
| Target IP for `0.0.0.0` allocations | `MIKROTIK_NAT_SYNC_DEFAULT_TARGET` | empty → skipped |
| Forbidden ports | `MIKROTIK_NAT_SYNC_FORBIDDEN_PORTS` | empty |
| Protect router service ports | `MIKROTIK_NAT_SYNC_PROTECT_SERVICES` | `true` |
| Interval | `MIKROTIK_NAT_SYNC_INTERVAL` | `everyFiveMinutes` |

## Changelog

* **1.2.0** — reconcile drifted rules (target/matcher), `dst-address-type=local` instead of
  match-all rules, router service ports protected, HTTPS support, configurable target for
  `0.0.0.0` allocations (no more hard-coded IP), duplicate cleanup, mass-delete guard,
  `--dry-run`, API errors reported and reflected in the exit code, schedule registered in
  the service provider (once per process), settings read through `config()`.
* **1.1.1** — `api_version`, panel beta36 settings contract.

## License

MIT
