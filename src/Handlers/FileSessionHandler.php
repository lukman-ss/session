<?php

declare(strict_types=1);

namespace Lukman\Session\Handlers;

use Lukman\Session\SessionHandlerInterface;
use Lukman\Session\Exception\SessionException;

class FileSessionHandler implements SessionHandlerInterface
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = rtrim($path, '/\\');

        if (!is_dir($this->path)) {
            try {
                if (!@mkdir($this->path, 0777, true) && !is_dir($this->path)) {
                    throw new SessionException("Failed to create session directory: {$this->path}");
                }
            } catch (\Throwable $e) {
                throw new SessionException("Failed to create session directory: {$this->path}", 0, $e);
            }
        }

        if (!is_writable($this->path)) {
            throw new SessionException("Session directory is not writable: {$this->path}");
        }
    }

    public function read(string $id): array
    {
        try {
            $file = $this->getFilePath($id);
            if (!file_exists($file)) {
                return [];
            }

            $content = @file_get_contents($file);
            if ($content === false) {
                throw new SessionException("Failed to read session file: {$file}");
            }

            $payload = json_decode($content, true);
            if (!is_array($payload) || !isset($payload['expires_at']) || !isset($payload['data'])) {
                return [];
            }

            if (time() >= $payload['expires_at']) {
                return [];
            }

            return $payload['data'];
        } catch (SessionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new SessionException("Error reading session data: " . $e->getMessage(), 0, $e);
        }
    }

    public function write(string $id, array $data, int $ttl): void
    {
        try {
            $file = $this->getFilePath($id);
            $payload = json_encode([
                'expires_at' => time() + $ttl,
                'data' => $data,
            ]);

            if ($payload === false) {
                throw new SessionException("Failed to serialize session data to JSON.");
            }

            $tempFile = $this->path . DIRECTORY_SEPARATOR . 'sess_tmp_' . bin2hex(random_bytes(16));

            if (@file_put_contents($tempFile, $payload) === false) {
                throw new SessionException("Failed to write temporary session file.");
            }

            if (!@rename($tempFile, $file)) {
                @unlink($tempFile);
                throw new SessionException("Failed to make session write atomic via rename.");
            }

            @chmod($file, 0666);
        } catch (SessionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new SessionException("Error writing session data: " . $e->getMessage(), 0, $e);
        }
    }

    public function destroy(string $id): void
    {
        try {
            $file = $this->getFilePath($id);
            if (file_exists($file)) {
                if (!@unlink($file)) {
                    throw new SessionException("Failed to delete session file: {$file}");
                }
            }
        } catch (SessionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new SessionException("Error destroying session: " . $e->getMessage(), 0, $e);
        }
    }

    public function gc(int $lifetime): int
    {
        try {
            $files = glob($this->path . DIRECTORY_SEPARATOR . 'sess_*');
            if ($files === false) {
                return 0;
            }

            $deleted = 0;
            $now = time();

            foreach ($files as $file) {
                if (str_contains(basename($file), 'sess_tmp_')) {
                    continue;
                }

                if (is_file($file)) {
                    $content = @file_get_contents($file);
                    if ($content !== false) {
                        $payload = json_decode($content, true);
                        if (is_array($payload) && isset($payload['expires_at'])) {
                            if ($now >= $payload['expires_at']) {
                                if (@unlink($file)) {
                                    $deleted++;
                                } else {
                                    throw new SessionException("Failed to delete expired session file during GC: {$file}");
                                }
                            }
                        }
                    }
                }
            }

            return $deleted;
        } catch (SessionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new SessionException("Error during garbage collection: " . $e->getMessage(), 0, $e);
        }
    }

    public function exists(string $id): bool
    {
        try {
            $file = $this->getFilePath($id);
            if (!file_exists($file)) {
                return false;
            }

            $content = @file_get_contents($file);
            if ($content === false) {
                throw new SessionException("Failed to read session file for existence check: {$file}");
            }

            $payload = json_decode($content, true);
            if (!is_array($payload) || !isset($payload['expires_at'])) {
                return false;
            }

            return time() < $payload['expires_at'];
        } catch (SessionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new SessionException("Error checking session existence: " . $e->getMessage(), 0, $e);
        }
    }

    private function getFilePath(string $id): string
    {
        if (str_contains($id, '/') || str_contains($id, '\\') || str_contains($id, '..')) {
            throw new SessionException("Path traversal attempt detected in session ID.");
        }

        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
        if (empty($sanitized)) {
            throw new SessionException("Session ID is empty or invalid after sanitization.");
        }

        return $this->path . DIRECTORY_SEPARATOR . 'sess_' . $sanitized;
    }
}
