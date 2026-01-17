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

    /** @var RangeDTO[] */
    public array $ranges;

    protected function rules(): array
    {
        return [
            'name' => 'string',
            'ranges' => 'array',
        ];
    }

    protected function defaults(): array
    {
        return [
            'ranges' => [['from' => 0, 'till' => 1440]]
        ];
    }

    protected function casts(): array
    {
        return [
            'name' => new StringCast(),
            'ranges' => new ArrayCast(new DTOCast(RangeDTO::class))
        ];
    }

    public function toPetkitConfiguration(): string
    {
        return json_encode([
            $this->name => $this->ranges
        ]);
    }


}
