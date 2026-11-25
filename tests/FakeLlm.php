<?php

declare(strict_types=1);

namespace Tests;

use App\LlmClient;
use App\LlmResult;

/** Returns queued answers (or throws queued exceptions) so tests never call a real API. */
final class FakeLlm implements LlmClient
{
    public int $calls = 0;

    /** @param array<int, string|\Throwable> $queue */
    public function __construct(private array $queue)
    {
    }

    public function complete(string $system, string $user): LlmResult
    {
        $this->calls++;
        $next = array_shift($this->queue);
        if ($next instanceof \Throwable) {
            throw $next;
        }
        return new LlmResult((string) $next, 'fake');
    }
}
