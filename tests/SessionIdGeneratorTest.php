<?php

declare(strict_types=1);

namespace Lukman\Session\Tests;

use PHPUnit\Framework\TestCase;
use Lukman\Session\SessionIdGenerator;

/**
 * @covers \Lukman\Session\SessionIdGenerator
 */
class SessionIdGeneratorTest extends TestCase
{
    private SessionIdGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new SessionIdGenerator();
    }

    public function testGenerateString(): void
    {
        $id = $this->generator->generate();
        $this->assertIsString($id);
    }

    public function testGenerateUnique(): void
    {
        $id1 = $this->generator->generate();
        $id2 = $this->generator->generate();
        $this->assertNotSame($id1, $id2);
    }

    public function testLengthCukup(): void
    {
        $id = $this->generator->generate();
        $this->assertGreaterThanOrEqual(40, strlen($id));
    }

    public function testOnlySafeCharacters(): void
    {
        $id = $this->generator->generate();
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_-]+$/', $id);
    }

    public function testIsValidValidId(): void
    {
        $id = $this->generator->generate();
        $this->assertTrue($this->generator->isValid($id));
    }

    public function testIsValidInvalidPathTraversal(): void
    {
        $this->assertFalse($this->generator->isValid('../traversal'));
        $this->assertFalse($this->generator->isValid('sub/directory'));
        $this->assertFalse($this->generator->isValid('sub\\directory'));
    }

    public function testIsValidEmptyFalse(): void
    {
        $this->assertFalse($this->generator->isValid(''));
    }

    public function testIsValidShortInvalid(): void
    {
        $this->assertFalse($this->generator->isValid('short_id_less_than_40_chars'));
    }

    public function testIsValidWhitespaceInvalid(): void
    {
        $id = $this->generator->generate() . ' ';
        $this->assertFalse($this->generator->isValid($id));
    }
}
