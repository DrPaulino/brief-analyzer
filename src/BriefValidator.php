<?php

declare(strict_types=1);

namespace App;

final class BriefValidator
{
    private const OBJECTIVES = ['awareness', 'traffic', 'leads', 'sales'];
    private const PLATFORMS = ['meta', 'tiktok', 'google_ads', 'other'];
    private const FORMATS = ['video', 'static', 'carousel', 'search_ad', 'other'];
    private const SERVICES = ['instagram', 'facebook', 'whatsapp', 'tiktok', 'search', 'youtube', 'display'];
    private const CONFIDENCE = ['low', 'medium', 'high'];

    /** @return string[] list of problems, empty when valid */
    public function validate(mixed $data): array
    {
        if (!is_array($data) || array_is_list($data)) {
            return ['output is not a JSON object'];
        }

        $errors = [];

        $summary = $data['summary'] ?? null;
        if (!is_string($summary) || trim($summary) === '') {
          $errors[] = 'summary must be a non-empty string';
        }
        if (is_string($summary) && mb_strlen($summary) > 500) {
          $errors[] = 'summary must be at most 500 characters';
        }
        $objective = $data['objective'] ?? null;
        if ($objective !== null && !in_array($objective, self::OBJECTIVES, true)) {
            $errors[] = 'objective must be one of ' . implode(', ', self::OBJECTIVES) . ' or null';
        }

        $audience = $data['audience'] ?? null;
        if ($audience !== null && !is_string($audience)) {
            $errors[] = 'audience must be a string or null';
        }
        if (is_string($audience) && mb_strlen($audience) > 300) {
         $errors[] = 'audience must be at most 300 characters';
        }
        $deliverables = $data['deliverables'] ?? null;
        if (!is_array($deliverables) || !array_is_list($deliverables)) {
            $errors[] = 'deliverables must be a list';
        } else {
            foreach ($deliverables as $i => $item) {
                array_push($errors, ...$this->deliverable($item, $i));
            }
        }

        $missing = $data['missing_info'] ?? [];
        if (!is_array($missing) || !array_is_list($missing)) {
            $errors[] = 'missing_info must be a list of strings';
        } else {
            foreach ($missing as $question) {
                if (!is_string($question)) {
                    $errors[] = 'missing_info must contain only strings';
                    break;
                }
            }
        }

        if (!in_array($data['confidence'] ?? null, self::CONFIDENCE, true)) {
            $errors[] = 'confidence must be one of ' . implode(', ', self::CONFIDENCE);
        }

        return $errors;
    }

    /** @return string[] */
    private function deliverable(mixed $item, int $i): array
    {
        if (!is_array($item)) {
            return ["deliverables[$i] must be an object"];
        }

        $errors = [];
        $service = $item['service'] ?? null;

        if (!in_array($item['platform'] ?? null, self::PLATFORMS, true)) {
            $errors[] = "deliverables[$i].platform must be one of " . implode(', ', self::PLATFORMS);
        }
        if (!in_array($item['format'] ?? null, self::FORMATS, true)) {
            $errors[] = "deliverables[$i].format must be one of " . implode(', ', self::FORMATS);
        }
        if ($service !== null && !in_array($service, self::SERVICES, true)) {
          $errors[] = "deliverables[$i].service must be one of " . implode(', ', self::SERVICES) . ' or null';
        }
        foreach (['specs', 'copy_notes'] as $key) {
            $value = $item[$key] ?? null;
            if ($value !== null && !is_string($value)) {
                $errors[] = "deliverables[$i].$key must be a string or null";
            }
        }

        $deadline = $item['deadline'] ?? null;
        if ($deadline !== null && !$this->isDate($deadline)) {
            $errors[] = "deliverables[$i].deadline must be YYYY-MM-DD or null";
        }

        return $errors;
    }

    private function isDate(mixed $value): bool
    {
        if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return false;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }
}
