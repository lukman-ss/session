<?php

declare(strict_types=1);

namespace Lukman\Session\Tests;

use PHPUnit\Framework\TestCase;
use Lukman\Session\SessionStore;
use Lukman\Session\Handlers\ArraySessionHandler;
use Lukman\Session\Exception\SessionException;
use Lukman\Session\Exception\SessionNotStartedException;

/**
 * @covers \Lukman\Session\SessionStore
 */
class SessionStoreTest extends TestCase
{
    private ArraySessionHandler $handler;
    private SessionStore $store;

    protected function setUp(): void
    {
        $this->handler = new ArraySessionHandler();
        $this->store = new SessionStore($this->handler);
    }

    public function testStartCreatesId(): void
    {
        $this->assertFalse($this->store->started());

        $this->store->start();

        $this->assertTrue($this->store->started());
        $this->assertNotEmpty($this->store->id());
        $this->assertGreaterThanOrEqual(40, strlen($this->store->id()));
    }

    public function testStartLoadsExistingData(): void
    {
        $id = 'existing-session-id-with-length-greater-than-forty-chars';
        $data = ['user' => 'lukman', 'role' => 'admin'];
        $this->handler->write($id, $data, 3600);

        $store = new SessionStore($this->handler, null, $id);
        $store->start();

        $this->assertSame($id, $store->id());
        $this->assertSame($data, $store->all());
    }

    public function testPutGet(): void
    {
        $this->store->start();
        $this->store->put('theme', 'dark');

        $this->assertSame('dark', $this->store->get('theme'));
        $this->assertSame('default-value', $this->store->get('missing-key', 'default-value'));
    }

    public function testHasNullValue(): void
    {
        $this->store->start();
        $this->store->put('nullable', null);

        $this->assertTrue($this->store->has('nullable'));
        $this->assertFalse($this->store->missing('nullable'));

        $this->assertFalse($this->store->has('non-existent'));
        $this->assertTrue($this->store->missing('non-existent'));
    }

    public function testForget(): void
    {
        $this->store->start();
        $this->store->put('key1', 'val1');
        $this->store->put('key2', 'val2');

        $this->assertTrue($this->store->has('key1'));
        $this->store->forget('key1');

        $this->assertFalse($this->store->has('key1'));
        $this->assertTrue($this->store->has('key2'));
    }

    public function testFlush(): void
    {
        $this->store->start();
        $this->store->put('key1', 'val1');
        $this->store->put('key2', 'val2');

        $this->store->flush();

        $this->assertSame([], $this->store->all());
    }

    public function testSaveWritesHandler(): void
    {
        $this->store->start();
        $this->store->put('foo', 'bar');
        $id = $this->store->id();

        $this->store->save();

        $savedData = $this->handler->read($id);
        $this->assertSame('bar', $savedData['foo']);
        $this->assertArrayHasKey('_flash', $savedData);
    }

    public function testDataPersistsAfterSaveAndRestart(): void
    {
        $this->store->start();
        $this->store->put('user_id', 42);
        $this->store->put('nullable', null);
        $id = $this->store->id();

        $this->store->save();

        $nextStore = new SessionStore($this->handler, null, $id);
        $nextStore->start();

        $this->assertTrue($nextStore->started());
        $this->assertSame(42, $nextStore->get('user_id'));
        $this->assertTrue($nextStore->has('nullable'));
        $this->assertNull($nextStore->get('nullable'));
    }

    public function testOperationBeforeStartThrows(): void
    {
        $this->expectException(SessionNotStartedException::class);
        $this->store->put('key', 'val');
    }

    public function testGetBeforeStartThrows(): void
    {
        $this->expectException(SessionNotStartedException::class);
        $this->store->get('key');
    }

    public function testHasBeforeStartThrows(): void
    {
        $this->expectException(SessionNotStartedException::class);
        $this->store->has('key');
    }

    public function testForgetBeforeStartThrows(): void
    {
        $this->expectException(SessionNotStartedException::class);
        $this->store->forget('key');
    }

    public function testSaveBeforeStartThrows(): void
    {
        $this->expectException(SessionNotStartedException::class);
        $this->store->save();
    }

    public function testIdBeforeStartThrows(): void
    {
        $this->expectException(SessionNotStartedException::class);
        $this->store->id();
    }

    // --- Phase 6 Tests ---

    public function testGetNested(): void
    {
        $this->store->start();
        $this->store->put('user', ['name' => 'Lukman', 'profile' => ['age' => 30]]);

        $this->assertSame('Lukman', $this->store->get('user.name'));
        $this->assertSame(30, $this->store->get('user.profile.age'));
        $this->assertNull($this->store->get('user.profile.location'));
        $this->assertSame('default', $this->store->get('user.profile.location', 'default'));
    }

