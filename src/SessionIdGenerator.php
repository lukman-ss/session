<?php

declare(strict_types=1);

namespace Lukman\Session;

class SessionIdGenerator
{
    public function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function isValid(string $id): bool
    {
        if ($id === '') {
            return false;
        }

        return preg_match('/^[a-zA-Z0-9_-]{40,}$/', $id) === 1;
    }
}
