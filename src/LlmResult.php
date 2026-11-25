<?php

declare(strict_types=1);

namespace App;

final class LlmResult
{
    public function __construct(
        public readonly string $text,
        public readonly string $provider,
        public readonly bool $fallbackUsed = false,
    ) {
    }
}
