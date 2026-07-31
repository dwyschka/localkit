<?php

namespace App\Petkit\Interfaces;

interface HasCamera
{
    // Petkit's own numeric device type code, e.g. used in cloud storage
    // capability payloads.
    public function deviceCode(): int;
}
