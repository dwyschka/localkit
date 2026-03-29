<?php

namespace App\DTOs;

use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class SandFullWeightDTO extends ValidatedDTO implements PetkitDTOInterface
{

    public int $unknown1;
    public int $unknown2;

    public int $unknown3;
    public int $unknown4;
    public int $unknown5;
    protected function rules(): array
    {
        return [
            'unknown1' => 'integer',
            'unknown2' => 'integer',
            'unknown3' => 'integer',
            'unknown4' => 'integer',
            'unknown5' => 'integer',

        ];
    }

    protected function defaults(): array
    {
        return [
            'unknown1' => 3200,
            'unknown2' => 5800,
            'unknown3' => 3000,
            'unknown4' => 3200,
            'unknown5' => 3200,

        ];
    }

    protected function casts(): array
    {
        return [
            'unknown1' => new IntegerCast(),
            'unknown2' => new IntegerCast(),
            'unknown3' => new IntegerCast(),
            'unknown4' => new IntegerCast(),
            'unknown5' => new IntegerCast(),
        ];
    }

    public function toPetkitConfiguration(): array {
        return [$this->unknown1, $this->unknown2, $this->unknown3, $this->unknown4, $this->unknown5];
    }


}
