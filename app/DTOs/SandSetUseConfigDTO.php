<?php

namespace App\DTOs;

use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class SandSetUseConfigDTO extends ValidatedDTO implements PetkitDTOInterface
{

    public int $unknown1;
    public int $unknown2;
    public int $unknown3;

    protected function rules(): array
    {
        return [
            'unknown1' => 'integer',
            'unknown2' => 'integer',
            'unknown3' => 'integer'
        ];
    }

    protected function defaults(): array
    {
        return [
            'unknown1' => 40,
            'unknown2' => 60,
            'unknown3' => 85
        ];
    }

    protected function casts(): array
    {
        return [
            'unknown1' => new IntegerCast(),
            'unknown2' => new IntegerCast(),
            'unknown3' => new IntegerCast()
        ];
    }

    public function toPetkitConfiguration(): array {
        return [$this->unknown1, $this->unknown2, $this->unknown3];
    }


}
