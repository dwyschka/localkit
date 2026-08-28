<?php

namespace App\Petkit\Exceptions;

use RuntimeException;

class TelnetCredentialsNotConfiguredException extends RuntimeException
{
    public function __construct()
    {
        // Keep the internal message generic and not containing secrets.
        parent::__construct('Telnet credentials not configured');
    }

    public function getPublicTitle(): string
    {
        return 'Telnet credentials not configured.';
    }

    public function getPublicBody(): string
    {
        return 'Set DEVICE_TELNET_USERNAME / DEVICE_TELNET_PASSWORD in .env';
    }
}
