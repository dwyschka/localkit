<?php

namespace App\Petkit;

class OutputSanitizer
{
    /**
     * Remove any configured telnet password occurrences from the provided text.
     */
    public static function sanitize(string $text): string
    {
        $pw = config('petkit.telnet_password');

        if (empty($pw)) {
            return $text;
        }

        // Replace any occurrences of the configured password with a redaction token.
        return str_replace($pw, '[REDACTED]', $text);
    }
}
