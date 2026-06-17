<?php

declare(strict_types=1);

namespace Lukman\Session\Tests;

use PHPUnit\Framework\TestCase;
use Lukman\Session\Handlers\ArraySessionHandler;

/**
 * @covers \Lukman\Session\Handlers\ArraySessionHandler
 */
class ArraySessionHandlerTest extends TestCase
{
    private ArraySessionHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new ArraySessionHandler();
    }

    public function testReadMissingSession(): void
    {
        $data = $this->handler->read('non-existent-id');
        $this->assertSame([], $data);
    }

    public function testWriteAndReadSession(): void
    {
        $id = 'session-123';
        $data = ['foo' => 'bar', 'user_id' => 42];
        $this->handler->write($id, $data, 60);

        $this->assertSame($data, $this->handler->read($id));
    }

    public function testExistsTrue(): void
    {
        $id = 'session-456';
        $data = ['active' => true];
        $this->handler->write($id, $data, 60);

        $this->assertTrue($this->handler->exists($id));
    }

    public function testDestroySession(): void
    {
        $id = 'session-789';
        $data = ['temp' => 'value'];
        $this->handler->write($id, $data, 60);
        $this->assertTrue($this->handler->exists($id));

        $this->handler->destroy($id);
        $this->assertFalse($this->handler->exists($id));
        $this->assertSame([], $this->handler->read($id));
    }

    public function testExpiredSessionDoesNotExist(): void
    {
        $id = 'session-expired';
        $data = ['expired' => true];
        $this->handler->write($id, $data, -1);

        $this->assertFalse($this->handler->exists($id));
        $this->assertSame([], $this->handler->read($id));
    }

    public function testGcRemovesExpiredSessions(): void
    {
        $this->handler->write('session-active', ['status' => 'active'], 100);
        $this->handler->write('session-expired-1', ['status' => 'expired'], -10);
        $this->handler->write('session-expired-2', ['status' => 'expired'], -5);

        $deletedCount = $this->handler->gc(60);

        $this->assertSame(2, $deletedCount);
        $this->assertTrue($this->handler->exists('session-active'));
        $this->assertFalse($this->handler->exists('session-expired-1'));
        $this->assertFalse($this->handler->exists('session-expired-2'));
    }
}
