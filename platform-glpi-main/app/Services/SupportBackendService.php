<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupportBackendService
{
    private string $baseUrl;
    private string $prefix;
    private int $timeout;
    private ?string $bearerToken;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.support_api.base_url'), '/');
        $this->prefix = '/' . trim((string) config('services.support_api.prefix', '/api/v1'), '/');
        $this->timeout = (int) config('services.support_api.timeout', 30);
        $this->bearerToken = config('services.support_api.bearer_token');
    }

    public function ingestGlpiTicket(array $payload): array
    {
        $errors = [];

        foreach ($this->baseUrlCandidates() as $baseUrl) {
            $url = $this->urlFromBase($baseUrl, '/internal/tickets/glpi-ingest');

            try {
                $response = $this->client()->post($url, $payload);

                if ($response->successful()) {
                    return $response->json() ?? [];
                }

                $errors[] = "{$url}: {$response->status()} {$response->body()}";
                Log::warning('Support backend GLPI ingest failed: ' . end($errors));
            } catch (\Throwable $e) {
                $errors[] = "{$url}: exception {$e->getMessage()}";
                Log::warning('Support backend GLPI ingest exception: ' . end($errors));
            }
        }

        $message = implode(' | ', $errors);
        throw new \RuntimeException('Backend ingest failed: ' . $message);
    }

    private function client()
    {
        $headers = ['Accept' => 'application/json'];
        if ($this->bearerToken) {
            $headers['Authorization'] = 'Bearer ' . $this->bearerToken;
        }

        return Http::timeout($this->timeout)->withHeaders($headers);
    }

    private function url(string $path): string
    {
        return $this->baseUrl . $this->prefix . '/' . ltrim($path, '/');
    }

    private function baseUrlCandidates(): array
    {
        $configured = $this->baseUrl;
        $public = rtrim((string) config('services.support_api.public_url'), '/');

        return array_values(array_unique(array_filter([
            $configured,
            $public,
            'http://support_api:8600',
            'http://api:8600',
            'http://localhost:8600',
            'http://127.0.0.1:8600',
            'http://host.docker.internal:8600',
        ])));
    }

    private function urlFromBase(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/') . '/' . trim((string) config('services.support_api.prefix', '/api/v1'), '/') . '/' . ltrim($path, '/');
    }
}
