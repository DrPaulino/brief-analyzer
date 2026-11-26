<?php

declare(strict_types=1);

namespace App;

/** Works with any provider that exposes the OpenAI chat completions format. */
final class OpenAiCompatibleClient implements LlmClient
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private string $model,
        private int $timeoutSeconds = 30,
    ) {
    }

    public function complete(string $system, string $user): LlmResult
    {
        $payload = json_encode([
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
        ], JSON_UNESCAPED_UNICODE);

        $last = null;
        foreach ([0, 1] as $attempt) {
            try {
                return $this->send($payload);
            } catch (LlmException $e) {
                $last = $e;
                if (!$this->isRetryable($e)) {
                    throw $e;
                }
                usleep(800_000); // wait 0.8 s before the second attempt
            }
        }

        throw $last;
    }

    private function isRetryable(LlmException $e): bool
    {
        return $e->httpStatus === 0
            || $e->httpStatus === 429
            || $e->httpStatus >= 500
            || str_contains($e->getMessage(), 'json_validate_failed');
    }

    private function send(string $payload): LlmResult
    {
        $ch = curl_init(rtrim($this->baseUrl, '/') . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new LlmException('Network error: ' . $error);
        }
        if ($status >= 400) {
            $code = json_decode((string) $body, true)['error']['code'] ?? null;
            throw new LlmException('Provider returned HTTP ' . $status . ($code ? " ($code)" : ''), $status);
        }

        $data = json_decode((string) $body, true);
        $text = $data['choices'][0]['message']['content'] ?? null;
        if (!is_string($text) || $text === '') {
            throw new LlmException('Empty response from provider', $status);
        }

        return new LlmResult($text, $this->model);
    }
}
