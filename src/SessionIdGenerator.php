<?php

declare(strict_types=1);

namespace Lukman\Session;

class SessionIdGenerator
{
    private const MIN_LENGTH = 40;

    public function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function isValid(string $id): bool
    {
        if ($id === '') {
            return false;
        }

        return preg_match('/\A[A-Za-z0-9_-]{' . self::MIN_LENGTH . ',}\z/', $id) === 1;
    }
}
