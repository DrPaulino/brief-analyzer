# Brief Analyzer API

Turns free-text ad creative requests (Meta, TikTok, Google Ads, ...) into a structured JSON brief using an LLM.

## Run locally

    composer install
    cp .env.example .env        # then fill in your API key
    composer start

    curl -X POST localhost:8080/api/briefs \
      -H "Content-Type: application/json" \
      -d '{"text":"Preciso de videos curtos pro TikTok da Black Friday, pronto dia 25.","language":"pt-BR"}'

## Tests

    composer test

The LLM is mocked in tests, so they never use your free-tier quota.

## Docker

    docker build -t brief-analyzer-api .
    docker run --rm -p 8080:8080 --env-file .env brief-analyzer-api

## Endpoints

- `POST /api/briefs`: body `{ "text": string, "language": "pt-BR" | "en" }`
- `GET /api/health`

## Notes

- Any OpenAI-compatible provider works: set `LLM_BASE_URL`, `LLM_API_KEY`, `LLM_MODEL`. Add `LLM_FALLBACK_*` for a second provider.
- Free-tier model names and limits change, so check the provider docs.
- The built-in PHP server is fine for a demo. Put a real web server in front for production.
- Request text is never logged, only metadata (provider, latency, retry, fallback).
