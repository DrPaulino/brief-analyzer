<?php

declare(strict_types=1);

namespace App;

interface LlmClient
{
    /** @throws LlmException */
    public function complete(string $system, string $user): LlmResult;
}
