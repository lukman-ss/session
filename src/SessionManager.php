<?php

declare(strict_types=1);

namespace Lukman\Session;

use Lukman\Session\Handlers\ArraySessionHandler;
use Lukman\Session\Handlers\FileSessionHandler;
use Lukman\Session\Exception\SessionException;

class SessionManager
{
    private array $config;
    private array $stores = [];
    private array $handlers = [];
    private string $defaultDriver = 'file';

    public function __construct(array $config = [])
    {
        $this->config = $config;
        if (isset($config['driver'])) {
            $this->defaultDriver = $config['driver'];
        }
    }

    public function driver(?string $name = null): SessionStore
    {
        $name = $name ?? $this->getDefaultDriver();

        if (isset($this->stores[$name])) {
            return $this->stores[$name];
        }

        $handler = $this->handler($name);
        $lifetime = $this->config['lifetime'] ?? 120; // Default: 120 minutes
        $ttl = (int) $lifetime * 60;

        $this->stores[$name] = new SessionStore($handler, new SessionIdGenerator(), null, $ttl);

        return $this->stores[$name];
    }

    public function store(?string $name = null): SessionStore
    {
        return $this->driver($name);
    }

    public function handler(?string $driver = null): SessionHandlerInterface
    {
        $driver = $driver ?? $this->getDefaultDriver();

        if (isset($this->handlers[$driver])) {
            return $this->handlers[$driver];
        }

        if ($driver === 'array') {
            $this->handlers[$driver] = new ArraySessionHandler();
        } elseif ($driver === 'file') {
            $path = $this->config['files'] ?? sys_get_temp_dir();
            $this->handlers[$driver] = new FileSessionHandler($path);
        } else {
            throw new SessionException("Driver [{$driver}] is not supported.");
        }

        return $this->handlers[$driver];
    }

    public function setDefaultDriver(string $driver): void
    {
        $this->defaultDriver = $driver;
    }

    public function getDefaultDriver(): string
    {
        return $this->defaultDriver;
    }

    public function config(): array
    {
        return $this->config;
    }
}
