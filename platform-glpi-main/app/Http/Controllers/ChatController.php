<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatController extends Controller
{
    public function conversations(Request $request)
    {
        return $this->jsonProxy($request, 'GET', '/conversations', true);
    }

    public function messages(Request $request, string $id)
    {
        return $this->jsonProxy($request, 'GET', "/conversations/{$id}/messages", true);
    }

    public function deleteConversation(Request $request, string $id)
    {
        return $this->jsonProxy($request, 'DELETE', "/conversations/{$id}", true);
    }

    public function updateConversation(Request $request, string $id)
    {
        $subject = trim((string) $request->input('subject', ''));
        if ($subject === '') {
            return response()->json(['message' => 'Le sujet est obligatoire.'], 422);
        }

        try {
            $response = $this->sendToSupportApi(
                $request,
                'PATCH',
                "/conversations/{$id}",
                ['subject' => $subject],
                true
            );

            if (!$response->successful()) {
                return response()->json($response->json() ?: ['message' => $response->body()], $response->status());
            }

            return response()->json($response->json(), $response->status());
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Service non disponible. Veuillez reessayer plus tard.',
                'message' => 'Service non disponible. Veuillez reessayer plus tard.',
                'detail' => $e->getMessage(),
            ], 503);
        }
    }

    public function send(Request $request)
    {
        $message = trim((string) $request->input('message', ''));
        if ($message === '') {
            return response()->json(['message' => 'Le message est obligatoire.'], 422);
        }

        try {
            $response = $this->sendToSupportApi(
                $request,
                'POST',
                '/conversations/stream',
                [
                    'content' => $message,
                    'conversation_id' => $request->input('conversation_id'),
                ],
                true
            );

            if (!$response->successful()) {
                return response()->json($response->json() ?: ['message' => $response->body()], $response->status());
            }

            $parsed = $this->parseStream($response->body());

            return response()->json([
                'conversation_id' => $parsed['conversation_id'] ?? $request->input('conversation_id'),
                'response' => $parsed['response'] ?? 'Message recu.',
                'message' => $parsed['response'] ?? 'Message recu.',
            ]);
        } catch (\Throwable $e) {
            $message = 'Erreur lors de l\'envoi du message. Service indisponible.';
            return response()->json([
                'error' => $message,
                'message' => $message,
            ], 503);
        }
    }

    private function jsonProxy(Request $request, string $method, string $path, bool $useAuthToken = true)
    {
        try {
            $response = $this->sendToSupportApi($request, $method, $path, null, 'application/json', $useAuthToken);
            return response($response->body(), $response->status())
                ->header('Content-Type', $response->header('Content-Type', 'application/json'));
        } catch (\Throwable $e) {
            $message = 'Service non disponible. Veuillez reessayer plus tard.';
            if ($this->isConnectionIssue($e)) {
                $message = 'Impossible de se connecter au service de chat. Le serveur est peut-etre hors ligne.';
            }

            return response()->json([
                'error' => $message,
                'message' => $message,
                'detail' => $e->getMessage(),
            ], 503);
        }
    }

    private function sendToSupportApi(
        Request $request,
        string $method,
        string $path,
        array|string|null $body = null,
        ?string $contentType = 'application/json',
        bool $useAuthToken = true
    ) {
        $lastException = null;

        foreach ($this->supportApiBaseUrlCandidates() as $baseUrl) {
            try {
                $client = $this->buildApiClient($useAuthToken ? $request->bearerToken() : null, $useAuthToken);
                if (is_array($body)) {
                    return match (strtoupper($method)) {
                        'POST' => $client->post($this->supportApiUrl($baseUrl, $path), $body),
                        'PATCH' => $client->patch($this->supportApiUrl($baseUrl, $path), $body),
                        'PUT' => $client->put($this->supportApiUrl($baseUrl, $path), $body),
                        'DELETE' => $client->delete($this->supportApiUrl($baseUrl, $path), $body),
                        default => $client->send($method, $this->supportApiUrl($baseUrl, $path)),
                    };
                }

                if ($body !== null) {
                    $client = $client->withBody($body, $contentType ?? 'application/json');
                }

                return $client->send($method, $this->supportApiUrl($baseUrl, $path));
            } catch (ConnectionException $e) {
                $lastException = $e;
                continue;
            }
        }

        throw $lastException ?? new \RuntimeException('Unable to reach support backend');
    }

    private function buildApiClient(?string $requestToken = null, bool $allowUserToken = true)
    {
        $headers = ['Accept' => 'application/json'];
        $token = $requestToken;

        if ($allowUserToken && auth()->check()) {
            $user = auth()->user();

            // Always include Laravel user ID so FastAPI can identify the user
            // even when the JWT has expired (deps.py supports X-Laravel-User-Id fallback).
            $headers['X-Laravel-User-Id'] = (string) $user->id;

            if (!$token) {
                // Use the cached token if it's still fresh (JWT expires in 15 min,
                // we refresh after 13 min to avoid using an expired token).
                $cachedToken = session('python_token');
                $cachedAt    = (int) session('python_token_at', 0);
                $ttl         = 13 * 60; // seconds

                if ($cachedToken && (time() - $cachedAt) < $ttl) {
                    $token = $cachedToken;
                } else {
                    $token = $this->fetchAndStorePythonToken($user);
                }
            }
        }

        if (!$token) {
            $token = config('services.support_api.bearer_token');
        }

        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return Http::timeout((int) config('services.support_api.timeout', 30))
            ->withHeaders($headers);
    }

    private function supportApiBaseUrlCandidates(): array
    {
        $configured = $this->trimApiSuffix((string) config('services.support_api.base_url'));
        $public = $this->trimApiSuffix((string) config('services.support_api.public_url'));

        $candidates = array_filter([
            $configured,
            $public,
            'http://support_api:8600',
            'http://api:8600',
            'http://localhost:8600',
            'http://127.0.0.1:8600',
            'http://host.docker.internal:8600',
        ]);

        return array_values(array_unique($candidates));
    }

    private function trimApiSuffix(string $baseUrl): string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        if ($baseUrl === '') {
            return '';
        }

        return preg_replace('#/api/v\d+$#i', '', $baseUrl) ?: $baseUrl;
    }

    private function supportApiUrl(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/')
            . '/'
            . trim((string) config('services.support_api.prefix', '/api/v1'), '/')
            . '/'
            . ltrim($path, '/');
    }

    private function isConnectionIssue(\Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        $message = strtolower($e->getMessage());
        foreach (['connection refused', 'could not resolve host', 'connection timed out', 'failed to connect'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function fetchAndStorePythonToken($user): ?string
    {
        $pythonRole = match ($user->role) {
            'super_admin' => 'ADMIN',
            'admin' => 'AGENT',
            default => 'CLIENT',
        };

        foreach ($this->supportApiBaseUrlCandidates() as $baseUrl) {
            try {
                $url = $this->supportApiUrl($baseUrl, '/internal');

                Http::timeout(5)
                    ->withHeaders(['X-Service-Key' => 'change-me-internal-key'])
                    ->post($url . '/sync-laravel-user', [
                        'laravel_user_id' => $user->id,
                        'email' => $user->email,
                        'role' => $pythonRole,
                    ]);

                $response = Http::timeout(5)
                    ->withHeaders(['X-Service-Key' => 'change-me-internal-key'])
                    ->post($url . '/laravel-token', [
                        'laravel_user_id' => $user->id,
                    ]);

                if ($response->successful()) {
                    $token = $response->json('access_token');
                    session([
                        'python_token'    => $token,
                        'python_token_at' => time(),
                    ]);
                    return $token;
                }
            } catch (\Throwable $e) {
                \Log::warning('Dynamic Python token fetch attempt failed in ChatController: ' . $e->getMessage());
                continue;
            }
        }

        return null;
    }

    private function parseStream(string $body): array
    {
        $conversationId = null;
        $reply = null;

        foreach (preg_split("/\r?\n\r?\n/", trim($body)) ?: [] as $chunk) {
            $event = 'message';
            $dataLines = [];

            foreach (preg_split("/\r?\n/", $chunk) ?: [] as $line) {
                if (str_starts_with($line, 'event:')) {
                    $event = trim(substr($line, 6));
                } elseif (str_starts_with($line, 'data:')) {
                    $dataLines[] = ltrim(substr($line, 5));
                }
            }

            if (!$dataLines) {
                continue;
            }

            $data = json_decode(implode("\n", $dataLines), true);
            if (!is_array($data)) {
                continue;
            }

            if ($event === 'meta') {
                $conversationId = $data['conversation']['id'] ?? $conversationId;
            }

            if ($event === 'done') {
                $reply = $data['assistant_message']['content'] ?? $reply;
            }
        }

        return ['conversation_id' => $conversationId, 'response' => $reply];
    }
}
