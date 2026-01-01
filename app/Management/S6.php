<?php

namespace App\Management;


use Illuminate\Support\Collection;

class S6
{
    private string $s6CommandPath = '/command';
    private string $serviceBasePath = '/run/service';

    /**
     * Start a service
     */
    public function start(string $serviceName): bool
    {
        $result = $this->executeCommand("s6-rc -u change {$serviceName}");
        return $result['exit_code'] === 0;
    }

    /**
     * Stop a service
     */
    public function stop(string $serviceName): bool
    {
        $result = $this->executeCommand("s6-rc -d change {$serviceName}");
        return $result['exit_code'] === 0;
    }

    /**
     * Restart a service
     */
    public function restart(string $serviceName): bool
    {
        $result = $this->executeCommand("s6-svc -r {$this->serviceBasePath}/{$serviceName}");
        return $result['exit_code'] === 0;
    }

    /**
     * Kill a service (force stop)
     */
    public function kill(string $serviceName): bool
    {
        $result = $this->executeCommand("s6-svc -k {$this->serviceBasePath}/{$serviceName}");
        return $result['exit_code'] === 0;
    }

    /**
     * Send SIGHUP to service
     */
    public function reload(string $serviceName): bool
    {
        $result = $this->executeCommand("s6-svc -1 {$this->serviceBasePath}/{$serviceName}");
        return $result['exit_code'] === 0;
    }

    /**
     * Get service status
     */
    public function status(string $serviceName): array
    {
        $result = $this->executeCommand("s6-svstat {$this->serviceBasePath}/{$serviceName}");

        if ($result['exit_code'] !== 0) {
            return [
                'running' => false,
                'error' => $result['output'],
            ];
        }

        $output = $result['output'];
        $isRunning = str_starts_with($output, 'up');

        // Parse output like: "up (pid 123) 456 seconds"
        preg_match('/up \(pid (\d+)\) (\d+) seconds/', $output, $matches);

        return [
            'running' => $isRunning,
            'pid' => $matches[1] ?? null,
            'uptime_seconds' => $matches[2] ?? null,
            'raw_output' => $output,
        ];
    }

    /**
     * Check if service is running
     */
    public function isRunning(string $serviceName): bool
    {
        $status = $this->status($serviceName);
        return $status['running'] ?? false;
    }

    /**
     * List all services
     */
    public function allServices(): Collection
    {
        $result = $this->executeCommand("s6-rc -a list");

        if ($result['exit_code'] !== 0) {
            return [];
        }

        return collect(array_filter(explode("\n", trim($result['output']))));
    }

    /**
     * List all running services
     */
    public function listRunningServices(): array
    {
        $services = $this->allServices();
        $running = [];

        foreach ($services as $service) {
            if ($this->isRunning($service)) {
                $running[] = $service;
            }
        }

        return $running;
    }

    /**
     * Get service logs (if available)
     */
    public function getLogs(string $serviceName, int $lines = 50): string
    {
        $logPath = "{$this->serviceBasePath}/{$serviceName}/log/current";

        if (!file_exists($logPath)) {
            return "Log file not found";
        }

        $result = $this->executeCommand("tail -n {$lines} {$logPath}");
        return $result['output'];
    }

    /**
     * Execute s6 command
     */
    private function executeCommand(string $command): array
    {
        // Add full path to s6 commands if needed
        if (!str_starts_with($command, '/')) {
            $command = "{$this->s6CommandPath}/{$command}";
        }

        $output = [];
        $exitCode = 0;

        exec($command . ' 2>&1', $output, $exitCode);

        return [
            'output' => implode("\n", $output),
            'exit_code' => $exitCode,
        ];
    }

    /**
     * Set custom s6 command path
     */
    public function setCommandPath(string $path): self
    {
        $this->s6CommandPath = rtrim($path, '/');
        return $this;
    }

    /**
     * Set custom service base path
     */
    public function setServiceBasePath(string $path): self
    {
        $this->serviceBasePath = rtrim($path, '/');
        return $this;
    }
}
