<?php

namespace Avalon\MikroTikNATSync;

use App\Contracts\Plugins\HasPluginSettings;
use App\Traits\EnvironmentWriterTrait;
use Avalon\MikroTikNATSync\Providers\MikroTikNATSyncPluginProvider;
use Filament\Contracts\Plugin as FilamentPlugin;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Panel;

class MikroTikNATSyncPlugin implements FilamentPlugin, HasPluginSettings
{
    use EnvironmentWriterTrait;

    public function getId(): string
    {
        return 'mikrotik-nat-sync';
    }

    // The console command and the schedule are registered by Pelican itself
    // (auto-discovered command + MikroTikNATSyncPluginProvider), nothing per panel.
    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void {}

    /**
     * @return array<string, mixed>
     */
    public function getSettingsFormData(): array
    {
        $c = config('mikrotik-nat-sync');

        return [
            'mk_ip' => $c['host'] ?? null,
            'mk_port' => $c['port'] ?? '9080',
            'mk_scheme' => $c['scheme'] ?? 'http',
            'mk_verify_tls' => (bool) ($c['verify_tls'] ?? true),
            'mk_user' => $c['username'] ?? null,
            'mk_pass' => $c['password'] ?? null,
            'mk_interface' => $c['interface'] ?? null,
            'mk_default_target' => $c['default_target'] ?? null,
            'mk_forbidden_ports' => $c['forbidden_ports'] ?? null,
            'mk_protect_services' => (bool) ($c['protect_router_services'] ?? true),
            'mk_interval' => $c['interval'] ?? 'everyFiveMinutes',
        ];
    }

    /**
     * @return \Filament\Schemas\Components\Component[]
     */
    public function getSettingsForm(): array
    {
        return [
            TextInput::make('mk_ip')
                ->label('MikroTik address')
                ->helperText('IP or hostname of the router (optionally host:port).')
                ->required(),
            TextInput::make('mk_port')
                ->label('REST API port')
                ->numeric()
                ->required(),
            Select::make('mk_scheme')
                ->label('Protocol')
                ->options(['http' => 'HTTP (www service)', 'https' => 'HTTPS (www-ssl service)'])
                ->required(),
            Toggle::make('mk_verify_tls')
                ->label('Verify HTTPS certificate')
                ->helperText('Turn off only for a self-signed router certificate.'),
            TextInput::make('mk_user')
                ->label('Username')
                ->required(),
            TextInput::make('mk_pass')
                ->label('Password')
                ->password()
                ->revealable(),
            TextInput::make('mk_interface')
                ->label('WAN interface (optional)')
                ->helperText('Empty: rules match traffic addressed to the router itself (dst-address-type=local).'),
            TextInput::make('mk_default_target')
                ->label('Target IP for 0.0.0.0 allocations (optional)')
                ->helperText('Empty: allocations on 0.0.0.0 are skipped.'),
            TextInput::make('mk_forbidden_ports')
                ->label('Forbidden ports (comma separated)')
                ->placeholder('22, 80, 443, 3306'),
            Toggle::make('mk_protect_services')
                ->label('Never forward ports of enabled router services'),
            Select::make('mk_interval')
                ->label('Sync interval')
                ->options([
                    'everyMinute' => 'Every minute',
                    'everyFiveMinutes' => 'Every 5 minutes',
                    'everyTenMinutes' => 'Every 10 minutes',
                    'hourly' => 'Hourly',
                ])
                ->required(),
        ];
    }

    /**
     * @param  array<mixed, mixed>  $data
     */
    public function saveSettings(array $data): void
    {
        $interval = $data['mk_interval'] ?? 'everyFiveMinutes';
        if (!in_array($interval, MikroTikNATSyncPluginProvider::INTERVALS, true)) {
            $interval = 'everyFiveMinutes';
        }

        $this->writeToEnvironment([
            'MIKROTIK_NAT_SYNC_IP' => trim((string) ($data['mk_ip'] ?? '')),
            'MIKROTIK_NAT_SYNC_PORT' => trim((string) ($data['mk_port'] ?? '9080')),
            'MIKROTIK_NAT_SYNC_SCHEME' => ($data['mk_scheme'] ?? 'http') === 'https' ? 'https' : 'http',
            'MIKROTIK_NAT_SYNC_VERIFY_TLS' => !empty($data['mk_verify_tls']) ? 'true' : 'false',
            'MIKROTIK_NAT_SYNC_USER' => (string) ($data['mk_user'] ?? ''),
            'MIKROTIK_NAT_SYNC_PASSWORD' => (string) ($data['mk_pass'] ?? ''),
            'MIKROTIK_NAT_SYNC_INTERFACE' => trim((string) ($data['mk_interface'] ?? '')),
            'MIKROTIK_NAT_SYNC_DEFAULT_TARGET' => trim((string) ($data['mk_default_target'] ?? '')),
            'MIKROTIK_NAT_SYNC_FORBIDDEN_PORTS' => (string) ($data['mk_forbidden_ports'] ?? ''),
            'MIKROTIK_NAT_SYNC_PROTECT_SERVICES' => !empty($data['mk_protect_services']) ? 'true' : 'false',
            'MIKROTIK_NAT_SYNC_INTERVAL' => $interval,
        ]);

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
