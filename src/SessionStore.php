<?php

declare(strict_types=1);

namespace Lukman\Session;

use Lukman\Session\Exception\SessionException;
use Lukman\Session\Exception\SessionNotStartedException;

class SessionStore
{
    private SessionHandlerInterface $handler;
    private SessionIdGenerator $idGenerator;
    private ?string $id;
    private int $ttl;
    private array $data = [];
    private bool $started = false;

    public function __construct(
        SessionHandlerInterface $handler,
        ?SessionIdGenerator $idGenerator = null,
        ?string $id = null,
        int $ttl = 7200
    ) {
        $this->handler = $handler;
        $this->idGenerator = $idGenerator ?? new SessionIdGenerator();
        $this->id = $id;
        $this->ttl = $ttl;
    }

    public function start(): void
    {
        if ($this->started) {
            return;
        }

        if ($this->id === null) {
            $this->id = $this->idGenerator->generate();
        }

        $this->data = $this->handler->read($this->id);

        $this->normalizeFlashMetadata();

        $this->started = true;
    }

    public function started(): bool
    {
        return $this->started;
    }

    public function id(): string
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }
        return $this->id;
    }

    public function all(): array
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }
        $result = $this->data;
        unset($result['_flash']);
        return $result;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }

        $parts = explode('.', $key);
        $current = $this->data;

        foreach ($parts as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return $default;
            }
            $current = $current[$part];
        }

        return $current;
    }

    public function put(string $key, mixed $value): void
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }

        $parts = explode('.', $key);
        $current = &$this->data;

        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $current[$part] = $value;
            } else {
                if (!isset($current[$part]) || !is_array($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }
        }
    }

    public function has(string $key): bool
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }

        $parts = explode('.', $key);
        $current = $this->data;

        foreach ($parts as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return false;
            }
            $current = $current[$part];
        }

        return true;
    }

    public function missing(string $key): bool
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }
        return !$this->has($key);
    }

    public function forget(string $key): void
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }

        $parts = explode('.', $key);
        $current = &$this->data;

        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                unset($current[$part]);
                return;
            }
            if (!isset($current[$part]) || !is_array($current[$part])) {
                return;
            }
            $current = &$current[$part];
        }
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }

        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    public function replace(array $data): void
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }
        $this->data = $data;
        $this->normalizeFlashMetadata();
    }

    public function only(array $keys): array
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }

        $result = [];
        foreach ($keys as $key) {
            if ($this->has($key)) {
                $result[$key] = $this->get($key);
            }
        }

        return $result;
    }

    public function except(array $keys): array
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }

        $original = $this->data;
        foreach ($keys as $key) {
            $this->forget($key);
        }
        $result = $this->data;
        $this->data = $original;

        unset($result['_flash']);
        return $result;
    }

    public function increment(string $key, int|float $amount = 1): int|float
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }

        $value = $this->get($key, 0);

        if (!is_numeric($value)) {
            throw new SessionException("Cannot increment a non-numeric value.");
        }

        $newValue = $value + $amount;
        $this->put($key, $newValue);

        return $newValue;
    }

    public function decrement(string $key, int|float $amount = 1): int|float
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }

        $value = $this->get($key, 0);

        if (!is_numeric($value)) {
            throw new SessionException("Cannot decrement a non-numeric value.");
        }

        $newValue = $value - $amount;
        $this->put($key, $newValue);

        return $newValue;
    }

    public function flash(string $key, mixed $value): void
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }

        $this->put($key, $value);

        if (!in_array($key, $this->data['_flash']['new'], true)) {
            $this->data['_flash']['new'][] = $key;
        }

        $this->data['_flash']['old'] = array_values(array_filter(
            $this->data['_flash']['old'],
            fn($k) => $k !== $key
        ));
    }

    public function now(string $key, mixed $value): void
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }

        $this->put($key, $value);

        if (!in_array($key, $this->data['_flash']['old'], true)) {
            $this->data['_flash']['old'][] = $key;
        }

        $this->data['_flash']['new'] = array_values(array_filter(
            $this->data['_flash']['new'],
            fn($k) => $k !== $key
        ));
    }

    public function reflash(): void
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }

        $merged = array_merge($this->data['_flash']['new'], $this->data['_flash']['old']);
        $this->data['_flash']['new'] = array_values(array_unique($merged));
        $this->data['_flash']['old'] = [];
    }

    public function keep(string|array $keys): void
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }

        $keys = is_array($keys) ? $keys : [$keys];

        foreach ($keys as $key) {
            if (!in_array($key, $this->data['_flash']['new'], true)) {
                $this->data['_flash']['new'][] = $key;
            }

            $this->data['_flash']['old'] = array_values(array_filter(
                $this->data['_flash']['old'],
                fn($k) => $k !== $key
            ));
        }
    }

    public function ageFlashData(): void
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }

        foreach ($this->data['_flash']['old'] as $key) {
            $this->forget($key);
        }

        $this->data['_flash']['old'] = $this->data['_flash']['new'];
        $this->data['_flash']['new'] = [];
    }

    public function flashInput(array $input): void
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }

        $this->flash('_old_input', $input);
    }

    public function old(?string $key = null, mixed $default = null): mixed
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }

        $oldInput = $this->get('_old_input', []);

        if ($key === null) {
            return $oldInput;
        }

        $parts = explode('.', $key);
        $current = $oldInput;

        foreach ($parts as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return $default;
            }
            $current = $current[$part];
        }

        return $current;
    }

    public function regenerate(bool $destroyOld = false): void
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }

        $oldId = $this->id;
        $this->id = $this->idGenerator->generate();

        if ($destroyOld) {
            $this->handler->destroy($oldId);
        }
    }

    public function invalidate(): void
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }

        $this->flush();
        $this->handler->destroy($this->id);
        $this->id = $this->idGenerator->generate();
    }

    public function destroy(): void
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }

        $this->handler->destroy($this->id);
    }

    public function token(): string
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }

        if (!$this->has('_token')) {
            $this->regenerateToken();
        }

        return $this->get('_token');
    }

    public function regenerateToken(): string
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }

        $token = bin2hex(random_bytes(20));
        $this->put('_token', $token);

        return $token;
    }

    public function flush(): void
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }
        $this->data = [
            '_flash' => [
                'new' => [],
                'old' => [],
            ],
        ];
    }

    public function save(): void
    {
        if (!$this->started) {
            throw new SessionNotStartedException("Session is not started.");
        }
        $this->handler->write($this->id, $this->data, $this->ttl);
    }

    private function normalizeFlashMetadata(): void
    {
        if (!isset($this->data['_flash']) || !is_array($this->data['_flash'])) {
            $this->data['_flash'] = ['new' => [], 'old' => []];

            return;
        }

        foreach (['new', 'old'] as $key) {
            if (!isset($this->data['_flash'][$key]) || !is_array($this->data['_flash'][$key])) {
                $this->data['_flash'][$key] = [];

                continue;
            }

            $this->data['_flash'][$key] = array_values(array_unique(array_filter(
                $this->data['_flash'][$key],
                static fn($value): bool => is_string($value) && $value !== ''
            )));
        }
    }
}
