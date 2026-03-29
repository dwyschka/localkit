<?php

namespace App\DTOs;

use App\Models\Device;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class RangeDTO extends ValidatedDTO implements PetkitDTOInterface
{

    public int $from;
    public int $till;

    protected function rules(): array
    {
        return [
            'from' => 'integer|min:0|max:1440',
            'till' => 'integer|min:0|max:1440',
        ];
    }

    protected function defaults(): array
    {
        return [
            'from' => 0,
            'till' => 1440
        ];
    }

    protected function casts(): array
    {
        return [
            'from' => new IntegerCast(),
            'till' => new IntegerCast(),
        ];
    }

    public function toPetkitConfiguration(): array {
        return [$this->from, $this->till];
    }

}
