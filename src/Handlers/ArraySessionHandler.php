<?php

declare(strict_types=1);

namespace Lukman\Session\Handlers;

use Lukman\Session\SessionHandlerInterface;

class ArraySessionHandler implements SessionHandlerInterface
{
    private array $sessions = [];

    public function read(string $id): array
    {
        if (!$this->exists($id)) {
            return [];
        }

        return $this->sessions[$id]['data'];
    }

    public function write(string $id, array $data, int $ttl): void
    {
        $this->sessions[$id] = [
            'data' => $data,
            'expires_at' => time() + $ttl,
        ];
    }

    public function destroy(string $id): void
    {
        unset($this->sessions[$id]);
    }

    public function gc(int $lifetime): int
    {
        $deleted = 0;

        foreach (array_keys($this->sessions) as $id) {
            if ($this->isExpired($id)) {
                unset($this->sessions[$id]);
                $deleted++;
            }
        }

        return $deleted;
    }

    public function exists(string $id): bool
    {
        if (!isset($this->sessions[$id])) {
            return false;
        }

        if ($this->isExpired($id)) {
            unset($this->sessions[$id]);

            return false;
        }

        return true;
    }

    private function isExpired(string $id): bool
    {
        return time() >= $this->sessions[$id]['expires_at'];
    }
}
