<?php

namespace Avalon\MikroTikNATSync;

use Filament\Contracts\Plugin as FilamentPlugin;
use Filament\Panel;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use App\Contracts\Plugins\HasPluginSettings;
use App\Traits\EnvironmentWriterTrait;
use Illuminate\Console\Scheduling\Schedule;

class MikroTikNATSyncPlugin implements FilamentPlugin, HasPluginSettings
{
    use EnvironmentWriterTrait;

    /**
     * Панель викликає register() окремо для кожної панелі (admin, app, server),
     * тому без цього прапорця завдання потрапляє в розклад тричі.
     */
    private static bool $scheduleRegistered = false;

    /**
     * Команда реєструється один раз на життя процесу, з тієї ж причини.
     */
    private static bool $commandsRegistered = false;

    public function getId(): string
    {
        return 'mikrotik-nat-sync';
    }

    public function register(Panel $panel): void
    {
        if (self::$scheduleRegistered) {
            return;
        }

        self::$scheduleRegistered = true;

        // Реєструємо розклад під час завантаження додатку
        app()->booted(function () {
            if (!app()->runningInConsole()) {
                return;
            }

            $interval = env('MIKROTIK_NAT_SYNC_INTERVAL', 'everyFiveMinutes');

            // Захист від опечатки в .env — інакше буде BadMethodCallException
            $allowed = ['everyMinute', 'everyFiveMinutes', 'everyTenMinutes', 'hourly'];

            if (!in_array($interval, $allowed, true)) {
                $interval = 'everyFiveMinutes';
            }

            app(Schedule::class)
                ->command('mikrotik:sync')
                ->{$interval}()
                ->withoutOverlapping();
        });
    }

    public function boot(Panel $panel): void
    {
        if (self::$commandsRegistered || !app()->runningInConsole()) {
            return;
        }

        self::$commandsRegistered = true;

        // Реєструємо консольну команду
        $this->commands([
            \Avalon\MikroTikNATSync\Console\Commands\SyncMikrotikCommand::class,
        ]);
    }

    /**
     * Поточні значення, якими панель заповнює форму налаштувань.
     * Ключі мають збігатися з іменами полів у getSettingsForm().
     *
     * @return array<string, mixed>
     */
    public function getSettingsFormData(): array
    {
        return [
            'mk_ip' => env('MIKROTIK_NAT_SYNC_IP'),
            'mk_port' => env('MIKROTIK_NAT_SYNC_PORT', '9080'),
            'mk_user' => env('MIKROTIK_NAT_SYNC_USER'),
            'mk_pass' => env('MIKROTIK_NAT_SYNC_PASSWORD'),
            'mk_interface' => env('MIKROTIK_NAT_SYNC_INTERFACE'),
            'mk_forbidden_ports' => env('MIKROTIK_NAT_SYNC_FORBIDDEN_PORTS'),
            'mk_interval' => env('MIKROTIK_NAT_SYNC_INTERVAL', 'everyFiveMinutes'),
        ];
    }

    /**
     * @return \Filament\Schemas\Components\Component[]
     */
    public function getSettingsForm(): array
    {
        return [
            TextInput::make('mk_ip')
                ->label('MikroTik IP')
                ->required(),
            TextInput::make('mk_port')
                ->label('REST API Port')
                ->numeric()
                ->required(),
            TextInput::make('mk_user')
                ->label('Username')
                ->required(),
            TextInput::make('mk_pass')
                ->label('Password')
                ->password()
                ->revealable(),
            TextInput::make('mk_interface')
                ->label('WAN Interface (Optional)')
                ->placeholder('Залиште порожнім для всіх інтерфейсів'),
            TextInput::make('mk_forbidden_ports')
                ->label('Forbidden Ports (comma separated)')
                ->placeholder('22, 80, 443, 3306'),
            Select::make('mk_interval')
                ->label('Sync Interval')
                ->options([
                    'everyMinute' => 'Every Minute',
                    'everyFiveMinutes' => 'Every 5 Minutes',
                    'everyTenMinutes' => 'Every 10 Minutes',
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
        $this->writeToEnvironment([
            'MIKROTIK_NAT_SYNC_IP' => $data['mk_ip'] ?? '',
            'MIKROTIK_NAT_SYNC_PORT' => $data['mk_port'] ?? '9080',
            'MIKROTIK_NAT_SYNC_USER' => $data['mk_user'] ?? '',
            'MIKROTIK_NAT_SYNC_PASSWORD' => $data['mk_pass'] ?? '',
            'MIKROTIK_NAT_SYNC_INTERFACE' => $data['mk_interface'] ?? '',
            'MIKROTIK_NAT_SYNC_INTERVAL' => $data['mk_interval'] ?? 'everyFiveMinutes',
            'MIKROTIK_NAT_SYNC_FORBIDDEN_PORTS' => $data['mk_forbidden_ports'] ?? '',
        ]);

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }
}
