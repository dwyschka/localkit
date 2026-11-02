<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Concerns\InteractsWithProvisioning;
use App\Filament\Resources\DeviceResource;
use App\Models\Device;
use App\Petkit\TelnetClient;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Throwable;

class ListDevices extends ListRecords
{
    use InteractsWithProvisioning;

    protected static string $resource = DeviceResource::class;

    public ?string $telnetConsoleLog = null;

    public function mount(): void
    {
        parent::mount();

        $this->mountProvisioningDefaults();
    }

    public function getFooter(): ?View
    {
        return view('filament.partials.console_modal');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('configure_rtsp')
                ->label('Configure RTSP (Telnet)')
                ->icon('heroicon-o-video-camera')
                ->color('gray')
                ->modalHeading('Configure Camera RTSP Server & Authentication')
                ->modalDescription('Updates raptor.conf on the selected camera device over Telnet and restarts the RTSP streamer (raptor.sh). Leave credentials blank to allow unauthenticated access.')
                ->form([
                    Select::make('device_id')
                        ->label('Camera Device')
                        ->options(fn () => Device::all()->filter(fn ($d) => (bool) $d->isNextGen())->pluck('name', 'id'))
                        ->default(fn () => Device::all()->first(fn ($d) => (bool) $d->isNextGen())?->id)
                        ->required()
                        ->selectablePlaceholder(false),
                    TextInput::make('rtsp_username')
                        ->label('RTSP Username')
                        ->placeholder('myuser')
                        ->maxLength(64)
                        ->regex('/^[A-Za-z0-9_-]*$/')
                        ->helperText('Alphanumeric username (e.g. myuser). Leave empty to disable authentication.'),
                    TextInput::make('rtsp_password')
                        ->label('RTSP Password')
                        ->password()
                        ->revealable()
                        ->placeholder('Password')
                        ->maxLength(64)
                        ->regex('/^[A-Za-z0-9_.-]*$/')
                        ->helperText('Maximum 64 characters. Avoid #, ;, quotes, spaces, or colons.'),
                    Select::make('timestamps')
                        ->label('Timestamp Mode')
                        ->options([
                            'none' => 'none (default: firmware PTS ticks - causes 0.5x slow-motion in night vision)',
                            'arrival' => 'arrival (recommended: dynamic arrival timestamps - fixes 0.5x slow-motion)',
                            'capture' => 'capture (hardware capture clock)',
                        ])
                        ->default('arrival')
                        ->selectablePlaceholder(false)
                        ->required(),
                    TextInput::make('fps')
                        ->label('FPS Mode')
                        ->default('0')
                        ->numeric()
                        ->helperText('0 = dynamic FPS matching sensor exposure, >0 = fixed FPS.'),
                ])
                ->action(function (array $data) {
                    $device = Device::find($data['device_id'] ?? null);

                    if (! $device) {
                        Notification::make()
                            ->danger()
                            ->title('Device not found')
                            ->send();

                        return;
                    }

                    $ipAddress = $device->configuration['states']['ipAddress'] ?? null;

                    if (empty($ipAddress)) {
                        Notification::make()
                            ->danger()
                            ->title('No IP address known for this device')
                            ->send();

                        return;
                    }

                    $username = config('petkit.telnet_username');
                    $password = config('petkit.telnet_password');

                    if (empty($username) || empty($password)) {
                        Notification::make()
                            ->danger()
                            ->title('Telnet credentials not configured')
                            ->body('Set DEVICE_TELNET_USERNAME / DEVICE_TELNET_PASSWORD in .env')
                            ->send();

                        return;
                    }

                    $logLines = [];
                    $logLines[] = "[Telnet] Connecting to {$ipAddress}:23 as {$username}...";

                    try {
                        $telnet = new TelnetClient($ipAddress, 23, 10);
                        $telnet->login($username, $password);
                        $logLines[] = "[Telnet] Authentication successful.";

                        // Detect LocalKit raptor directory on device (supports Gen 1 /system and Gen 2 /opt)
                        $pathsCheck = $telnet->exec('if [ -d /opt/localkit/files ]; then echo /opt/localkit/files; elif [ -d /system/localkit/files ]; then echo /system/localkit/files; else echo /system/etc; fi');
                        $lines = array_filter(array_map('trim', explode("\n", $pathsCheck)));
                        $dir = '/system/localkit/files';
                        foreach ($lines as $line) {
                            if (str_starts_with($line, '/')) {
                                $dir = $line;
                                break;
                            }
                        }

                        $conf = "{$dir}/raptor.conf";
                        $sh = "{$dir}/raptor.sh";
                        $logLines[] = "[Config] Located streamer files in: {$dir}";

                        // Backup raptor.conf if not already backed up
                        $telnet->exec("[ ! -f {$conf}.bak ] && cp {$conf} {$conf}.bak");
                        $logLines[] = "[Backup] Configuration verified/backed up to {$conf}.bak.";

                        // Clean out any existing username / password / legacy auth directives
                        $telnet->exec("sed -i '/^[[:space:]]*username[[:space:]]*=/d; /^[[:space:]]*password[[:space:]]*=/d; /^\\[auth\\]/d' {$conf}");

                        $rtspUser = trim($data['rtsp_username'] ?? '');
                        $rtspPass = trim($data['rtsp_password'] ?? '');

                        if (! empty($rtspUser) && ! empty($rtspPass)) {
                            $telnet->exec(sprintf(
                                "printf '\\nusername = %s\\npassword = %s\\n' >> %s",
                                $rtspUser,
                                $rtspPass,
                                $conf
                            ));
                            $logLines[] = "[Config] Added RTSP Digest credentials: username={$rtspUser}, password=********";
                        } else {
                            $logLines[] = "[Config] No RTSP credentials provided. Authentication disabled.";
                        }

                        $timestamps = $data['timestamps'] ?? 'arrival';
                        $fps = $data['fps'] ?? '0';
                        $telnet->exec("sed -i '/^[[:space:]]*timestamps[[:space:]]*=/d; /^[[:space:]]*fps[[:space:]]*=/d' {$conf}");
                        if ($timestamps !== 'none') {
                            $telnet->exec(sprintf("sed -i '/\\[petkit\\]/a timestamps = %s' %s", $timestamps, $conf));
                            $telnet->exec(sprintf("sed -i '/\\[petkit\\]/a fps = %s' %s", $fps, $conf));
                            $logLines[] = "[Config] Set timestamps={$timestamps}, fps={$fps}";
                        } else {
                            $logLines[] = "[Config] Kept default firmware timestamps";
                        }

                        $restartOutput = $telnet->exec("{$sh} restart");
                        $logLines[] = "[Daemon] Streamer restarted: " . trim(str_replace(["\r\n", "\r", "\n"], ' ', $restartOutput));
                        $telnet->close();
                        $logLines[] = "[Telnet] Connection closed.";

                        $config = $device->configuration;
                        $config['settings']['rtsp_username'] = $rtspUser;
                        $config['settings']['rtsp_password'] = $rtspPass;
                        $device->configuration = $config;
                        $device->saveQuietly();
                        $logLines[] = "[Database] Device configuration updated in LocalKit.";

                        $this->telnetConsoleLog = implode("\n", $logLines);
                        $this->dispatch('open-modal', id: 'rtsp-console-modal');

                        Notification::make()
                            ->success()
                            ->title('RTSP Configuration Applied')
                            ->send();
                    } catch (Throwable $e) {
                        Log::warning('Configuring RTSP via Telnet failed', [
                            'device_id' => $device->id,
                            'ip' => $ipAddress,
                            'error' => $e->getMessage(),
                        ]);

                        $logLines[] = "[ERROR] " . $e->getMessage();
                        $this->telnetConsoleLog = implode("\n", $logLines);
                        $this->dispatch('open-modal', id: 'rtsp-console-modal');

                        Notification::make()
                            ->danger()
                            ->title('Failed to configure RTSP')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            Action::make('provision')
                ->label('Provision Device')
                ->icon('heroicon-o-signal')
                ->color('primary')
                ->modalHeading('Set a device up over Bluetooth')
                ->modalDescription(
                    "Hands a device its WiFi credentials and this server's address directly, so it "
                    . "never contacts PetKit's cloud. No PetKit app and no DNS redirect involved."
                )
                ->modalWidth(Width::ThreeExtraLarge)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(fn (): View => view(
                    'filament.partials.provisioning',
                    [
                        ...$this->provisionWifiDefaults(),
                        'server' => $this->provisionServer,
                        'timezone' => $this->provisionTimezone,
                        'zone' => $this->provisionZone,
                        'timezoneOptions' => $this->provisionTimezoneOptions(),
                        'installSteps' => $this->provisioningInstallSteps(),
                    ],
                )),
        ];
    }
}
