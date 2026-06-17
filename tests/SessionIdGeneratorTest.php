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
        $ids = [];

        for ($i = 0; $i < 100; $i++) {
            $ids[] = $this->generator->generate();
        }

        $this->assertSame($ids, array_unique($ids));
    }

    public function testLengthCukup(): void
    {
        $id = $this->generator->generate();
        $this->assertGreaterThanOrEqual(40, strlen($id));
    }

    public function testOnlySafeCharacters(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $id = $this->generator->generate();
            $this->assertMatchesRegularExpression('/\A[A-Za-z0-9_-]+\z/', $id);
            $this->assertStringNotContainsString('/', $id);
            $this->assertStringNotContainsString('\\', $id);
            $this->assertStringNotContainsString('..', $id);
        }
    }

    public function testIsValidValidId(): void
    {
        $id = $this->generator->generate();
        $this->assertTrue($this->generator->isValid($id));
    }

    public function testIsValidInvalidPathTraversal(): void
    {
        $this->assertFalse($this->generator->isValid('../traversal'));
        $this->assertFalse($this->generator->isValid('validPrefix..validSuffix12345678901234567890'));
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
        $this->assertFalse($this->generator->isValid($this->generator->generate() . ' '));
        $this->assertFalse($this->generator->isValid(' ' . $this->generator->generate()));
        $this->assertFalse($this->generator->isValid(str_repeat('a', 20) . "\n" . str_repeat('b', 20)));
    }

    public function testIsValidUnsafeCharactersInvalid(): void
    {
        $this->assertFalse($this->generator->isValid(str_repeat('a', 39) . '+'));
        $this->assertFalse($this->generator->isValid(str_repeat('a', 39) . '='));
        $this->assertFalse($this->generator->isValid(str_repeat('a', 39) . '.'));
    }
}
