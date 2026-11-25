<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\BriefService;
use App\FallbackClient;
use App\InputException;
use App\InvalidOutputException;
use App\LlmException;
use App\OpenAiCompatibleClient;

function loadEnv(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        if (getenv(trim($key)) === false) {
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

function errorBody(string $code, string $message, bool $retryable): array
{
    return ['error' => ['code' => $code, 'message' => $message, 'retryable' => $retryable]];
}

function respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function buildLlm(): FallbackClient
{
    $clients = [];
    foreach (['LLM', 'LLM_FALLBACK'] as $prefix) {
        $url = getenv("{$prefix}_BASE_URL");
        $key = getenv("{$prefix}_API_KEY");
        $model = getenv("{$prefix}_MODEL");
        if ($url && $key && $model) {
            $clients[] = new OpenAiCompatibleClient($url, $key, $model);
        }
    }
    if ($clients === []) {
        respond(500, errorBody('server_misconfigured', 'No LLM provider configured', false));
    }
    return new FallbackClient($clients);
}

loadEnv(__DIR__ . '/../.env');

header('Content-Type: application/json; charset=utf-8');
$staticPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (!str_starts_with($staticPath, '/api/')) {
    $page = $staticPath === '/' ? '/index.html' : $staticPath;
    $file = __DIR__ . $page;
    header('Content-Type: text/html; charset=utf-8');
    if (!str_contains($page, '..') && str_ends_with($page, '.html') && is_file($file)) {
        readfile($file);
        exit;
    }
    http_response_code(404);
    readfile(__DIR__ . '/error.html');
    exit;
}
header('Access-Control-Allow-Origin: ' . (getenv('CORS_ORIGIN') ?: '*'));
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');


$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($method === 'GET' && $path === '/api/health') {
    respond(200, ['status' => 'ok']);
}

if ($method === 'POST' && $path === '/api/briefs') {
    $body = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($body)) {
        respond(400, errorBody('invalid_input', 'Body must be a JSON object', false));
    }

    $text = $body['text'] ?? '';
    $language = $body['language'] ?? 'pt-BR';
    if (!is_string($text) || !is_string($language)) {
        respond(400, errorBody('invalid_input', 'text and language must be strings', false));
    }

    try {
        $result = (new BriefService(buildLlm()))->analyze($text, $language);
        // Log metadata only. Never log the request text: it may hold client data.
        error_log(json_encode(['event' => 'brief_ok'] + $result['meta']));
        respond(200, ['id' => 'brf_' . bin2hex(random_bytes(4))] + $result);
    } catch (InputException $e) {
        respond(400, errorBody('invalid_input', $e->getMessage(), false));
    } catch (InvalidOutputException $e) {
        error_log(json_encode(['event' => 'brief_invalid_output', 'reason' => $e->getMessage()]));
        respond(422, errorBody('invalid_output', 'Could not produce a valid brief. Try again.', true));
    } catch (LlmException $e) {
        error_log(json_encode(['event' => 'llm_error', 'status' => $e->httpStatus, 'reason' => $e->getMessage()]));
        $status = $e->httpStatus === 429 ? 429 : 502;
        respond($status, errorBody('llm_unavailable', 'The AI provider is unavailable. Try again shortly.', true));
    } catch (Throwable $e) {
        error_log(json_encode(['event' => 'unexpected_error', 'reason' => $e->getMessage()]));
        respond(500, errorBody('internal_error', 'Unexpected error', false));
    }
}

respond(404, errorBody('not_found', 'Route not found', false));
