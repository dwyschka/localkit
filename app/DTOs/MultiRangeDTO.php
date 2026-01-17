<?php

namespace App\DTOs;

use Illuminate\Support\Collection;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\CollectionCast;
use WendellAdriel\ValidatedDTO\Casting\DTOCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class MultiRangeDTO extends ValidatedDTO implements PetkitDTOInterface
{

    public string $name;

    public array $ranges;

    protected function rules(): array
    {
        return [
            'name' => 'string',
            'ranges' => 'required|array',
            'ranges.*.from' => 'required|integer|min:0|max:1440',
            'ranges.*.till' => 'required|integer|min:0|max:1440',
        ];
    }

    protected function defaults(): array
    {
        return [
        ];
    }

    protected function casts(): array
    {
        return [
            'name' => new StringCast(),
            'ranges' => new ArrayCast()
        ];
    }

    public function toPetkitConfiguration(): string
    {
        if($this->name === 'disturbMultiRange') {
            //Petkit failed at naming.. -_-
            $this->name = 'distrubMultiRange';
        }

        return json_encode([
            $this->name => $this->ranges
        ]);
    }


}
