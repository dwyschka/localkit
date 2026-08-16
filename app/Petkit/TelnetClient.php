<?php

namespace App\Petkit;

use RuntimeException;

/**
 * Minimal telnet client for the NextGen devices' busybox-style telnetd
 * (login/password prompt, then a plain shell) - no composer package for
 * this exists in the project, and the protocol needed here is small enough
 * not to warrant one: connect, answer IAC option negotiation with a
 * blanket refusal (these devices don't seem to require anything fancier
 * than a plain-text session), match the login/password/shell prompts, run
 * one command.
 */
class TelnetClient
{
    /** @var resource|null */
    private $socket;

    public function __construct(
        private readonly string $host,
        private readonly int $port = 23,
        private readonly float $timeout = 5.0,
    ) {
    }

    public function login(string $username, string $password): void
    {
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

        if ($socket === false) {
            throw new RuntimeException(sprintf('Telnet connect to %s:%d failed: %s', $this->host, $this->port, $errstr));
        }

        $this->socket = $socket;
        stream_set_blocking($this->socket, false);

        $this->readUntil(['login:', 'Login:']);
        $this->write($username);
        $this->readUntil(['Password:', 'password:']);
        $this->write($password);
        $this->readUntil(['#', '$']);
    }

    public function exec(string $command): string
    {
        $this->write($command);

        return $this->readUntil(['#', '$']);
    }

    public function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }

        $this->socket = null;
    }

    private function write(string $line): void
    {
        fwrite($this->socket, $line . "\r\n");
    }

    private function readUntil(array $markers): string
    {
        $buffer = '';
        $deadline = microtime(true) + $this->timeout;

        while (microtime(true) < $deadline) {
            $chunk = fread($this->socket, 2048);

            if ($chunk === false || $chunk === '') {
                usleep(50_000);
                continue;
            }

            $buffer .= $this->stripTelnetControl($chunk);

            foreach ($markers as $marker) {
                if (str_contains($buffer, $marker)) {
                    return $buffer;
                }
            }
        }

        throw new RuntimeException(sprintf('Telnet read timed out waiting for one of: %s (got: %s)', implode('/', $markers), $buffer));
    }

    /**
     * Strips IAC (0xFF) option-negotiation sequences out of the stream and
     * answers every WILL/WONT/DO/DONT with a flat refusal (DONT/WONT) -
     * enough to keep a negotiating telnetd from hanging the session,
     * without implementing full RFC 854 option state.
     */
    private function stripTelnetControl(string $chunk): string
    {
        $clean = '';
        $length = strlen($chunk);

        for ($i = 0; $i < $length; $i++) {
            $byte = ord($chunk[$i]);

            if ($byte !== 0xFF) {
                $clean .= $chunk[$i];
                continue;
            }

            $command = ord($chunk[$i + 1] ?? "\0");

            $reply = match ($command) {
                251, 252 => 254, // WILL, WONT -> DONT
                253, 254 => 252, // DO, DONT -> WONT
                default => null,
            };

            if ($reply === null) {
                $i += 1;
                continue;
            }

            $option = ord($chunk[$i + 2] ?? "\0");
            fwrite($this->socket, chr(0xFF) . chr($reply) . chr($option));
            $i += 2;
        }

        return $clean;
    }
}
