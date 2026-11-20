<?php

namespace Avalon\MikroTikNATSync\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SyncMikrotikCommand extends Command
{
    protected $signature = 'mikrotik:sync
        {--dry-run : Show what would change without touching the router}
        {--force : Allow removing all managed rules when Pelican has no allocations}';

    protected $description = 'Synchronize MikroTik dst-nat rules with Pelican allocations';

    /** Comment prefix that marks rules owned by this plugin. */
    private const TAG = 'Pelican:';

    /** Rule properties compared to decide whether an existing rule is up to date. */
    private const COMPARED = ['to-addresses', 'to-ports', 'in-interface', 'dst-address-type'];

    private PendingRequest $http;

    private string $url;

    private int $failures = 0;

    public function handle(): int
    {
        $c = config('mikrotik-nat-sync', []);
        $dryRun = (bool) $this->option('dry-run');

        $host = preg_replace('#^https?://#i', '', trim((string) ($c['host'] ?? '')));
        if ($host === '' || empty($c['username']) || empty($c['password'])) {
            $this->error('MikroTik settings are not configured (address, username, password).');

            return self::FAILURE;
        }

        $scheme = ($c['scheme'] ?? 'http') === 'https' ? 'https' : 'http';
        $base = str_contains($host, ':') ? $host : $host . ':' . ($c['port'] ?? '9080');
        $this->url = "{$scheme}://{$base}/rest/ip/firewall/nat";

        $this->http = Http::withBasicAuth((string) $c['username'], (string) $c['password'])
            ->acceptJson()
            ->timeout((int) ($c['timeout'] ?? 10));
        if ($scheme === 'https' && !filter_var($c['verify_tls'] ?? true, FILTER_VALIDATE_BOOL)) {
            $this->http = $this->http->withoutVerifying();
        }

        try {
            $existing = $this->http->get($this->url);
        } catch (\Throwable $e) {
            $this->error('Cannot reach MikroTik: ' . $e->getMessage());

            return self::FAILURE;
        }
        if (!$existing->successful() || !is_array($existing->json())) {
            $this->error("MikroTik API error (HTTP {$existing->status()}): " . mb_substr($existing->body(), 0, 300));

            return self::FAILURE;
        }

        $protected = $this->protectedPorts($c);
        $interface = trim((string) ($c['interface'] ?? ''));
        $defaultTarget = trim((string) ($c['default_target'] ?? ''));

        // ---- Desired state from Pelican -------------------------------------------------
        $allocations = DB::table('allocations')
            ->join('servers', 'allocations.server_id', '=', 'servers.id')
            ->select('servers.uuid', 'servers.name', 'allocations.ip', 'allocations.port')
            ->orderBy('allocations.port')
            ->get();

        $desired = [];
        foreach ($allocations as $a) {
            $port = (int) $a->port;
            if (isset($protected[$port])) {
                $this->warn("Port {$port} ({$a->name}) is forbidden/protected, skipped.");

                continue;
            }

            $target = $a->ip === '0.0.0.0' ? $defaultTarget : $a->ip;
            if ($target === '' || !filter_var($target, FILTER_VALIDATE_IP)) {
                $this->warn("Port {$port} ({$a->name}) has allocation IP {$a->ip} and no valid target, skipped.");

                continue;
            }

            foreach (['tcp', 'udp'] as $proto) {
                $key = "{$port}/{$proto}";
                if (isset($desired[$key])) {
                    $this->warn("Port {$key} is allocated more than once, keeping {$desired[$key]['comment']}.");

                    continue;
                }

                $rule = [
                    'chain' => 'dstnat',
                    'action' => 'dst-nat',
                    'protocol' => $proto,
                    'dst-port' => (string) $port,
                    'to-addresses' => $target,
                    'to-ports' => (string) $port,
                    'comment' => self::TAG . " {$a->name} ({$a->uuid})",
                ];
                if ($interface !== '') {
                    $rule['in-interface'] = $interface;
                } else {
                    $rule['dst-address-type'] = 'local';
                }
                $desired[$key] = $rule;
            }
        }

        // ---- Current managed rules on the router ---------------------------------------
        $current = [];
        foreach ($existing->json() as $rule) {
            if (!is_array($rule) || !str_starts_with((string) ($rule['comment'] ?? ''), self::TAG) || !isset($rule['.id'])) {
                continue;
            }
            $key = ($rule['dst-port'] ?? '') . '/' . ($rule['protocol'] ?? '');
            $current[$key][] = $rule;
        }

        $managed = array_sum(array_map('count', $current));
        if ($desired === [] && $managed > 0 && !$this->option('force')) {
            $this->error("Pelican returned no allocations but the router has {$managed} managed rules. "
                . 'Refusing to delete them all; re-run with --force if this is intended.');

            return self::FAILURE;
        }

        // ---- Reconcile --------------------------------------------------------------------
        $unchanged = 0;
        $toDelete = [];

        foreach ($current as $key => $rules) {
            // Keep at most one rule per key, and only if it already matches the desired state.
            // Anything else (stale, outdated, duplicate) is deleted; a missing rule is created.
            $keep = null;
            foreach ($rules as $rule) {
                if ($keep === null && isset($desired[$key]) && $this->matches($rule, $desired[$key])) {
                    $keep = $rule;

                    continue;
                }
                $why = !isset($desired[$key]) ? 'no longer allocated' : ($keep ? 'duplicate' : 'outdated');
                $toDelete[] = [$key, $why, $rule];
            }
            if ($keep !== null) {
                $unchanged++;
                unset($desired[$key]);
            }
        }

        // Create first, delete after: a replaced rule never leaves its port unforwarded.
        $created = $deleted = 0;
        $notCreated = [];
        foreach ($desired as $key => $rule) {
            $match = isset($rule['in-interface']) ? "in-interface={$rule['in-interface']}" : 'dst-address-type=local';
            $this->line("  + {$key} -> {$rule['to-addresses']} ({$match}): {$rule['comment']}");
            if ($this->create($rule, $dryRun)) {
                $created++;
            } else {
                $notCreated[$key] = true;
            }
        }
        foreach ($toDelete as [$key, $why, $rule]) {
            if ($why === 'outdated' && isset($notCreated[$key])) {
                // The replacement failed: keep the old rule so the port stays forwarded.
                $this->warn("  = {$key} kept (replacement failed): {$rule['comment']}");

                continue;
            }
            $this->line("  - {$key} ({$why}): {$rule['comment']}");
            if ($this->delete($rule['.id'], $dryRun)) {
                $deleted++;
            }
        }

        $summary = sprintf('%screated %d, deleted %d, unchanged %d, failed requests %d.',
            $dryRun ? '[dry-run] ' : '', $created, $deleted, $unchanged, $this->failures);
        $this->failures ? $this->error($summary) : $this->info($summary);

        return $this->failures ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Ports that must never be forwarded: the configured list plus, optionally,
     * the ports of enabled router services (so a game allocation can't hijack SSH/WinBox/API).
     *
     * @param  array<string, mixed>  $c
     * @return array<int, true>
     */
    private function protectedPorts(array $c): array
    {
        $ports = [];
        foreach (preg_split('/[\s,;]+/', (string) ($c['forbidden_ports'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $p) {
            if (ctype_digit($p)) {
                $ports[(int) $p] = true;
            }
        }

        if (filter_var($c['protect_router_services'] ?? true, FILTER_VALIDATE_BOOL)) {
            $serviceUrl = preg_replace('#/firewall/nat$#', '/service', $this->url);
            try {
                $services = $this->http->get($serviceUrl);
                foreach ($services->successful() ? (array) $services->json() : [] as $s) {
                    $disabled = filter_var($s['disabled'] ?? false, FILTER_VALIDATE_BOOL);
                    if (!$disabled && isset($s['port']) && ctype_digit((string) $s['port'])) {
                        $ports[(int) $s['port']] = true;
                    }
                }
                if (!$services->successful()) {
                    $this->warn('Could not read router services (HTTP ' . $services->status() . '); only the configured forbidden ports apply.');
                }
            } catch (\Throwable $e) {
                $this->warn('Could not read router services: ' . $e->getMessage());
            }
        }

        return $ports;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $want
     */
    private function matches(array $rule, array $want): bool
    {
        if (filter_var($rule['disabled'] ?? false, FILTER_VALIDATE_BOOL)) {
            return false;
        }
        foreach (self::COMPARED as $field) {
            if ((string) ($rule[$field] ?? '') !== (string) ($want[$field] ?? '')) {
                return false;
            }
        }

        return true;
    }

    private function delete(string $id, bool $dryRun): bool
    {
        if ($dryRun) {
            return true;
        }

        // RouterOS ids look like "*2C5"; the REST API rejects a percent-encoded "*".
        if (!preg_match('/^\*[0-9A-Fa-f]+$/', $id)) {
            $this->failures++;
            $this->error("    delete {$id} skipped: unexpected rule id");

            return false;
        }

        return $this->send(fn () => $this->http->delete($this->url . '/' . $id), "delete {$id}");
    }

    /**
     * @param  array<string, string>  $rule
     */
    private function create(array $rule, bool $dryRun): bool
    {
        if ($dryRun) {
            return true;
        }

        return $this->send(fn () => $this->http->put($this->url, $rule), "add {$rule['dst-port']}/{$rule['protocol']}");
    }

    private function send(\Closure $request, string $what): bool
    {
        try {
            $response = $request();
        } catch (\Throwable $e) {
            $this->failures++;
            $this->error("    {$what} failed: " . $e->getMessage());

            return false;
        }
        if (!$response->successful()) {
            $this->failures++;
            $this->error("    {$what} failed (HTTP {$response->status()}): " . mb_substr($response->body(), 0, 200));

            return false;
        }

        return true;
    }
}
