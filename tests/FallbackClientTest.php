<?php

declare(strict_types=1);

namespace Tests;

use App\FallbackClient;
use App\LlmException;
use PHPUnit\Framework\TestCase;

final class FallbackClientTest extends TestCase
{
    public function testUsesNextProviderWhenFirstFails(): void
    {
        $first = new FakeLlm([new LlmException('rate limited', 429)]);
        $second = new FakeLlm(['ok']);

        $result = (new FallbackClient([$first, $second]))->complete('system', 'user');

        $this->assertSame('ok', $result->text);
        $this->assertTrue($result->fallbackUsed);
    }

    public function testDoesNotFlagFallbackWhenFirstWorks(): void
    {
        $result = (new FallbackClient([new FakeLlm(['ok']), new FakeLlm(['unused'])]))
            ->complete('system', 'user');

        $this->assertFalse($result->fallbackUsed);
    }

    public function testThrowsWhenAllProvidersFail(): void
    {
        $this->expectException(LlmException::class);

        (new FallbackClient([
            new FakeLlm([new LlmException('down', 503)]),
            new FakeLlm([new LlmException('down', 503)]),
        ]))->complete('system', 'user');
    }
}
