<?php

namespace App\Management;

use App\Models\Device;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

class Go2RTC
{

    public function __construct(protected Supervisor $supervisor)
    {
    }

    public function enable(): void
    {
        Config::set('app.enable.go2rtc', true);
    }

    public function disable(): void
    {
        Config::set('app.enable.go2rtc', false);
    }

    public function createConfigYml(Collection $devices)
    {

        $go2rtcConfig = config('go2rtc.settings');
        $tserver = \config('go2rtc.petkit.tserverUrl');

        $go2rtcConfig['streams'] = $devices->mapWithKeys(function($device) use ($tserver) {
            if($device->configuration['states']['ipAddress'] === null) {
                return false;
            }
            return [$device->name => sprintf($tserver, $device->configuration['states']['ipAddress'])];
        })->toArray();


        Storage::disk('local')->put('go2rtc.yml', Yaml::dump($go2rtcConfig));
    }

    public function streamUrl(Device $device): string
    {

        return sprintf('http://%s:1984/stream.html?src=%s', Str::of(config('petkit.local_ip'))->replace(':80', ''), $device->name);
    }

}
