<?php

declare(strict_types=1);

namespace Lukman\Session\Tests;

use PHPUnit\Framework\TestCase;
use Lukman\Session\Handlers\FileSessionHandler;
use Lukman\Session\Exception\SessionException;

/**
 * @covers \Lukman\Session\Handlers\FileSessionHandler
 */
class FileSessionHandlerTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = __DIR__ . '/session_test_dir';
        $this->cleanUpDirectory($this->testDir);
    }

    protected function tearDown(): void
    {
        $this->cleanUpDirectory($this->testDir);
    }

    private function cleanUpDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            $files = glob($dir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
            @rmdir($dir);
        }
    }

    public function testDirectoryAutoCreated(): void
    {
        $targetDir = $this->testDir . '/nested/dir';
        if (is_dir($targetDir)) {
            @rmdir($targetDir);
        }

        new FileSessionHandler($targetDir);

        $this->assertDirectoryExists($targetDir);
        $this->cleanUpDirectory($targetDir);
        $this->cleanUpDirectory($this->testDir . '/nested');
    }

    public function testWriteAndReadSessionFile(): void
    {
        $handler = new FileSessionHandler($this->testDir);
        $id = 'sess123';
        $data = ['foo' => 'bar', 'counter' => 1];

        $handler->write($id, $data, 30);

        $this->assertTrue($handler->exists($id));
        $this->assertSame($data, $handler->read($id));
    }

    public function testDestroySessionFile(): void
    {
        $handler = new FileSessionHandler($this->testDir);
        $id = 'sessDelete';
        $data = ['test' => true];

        $handler->write($id, $data, 30);
        $this->assertTrue($handler->exists($id));

        $handler->destroy($id);

        $this->assertFalse($handler->exists($id));
        $this->assertSame([], $handler->read($id));
    }

    public function testReadMissingReturnsEmpty(): void
    {
        $handler = new FileSessionHandler($this->testDir);
        $this->assertSame([], $handler->read('missing-session-id'));
    }

    public function testExpiredSessionReturnsEmpty(): void
    {
        $handler = new FileSessionHandler($this->testDir);
        $id = 'sessExpired';
        $data = ['expired' => true];

        $handler->write($id, $data, -5);

        $this->assertFalse($handler->exists($id));
        $this->assertSame([], $handler->read($id));
    }

    public function testGcRemovesExpiredFiles(): void
    {
        $handler = new FileSessionHandler($this->testDir);

        $handler->write('active', ['status' => 'active'], 100);
        $handler->write('expired1', ['status' => 'expired'], -10);
        $handler->write('expired2', ['status' => 'expired'], -5);

        $deletedCount = $handler->gc(60);

        $this->assertSame(2, $deletedCount);
        $this->assertTrue($handler->exists('active'));
        $this->assertFalse($handler->exists('expired1'));
        $this->assertFalse($handler->exists('expired2'));
    }

    public function testPathTraversalIdRejected(): void
    {
        $handler = new FileSessionHandler($this->testDir);

        $this->expectException(SessionException::class);
        $handler->read('../etc/passwd');
    }
}
