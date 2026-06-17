<?php

declare(strict_types=1);

namespace Lukman\Session\Tests;

use PHPUnit\Framework\TestCase;
use Lukman\Session\SessionManager;
use Lukman\Session\SessionStore;
use Lukman\Session\SessionHandlerInterface;
use Lukman\Session\SessionIdGenerator;
use Lukman\Session\Handlers\ArraySessionHandler;
use Lukman\Session\Handlers\FileSessionHandler;
use Lukman\Session\Exception\SessionException;
use Lukman\Session\Exception\SessionNotStartedException;

/**
 * @coversNothing
 */
class AutoloadTest extends TestCase
{
    public function testClassesCanBeAutoloaded(): void
    {
        $this->assertTrue(class_exists(SessionManager::class));
        $this->assertTrue(class_exists(SessionStore::class));
        $this->assertTrue(interface_exists(SessionHandlerInterface::class));
        $this->assertTrue(class_exists(SessionIdGenerator::class));
        $this->assertTrue(class_exists(ArraySessionHandler::class));
        $this->assertTrue(class_exists(FileSessionHandler::class));
        $this->assertTrue(class_exists(SessionException::class));
        $this->assertTrue(class_exists(SessionNotStartedException::class));
    }
}