    public function testPutNested(): void
    {
        $this->store->start();
        $this->store->put('user.name', 'Lukman');
        $this->store->put('user.profile.age', 30);

        $expected = [
            'user' => [
                'name' => 'Lukman',
                'profile' => [
                    'age' => 30
                ]
            ]
        ];

        $this->assertSame($expected, $this->store->all());
    }

    public function testHasNestedNull(): void
    {
        $this->store->start();
        $this->store->put('user.profile.age', null);

        $this->assertTrue($this->store->has('user.profile.age'));
        $this->assertFalse($this->store->has('user.profile.location'));
    }

    public function testForgetNested(): void
    {
        $this->store->start();
        $this->store->put('user.name', 'Lukman');
        $this->store->put('user.profile.age', 30);

        $this->store->forget('user.profile.age');
        $this->assertFalse($this->store->has('user.profile.age'));
        $this->assertTrue($this->store->has('user.name'));
    }

    public function testPullNested(): void
    {
        $this->store->start();
        $this->store->put('user.profile.age', 30);

        $pulled = $this->store->pull('user.profile.age');
        $this->assertSame(30, $pulled);
        $this->assertFalse($this->store->has('user.profile.age'));
    }

    public function testReplace(): void
    {
        $this->store->start();
        $this->store->put('foo', 'bar');

        $newData = ['a' => 'b', 'c' => 'd'];
        $this->store->replace($newData);

        $this->assertSame($newData, $this->store->all());
    }

    public function testOnly(): void
    {
        $this->store->start();
        $this->store->put('user.name', 'Lukman');
        $this->store->put('user.age', 30);
        $this->store->put('token', 'xyz');

        $result = $this->store->only(['user.name', 'token', 'missing']);
        $this->assertSame([
            'user.name' => 'Lukman',
            'token' => 'xyz'
        ], $result);
    }

    public function testExcept(): void
    {
        $this->store->start();
        $this->store->put('user.name', 'Lukman');
        $this->store->put('user.age', 30);
        $this->store->put('token', 'xyz');

        $result = $this->store->except(['token', 'user.age']);
        $this->assertSame([
            'user' => [
                'name' => 'Lukman'
            ]
        ], $result);
    }

    public function testIncrementMissing(): void
    {
        $this->store->start();

        $newValue = $this->store->increment('counter', 5);
        $this->assertSame(5, $newValue);
        $this->assertSame(5, $this->store->get('counter'));
    }

    public function testIncrementExisting(): void
    {
        $this->store->start();
        $this->store->put('counter', 10);

        $newValue = $this->store->increment('counter');
        $this->assertSame(11, $newValue);
        $this->assertSame(11, $this->store->get('counter'));
    }

    public function testDecrement(): void
    {
        $this->store->start();

        $newValue1 = $this->store->decrement('counter', 2);
        $this->assertSame(-2, $newValue1);

        $this->store->put('counter2', 10);
        $newValue2 = $this->store->decrement('counter2');
        $this->assertSame(9, $newValue2);
    }

    public function testIncrementNonNumericThrows(): void
    {
        $this->store->start();
        $this->store->put('string_key', 'hello');

        $this->expectException(SessionException::class);
        $this->store->increment('string_key');
    }

    public function testDecrementNonNumericThrows(): void
    {
        $this->store->start();
        $this->store->put('string_key', 'hello');

        $this->expectException(SessionException::class);
        $this->store->decrement('string_key');
    }

    // --- Phase 7 Tests ---

    public function testFlashAvailableBeforeSave(): void
    {
        $this->store->start();
        $this->store->flash('status', 'success');

        $this->assertTrue($this->store->has('status'));
        $this->assertSame('success', $this->store->get('status'));
    }

    public function testFlashPersistsUntilNextLifecycle(): void
    {
        $this->store->start();
        $this->store->flash('status', 'success');
        $this->store->ageFlashData();
        $id = $this->store->id();
        $this->store->save();

        $nextStore = new SessionStore($this->handler, null, $id);
        $nextStore->start();

        $this->assertSame('success', $nextStore->get('status'));

        $nextStore->ageFlashData();

        $this->assertFalse($nextStore->has('status'));
    }

    public function testAgeRemovesOldFlash(): void
    {
        $this->store->start();
        $this->store->flash('status', 'success');

        $this->store->ageFlashData();
        $this->assertTrue($this->store->has('status'));
        $this->assertSame('success', $this->store->get('status'));

        $this->store->ageFlashData();
        $this->assertFalse($this->store->has('status'));
        $this->assertNull($this->store->get('status'));
    }

