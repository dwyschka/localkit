<?php

namespace App\Homeassistant\Interfaces;


interface Snapshot {

    public function toSnapshot(): ?string;
}
