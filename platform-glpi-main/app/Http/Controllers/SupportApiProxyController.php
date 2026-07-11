<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SupportApiProxyController extends Controller
{
    public function __invoke(Request $request, string $path = '')
    {
        $timeout = (int) config('services.support_api.timeout', 30);
        $headers = $this->forwardHeaders($request);

        if (empty($headers['Authorization']) && auth()->check()) {
            $user = auth()->user();

            // TTL-based token cache: reuse the session token if it's < 13 min old
            // (JWT expires in 15 min). Otherwise fetch a fresh one.
            $cachedToken = session('python_token');
            $cachedAt    = (int) session('python_token_at', 0);
            $ttl         = 13 * 60;

            if ($cachedToken && (time() - $cachedAt) < $ttl) {
                $headers['Authorization'] = 'Bearer ' . $cachedToken;
            } else {
                $token = $this->fetchAndStorePythonToken($user);
                if ($token) {
                    $headers['Authorization'] = 'Bearer ' . $token;
                }
            }
        }

        $configuredToken = config('services.support_api.bearer_token');
        if ($configuredToken && empty($headers['Authorization'])) {
            $headers['Authorization'] = 'Bearer ' . $configuredToken;
        }

        if (auth()->check()) {
            $headers['X-Laravel-User-Id'] = (string) auth()->id();
        }

        $lastException = null;
        $lastResponse = null;
        foreach ($this->supportApiBaseUrlCandidates() as $baseUrl) {
            $target = $this->supportApiUrl($baseUrl, $path, $request->getQueryString());

            try {
                $response = $this->forwardRequest($request, $target, $headers, $timeout);

                if (in_array($response->status(), [502, 503, 504], true)) {
                    $lastResponse = $response;
                    continue;
                }

                if ($response->status() === 422) {
                    \Log::error('FastAPI 422 Response: ' . $response->body());
                }

                return response($response->body(), $response->status())
                    ->withHeaders($this->responseHeaders($response->headers()));
            } catch (ConnectionException $e) {
                $lastException = $e;
                continue;
            }
        }

        $message = 'Service non disponible. Veuillez rÃƒÂ©essayer plus tard.';
        if ($lastResponse) {
            return response($lastResponse->body(), $lastResponse->status())
                ->withHeaders($this->responseHeaders($lastResponse->headers()));
        }

        return response()->json([
            'error' => $message,
            'message' => $message,
            'detail' => $lastException?->getMessage(),
        ], 503);
    }

    private function forwardRequest(Request $request, string $target, array $headers, int $timeout)
    {
        if ($request->files->count() > 0) {
            $headers = array_diff_key($headers, ['Content-Type' => true]);
            $client = Http::timeout($timeout)->withHeaders($headers)->asMultipart();
            foreach ($this->multipartFields($request) as $field) {
                $client = $client->attach($field['name'], $field['contents']);
            }
            foreach ($this->multipartFiles($request->allFiles()) as $file) {
                $contents = $file['contents'];
                if ($contents === false || $contents === null) {
                    continue;
                }
                $client = $client->attach(
                    $file['name'],
                    $contents,
                    $file['filename'],
                    ['Content-Type' => $file['mime']]
                );
            }
            return $client->send($request->method(), $target);
        }

        $method = strtoupper($request->method());
        $client = Http::timeout($timeout)->withHeaders($headers);

        // FastAPI endpoints behind the support bridge are sensitive to GET
        // requests that carry an empty JSON body. Send GET/HEAD requests without
        // a body so status/inbox polling stays stable and does not trip the proxy.
        if (in_array($method, ['GET', 'HEAD'], true)) {
            return $client->send($method, $target);
        }

        $content = $request->getContent();
        if ($content === '' || $content === null) {
            return $client->send($method, $target);
        }

        $contentType = (string) $request->header('Content-Type', 'application/json');
        return $client
            ->withBody($content, $contentType)
            ->send($method, $target);
    }

    private function forwardHeaders(Request $request): array
    {
        $headers = [];
        foreach (['Accept', 'Content-Type', 'Authorization'] as $name) {
            $value = $request->header($name);
            if ($value !== null) {
                $headers[$name] = $value;
            }
        }

        foreach (['X-Request-ID', 'X-Trace-ID'] as $name) {
            $value = $request->header($name);
            if ($value !== null) {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    private function responseHeaders(array $headers): array
    {
        $safe = [];
        foreach ($headers as $name => $values) {
            $lower = Str::lower($name);
            if (in_array($lower, ['transfer-encoding', 'connection', 'content-length'], true)) {
                continue;
            }
            if (in_array($lower, ['content-type', 'content-disposition', 'cache-control'], true)) {
                $safe[$name] = is_array($values) ? implode(', ', $values) : (string) $values;
            }
        }
        return $safe;
    }

    private function multipartFields(Request $request): array
    {
        $fields = [];
        foreach ($request->except(array_keys($request->allFiles())) as $name => $value) {
            foreach ((array) $value as $item) {
                $fields[] = ['name' => $name, 'contents' => (string) $item];
            }
        }
        return $fields;
    }

    private function multipartFiles(array $files, string $prefix = ''): array
    {
        $out = [];
        foreach ($files as $name => $file) {
            $field = $prefix ?: (string) $name;
            if (is_array($file)) {
                foreach ($file as $child) {
                    $out = array_merge($out, $this->multipartFiles([(string) $name => $child], $field));
                }
                continue;
            }

            // Prefer the client-declared MIME type to avoid PHP finfo misdetecting
            // audio/webm files as video/webm (a common issue with webm containers).
            $mime = $file->getClientMimeType()
                ?: $file->getMimeType()
                ?: 'application/octet-stream';
            // Normalise codec suffix so FastAPI sees a clean type (e.g. audio/webm)
            $mime = explode(';', $mime)[0];

            // Read the file contents directly — getRealPath() on a moved/temp file
            // can point to an invalid location, causing fopen() to fail silently.
            $path = $file->getRealPath();
            $contents = $path !== false ? @file_get_contents($path) : false;

            $out[] = [
                'name'     => $field,
                'contents' => $contents,
                'filename' => $file->getClientOriginalName(),
                'mime'     => $mime,
            ];
        }
        return $out;
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

    private function supportApiUrl(string $baseUrl, string $path, ?string $queryString = null): string
    {
        $target = rtrim($baseUrl, '/')
            . '/'
            . trim((string) config('services.support_api.prefix', '/api/v1'), '/')
            . '/'
            . ltrim($path, '/');

        if ($queryString) {
            $target .= '?' . $queryString;
        }

        return $target;
    }

    private function fetchAndStorePythonToken($user): ?string
    {
        try {
            $pythonRole = match ($user->role) {
                'super_admin' => 'ADMIN',
                'admin'       => 'AGENT',
                default       => 'CLIENT',
            };

            foreach ($this->supportApiBaseUrlCandidates() as $baseUrl) {
                $url = $this->supportApiUrl($baseUrl, '/internal');

                Http::timeout(5)
                    ->withHeaders(['X-Service-Key' => 'change-me-internal-key'])
                    ->post($url . '/sync-laravel-user', [
                        'laravel_user_id' => $user->id,
                        'email'           => $user->email,
                        'role'            => $pythonRole,
                    ]);

                $response = Http::timeout(5)
                    ->withHeaders(['X-Service-Key' => 'change-me-internal-key'])
                    ->post(
                        $url . '/laravel-token',
                        ['laravel_user_id' => $user->id]
                    );

                if ($response->successful()) {
                    $token = $response->json('access_token');
                    session([
                        'python_token'    => $token,
                        'python_token_at' => time(),
                    ]);
                    return $token;
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Dynamic Python token fetch failed in SupportApiProxyController: ' . $e->getMessage());
        }

        return null;
    }
}