    public function testReflashKeepsData(): void
    {
        $this->store->start();
        $this->store->flash('status', 'success');

        $this->store->ageFlashData();
        $this->store->reflash();

        $this->store->ageFlashData();
        $this->assertTrue($this->store->has('status'));

        $this->store->ageFlashData();
        $this->assertFalse($this->store->has('status'));
    }

    public function testKeepSpecificKey(): void
    {
        $this->store->start();
        $this->store->flash('status', 'success');
        $this->store->flash('error', 'none');

        $this->store->ageFlashData();
        $this->store->keep('status');

        $this->store->ageFlashData();
        $this->assertTrue($this->store->has('status'));
        $this->assertFalse($this->store->has('error'));
    }

    public function testNowRemovedAfterAge(): void
    {
        $this->store->start();
        $this->store->now('status', 'success');

        $this->assertTrue($this->store->has('status'));

        $this->store->ageFlashData();
        $this->assertFalse($this->store->has('status'));
    }

    public function testFlashInputAndOld(): void
    {
        $this->store->start();
        $input = ['username' => 'lukman', 'nested' => ['role' => 'admin']];
        $this->store->flashInput($input);

        $this->assertSame($input, $this->store->old());
        $this->assertSame('lukman', $this->store->old('username'));
        $this->assertSame('admin', $this->store->old('nested.role'));
        $this->assertSame('default', $this->store->old('missing_key', 'default'));
    }

    public function testCorruptFlashMetadataIsNormalized(): void
    {
        $id = 'session-with-corrupt-flash-metadata';
        $this->handler->write($id, [
            'status' => 'success',
            '_flash' => [
                'new' => 'invalid',
                'old' => ['status', '', 123, 'status'],
            ],
        ], 3600);

        $store = new SessionStore($this->handler, null, $id);
        $store->start();
        $store->ageFlashData();

        $this->assertFalse($store->has('status'));
    }

    // --- Phase 8 Tests ---

    public function testRegenerateChangesId(): void
    {
        $this->store->start();
        $oldId = $this->store->id();

        $this->store->regenerate();
        $newId = $this->store->id();

        $this->assertNotSame($oldId, $newId);
        $this->assertGreaterThanOrEqual(40, strlen($newId));
    }

    public function testRegenerateKeepsData(): void
    {
        $this->store->start();
        $this->store->put('user', 'lukman');
        $oldId = $this->store->id();

        $this->store->regenerate();

        $this->assertSame('lukman', $this->store->get('user'));
    }

    public function testRegenerateDestroyOld(): void
    {
        $this->store->start();
        $this->store->put('user', 'lukman');
        $oldId = $this->store->id();

        // Write some data in handler for oldId so it is physically present
        $this->store->save();
        $this->assertTrue($this->handler->exists($oldId));

        $this->store->regenerate(true);

        // Handler should not contain oldId anymore
        $this->assertFalse($this->handler->exists($oldId));
    }

    public function testInvalidateFlushesData(): void
    {
        $this->store->start();
        $this->store->put('user', 'lukman');
        $oldId = $this->store->id();
        $this->store->save();

        $this->store->invalidate();

        $this->assertNotSame($oldId, $this->store->id());
        $this->assertSame([], $this->store->all());
        $this->assertFalse($this->handler->exists($oldId));
    }

    public function testDestroyRemovesHandlerData(): void
    {
        $this->store->start();
        $this->store->put('user', 'lukman');
        $id = $this->store->id();
        $this->store->save();

        $this->assertTrue($this->handler->exists($id));

        $this->store->destroy();

        $this->assertFalse($this->handler->exists($id));
    }

    public function testTokenGenerated(): void
    {
        $this->store->start();
        $this->assertFalse($this->store->has('_token'));

        $token = $this->store->token();
        $this->assertNotEmpty($token);
        $this->assertTrue($this->store->has('_token'));
    }

    public function testTokenStable(): void
    {
        $this->store->start();
        $token1 = $this->store->token();
        $token2 = $this->store->token();

        $this->assertSame($token1, $token2);
    }

    public function testRegenerateTokenChangesToken(): void
    {
        $this->store->start();
        $token1 = $this->store->token();

        $token2 = $this->store->regenerateToken();
        $this->assertNotSame($token1, $token2);
        $this->assertSame($token2, $this->store->token());
    }

    public function testSecurityLifecycleBeforeStartThrows(): void
    {
        $methods = [
            fn() => $this->store->regenerate(),
            fn() => $this->store->invalidate(),
            fn() => $this->store->destroy(),
            fn() => $this->store->token(),
            fn() => $this->store->regenerateToken(),
        ];

        foreach ($methods as $method) {
            try {
                $method();
                $this->fail('Expected SessionNotStartedException.');
            } catch (SessionNotStartedException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
