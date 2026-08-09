<?php

namespace App\Localkit;

use Exception;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OTA
{

    public function __construct()
    {
    }

    public function getAvailable(Device $device): ?array
    {
        $currentFirmware = $device->firmware;
        $available = $this->firmwareByDevice($device);
        if (is_null($available)) {
            return null;
        }

        return $available['version'] >= $currentFirmware ? $available : null;
    }

    public function isAvailable(Device $device): bool
    {
        return $this->getAvailable($device) !== null;
    }

    public function loadRepository(): Collection
    {
        $repository = Http::get(sprintf('%s/%s', config('localkit.ota_repository'), 'repository.json'));

        if ($repository->successful()) {
            return collect($repository->json());
        }
        Log::error('Failed to load OTA repository');
        return collect([]);
    }

    public function toOTA($device): array
    {
        $detail = $this->firmwareByDevice($device)['detail'];
        $detail = Http::get($detail);
        if ($detail->successful()) {
            $result = $detail->json('result');

            if (config('localkit.firmware_proxy')) {
                $upstreamHost = parse_url(config('localkit.ota_repository'), PHP_URL_HOST);
                $result = json_decode(
                    str_replace($upstreamHost, 'api.eu-pet.com', json_encode($result)),
                    true
                );
            }

            return (array) $result;
        }
        throw new Exception('No valid data');
    }

    private function firmwareByDevice(Device $device): array
    {
        $repository = $this->loadRepository();
        return $repository->filter(fn($item) => $item['device_type'] === $device->device_type)->first();
    }
}
