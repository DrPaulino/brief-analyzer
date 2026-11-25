<?php

declare(strict_types=1);

namespace App;

final class BriefService
{
    private const LANGUAGES = ['pt-BR', 'en'];

    public function __construct(
        private LlmClient $llm,
        private BriefValidator $validator = new BriefValidator(),
        private int $maxLength = 2000,
    ) {
    }

    /**
     * @return array{brief: array<string, mixed>, meta: array<string, mixed>}
     * @throws InputException
     * @throws InvalidOutputException
     * @throws LlmException
     */
    public function analyze(string $text, string $language = 'pt-BR'): array
    {
        $text = trim($text);
        if ($text === '') {
            throw new InputException('text is required');
        }
        if (mb_strlen($text) > $this->maxLength) {
            throw new InputException("text must be at most {$this->maxLength} characters");
        }
        if (!in_array($language, self::LANGUAGES, true)) {
            throw new InputException('language must be pt-BR or en');
        }

        $start = hrtime(true);
        $system = Prompt::system($language);
        $retried = false;
        $brief = null;

        $result = $this->llm->complete($system, Prompt::user($text));
        $errors = $this->check($result->text, $brief);

        if ($errors !== []) {
            $retried = true;
            $result = $this->llm->complete($system, Prompt::retry($text, $errors));
            $errors = $this->check($result->text, $brief);
            if ($errors !== []) {
                throw new InvalidOutputException(implode('; ', $errors));
            }
        }

        return [
            'brief' => $this->normalize($brief),
            'meta' => [
                'provider' => $result->provider,
                'latency_ms' => (int) round((hrtime(true) - $start) / 1_000_000),
                'retried' => $retried,
                'fallback_used' => $result->fallbackUsed,
            ],
        ];
    }

    /** @return string[] */
    private function check(string $raw, ?array &$brief): array
    {
        $clean = trim($raw);
        $clean = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $clean) ?? $clean;

        $data = json_decode($clean, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $brief = null;
            return ['output is not valid JSON'];
        }

        $errors = $this->validator->validate($data);
        $brief = $errors === [] ? $data : null;

        return $errors;
    }

    /** @param array<string, mixed> $d */
    private function normalize(array $d): array
    {
        return [
            'summary' => trim($d['summary']),
            'objective' => $d['objective'] ?? null,
            'audience' => $d['audience'] ?? null,
            'deliverables' => array_map(static fn (array $x): array => [
                'platform' => $x['platform'],
                'format' => $x['format'],
                'specs' => $x['specs'] ?? null,
                'copy_notes' => $x['copy_notes'] ?? null,
                'deadline' => $x['deadline'] ?? null,
            ], $d['deliverables']),
            'missing_info' => array_values($d['missing_info'] ?? []),
            'confidence' => $d['confidence'],
        ];
    }
}
