<?php

namespace App\DTOs;

use App\Models\BluetoothDevice;
use App\Models\Device;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

abstract class DeviceConfigurationDTO extends ValidatedDTO implements PetkitDTOInterface
{
    protected function rules(): array
    {
        return [];
    }

    protected function defaults(): array
    {
        return [];
    }

    protected function casts(): array
    {
        return [];
    }

    public function toPetkitConfiguration(): array {
        return [];
    }

    public static function fromDevice(Device|BluetoothDevice $device): PetkitDTOInterface
    {
        // TODO: Implement fromDevice() method.
    }
}
