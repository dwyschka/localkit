<?php

namespace App\DTOs;

use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class UserHomeModeDTO extends ValidatedDTO implements PetkitDTOInterface
{
    public int $homeMode;

    public int $mic;

    public int $camera;

    public int $ptz;

    protected function rules(): array
    {
        return [
            'homeMode' => ['integer', 'min:-1'],
            'mic' => ['integer', 'min:-1'],
            'camera' => ['integer', 'min:-1'],
            'ptz' => ['integer', 'min:-1'],
        ];
    }

    protected function defaults(): array
    {
        return [
            'homeMode' => 0,
            'mic' => -1,
            'camera' => -1,
            'ptz' => -1,
        ];
    }

    protected function casts(): array
    {
        return [
            'homeMode' => new IntegerCast(),
            'mic' => new IntegerCast(),
            'camera' => new IntegerCast(),
            'ptz' => new IntegerCast(),
        ];
    }

    public function toPetkitConfiguration(): array
    {
        return [
            'homeMode' => $this->homeMode,
            'mic' => $this->mic,
            'camera' => $this->camera,
            'ptz' => $this->ptz,
        ];
    }
}
