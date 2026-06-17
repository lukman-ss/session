<?php

declare(strict_types=1);

namespace Lukman\Session\Tests;

use PHPUnit\Framework\TestCase;
use Lukman\Session\SessionManager;
use Lukman\Session\SessionStore;
use Lukman\Session\Handlers\ArraySessionHandler;
use Lukman\Session\Handlers\FileSessionHandler;
use Lukman\Session\Exception\SessionException;

/**
 * @covers \Lukman\Session\SessionManager
 */
class SessionManagerTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = __DIR__ . '/manager_session_test_dir';
        if (is_dir($this->testDir)) {
            @rmdir($this->testDir);
        }
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->testDir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->testDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
                continue;
            }

            @unlink($item->getPathname());
        }

        @rmdir($this->testDir);
    }

    public function testDefaultDriver(): void
    {
        $manager = new SessionManager();
        $this->assertSame('file', $manager->getDefaultDriver());
    }

    public function testArrayDriver(): void
    {
        $manager = new SessionManager([
            'driver' => 'array',
        ]);
        $this->assertSame('array', $manager->getDefaultDriver());

        $store = $manager->store();
        $this->assertInstanceOf(SessionStore::class, $store);
        $this->assertInstanceOf(ArraySessionHandler::class, $manager->handler());
    }

    public function testFileDriver(): void
    {
        $manager = new SessionManager([
            'driver' => 'file',
            'files' => $this->testDir,
        ]);

        $store = $manager->store();
        $this->assertInstanceOf(SessionStore::class, $store);
        $this->assertInstanceOf(FileSessionHandler::class, $manager->handler());
    }

    public function testFileDriverUsesConfiguredPath(): void
    {
        $manager = new SessionManager([
            'driver' => 'file',
            'files' => $this->testDir,
        ]);

        $store = $manager->store();
        $store->start();
        $id = $store->id();
        $store->put('foo', 'bar');
        $store->save();

        $this->assertFileExists($this->testDir . DIRECTORY_SEPARATOR . 'sess_' . $id);
    }

    public function testStoreIsLazy(): void
    {
        $manager = new SessionManager([
            'driver' => 'file',
            'files' => $this->testDir,
        ]);

        $this->assertDirectoryDoesNotExist($this->testDir);

        $manager->store();

        $this->assertDirectoryExists($this->testDir);
    }

    public function testStoreCached(): void
    {
        $manager = new SessionManager([
            'driver' => 'array',
        ]);

        $store1 = $manager->store();
        $store2 = $manager->store();

        $this->assertSame($store1, $store2);
    }

    public function testSetDefaultDriver(): void
    {
        $manager = new SessionManager();
        $manager->setDefaultDriver('array');

        $this->assertSame('array', $manager->getDefaultDriver());
    }

    public function testUnknownDriverThrow(): void
    {
        $manager = new SessionManager();

        $this->expectException(SessionException::class);
        $manager->driver('redis');
    }

    public function testLifetimeConvertedToTtl(): void
    {
        $manager = new SessionManager([
            'driver' => 'array',
            'lifetime' => 60,
        ]);

        $store = $manager->store();
        $store->start();

        $store->put('foo', 'bar');
        $id = $store->id();
        $store->save();

        $handler = $manager->handler('array');

        $ref = new \ReflectionClass($handler);
        $prop = $ref->getProperty('sessions');
        $prop->setAccessible(true);
        $sessions = $prop->getValue($handler);

        $this->assertArrayHasKey($id, $sessions);
        $expiresAt = $sessions[$id]['expires_at'];

        $expectedExpiry = time() + 3600;
        $this->assertGreaterThanOrEqual($expectedExpiry - 2, $expiresAt);
        $this->assertLessThanOrEqual($expectedExpiry + 2, $expiresAt);
    }
}
