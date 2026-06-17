<?php

declare(strict_types=1);

namespace Lukman\Session;

interface SessionHandlerInterface
{
    public function read(string $id): array;

    public function write(string $id, array $data, int $ttl): void;

    public function destroy(string $id): void;

    public function gc(int $lifetime): int;

    public function exists(string $id): bool;
}
