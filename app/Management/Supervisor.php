<?php

namespace App\Management;

class Supervisor
{

    private \Supervisor\Supervisor $supervisor;

    public function __construct()
    {
        $guzzleClient = new \GuzzleHttp\Client();

        $client = new \fXmlRpc\Client(
            'http://127.0.0.1:9001/RPC2',
            new \fXmlRpc\Transport\PsrTransport(
                new \GuzzleHttp\Psr7\HttpFactory(),
                $guzzleClient
            )
        );

        $this->supervisor = new \Supervisor\Supervisor($client);
    }

    public function start(string $serviceName): bool
    {
        $this->supervisor->startProcess($serviceName);
        return $this->running($serviceName);
    }

    public function stop(string $serviceName): bool {
        $this->supervisor->stopProcess($serviceName);
        return !$this->running($serviceName);
    }

    public function running($serviceName)
    {
        return $this->supervisor->getProcess($serviceName)->isRunning();
    }

    public function restart($serviceName): bool {
        $this->supervisor->stopProcess($serviceName);
        $this->supervisor->startProcess($serviceName);

        return $this->running($serviceName);
    }
}
