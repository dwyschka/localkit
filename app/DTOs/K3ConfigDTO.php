<?php

namespace App\DTOs;

use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class K3ConfigDTO extends ValidatedDTO implements PetkitDTOInterface
{

    public array $standard;
    public int $lightness;
    public int $lowVoltage;
    public int $refreshTotalTime;
    public int $singleRefreshTime;
    public int $singleLightTime;

    protected function rules(): array
    {
        return [
            'standard' => 'array',
            'lightness' => 'int',
            'lowVoltage' => 'int',
            'refreshTotalTime' => 'int',
            'singleRefreshTime' => 'int',
            'singleLightTime' => 'int',
        ];
    }

    protected function defaults(): array
    {
        return [
            'standard' => [5, 30],
            'lightness' => 100,
            'lowVoltage' => 5,
            'refreshTotalTime' => 11500,
            'singleRefreshTime' => 25,
            'singleLightTime' => 120
        ];
    }

    protected function casts(): array
    {
        return [
            'standard' => new ArrayCast(),
        ];
    }

    public function toPetkitConfiguration(): array
    {
        return [
            'config' => [
                'standard' => $this->standard,
                'lightness' => $this->lightness,
                'lowVoltage' => $this->lowVoltage,
                'refreshTotalTime' => $this->refreshTotalTime,
                'singleRefreshTime' => $this->singleRefreshTime,
                'singleLightTime' => $this->singleLightTime
            ]
        ];
    }


}
