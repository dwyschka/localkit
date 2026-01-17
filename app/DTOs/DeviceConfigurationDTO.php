<?php

namespace App\DTOs;

use WendellAdriel\ValidatedDTO\ValidatedDTO;

class DeviceConfigurationDTO extends ValidatedDTO implements PetkitDTOInterface
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
}
