<?php

declare(strict_types=1);

namespace App;

/** Tries each client in order and moves to the next one when a call fails. */
final class FallbackClient implements LlmClient
{
    /** @param LlmClient[] $clients */
    public function __construct(private array $clients)
    {
        if ($clients === []) {
            throw new \InvalidArgumentException('At least one LLM client is required');
        }
    }

    public function complete(string $system, string $user): LlmResult
    {
        $last = null;
        foreach ($this->clients as $index => $client) {
            try {
                $result = $client->complete($system, $user);
                return new LlmResult($result->text, $result->provider, $index > 0);
            } catch (LlmException $e) {
                $last = $e;
            }
        }
        throw $last;
    }
}
