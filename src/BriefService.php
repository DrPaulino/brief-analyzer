<?php

declare(strict_types=1);

namespace App;

final class BriefService
{
    private const LANGUAGES = ['pt-BR', 'en'];
    private const PLATFORM_SERVICES = [
        'meta' => ['instagram', 'facebook', 'whatsapp'],
        'tiktok' => ['tiktok'],
        'google_ads' => ['search', 'youtube', 'display'],
    ];

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
        $today = (new \DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');

        $deliverables = array_map(static function (array $x) use ($today): array {
            $deadline = $x['deadline'] ?? null;
            if ($deadline !== null && $deadline < $today) {
                $deadline = null; // a deadline in the past is almost certainly a mistake
            }

            $service = $x['service'] ?? null;
            if (!in_array($service, self::PLATFORM_SERVICES[$x['platform']] ?? [], true)) {
                $service = null; // service must belong to the platform
            }

            return [
                'platform' => $x['platform'],
                'service' => $service,
                'title' => isset($x['title']) ? mb_substr(trim($x['title']), 0, 60) : null,
                'quantity' => $x['quantity'] ?? null,
                'duration_seconds' => $x['duration_seconds'] ?? null,
                'aspect_ratio' => $x['aspect_ratio'] ?? null,
                'format' => $x['format'],
                'specs' => $x['specs'] ?? null,
                'copy_notes' => $x['copy_notes'] ?? null,
                'deadline' => $deadline,
            ];
        }, $d['deliverables']);

        $brief = [
            'summary' => trim($d['summary']),
            'campaign' => isset($d['campaign']) ? mb_substr(trim($d['campaign']), 0, 40) : null,
            'objective' => $d['objective'] ?? null,
            'audience' => $d['audience'] ?? null,
            'deliverables' => $deliverables,
            'missing_info' => array_values($d['missing_info'] ?? []),
        ];
        $brief['confidence'] = $this->confidence($brief);

        return $brief;
    }

    /** @param array<string, mixed> $b */
    private function confidence(array $b): string
    {
        $questions = count($b['missing_info']);

        if ($b['deliverables'] === [] || $questions >= 3) {
            return 'low';
        }

        $complete = $b['objective'] !== null && $b['audience'] !== null;
        foreach ($b['deliverables'] as $deliverable) {
            if ($deliverable['deadline'] === null) {
                $complete = false;
            }
        }

        return ($complete && $questions <= 1) ? 'high' : 'medium';
    }
}
