<?php

namespace App\DTOs;

use App\Models\Device;

interface PetkitDTOInterface
{

    public function toPetkitConfiguration(): mixed;

}
