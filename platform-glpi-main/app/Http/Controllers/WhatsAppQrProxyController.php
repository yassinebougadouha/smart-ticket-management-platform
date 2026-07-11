<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WhatsAppQrProxyController extends Controller
{
    public function show(Request $request)
    {
        $version = (string) $request->query('_v', '');
        $response = null;

        $bases = array_values(array_filter([
            (string) config('services.whatsapp.internal_bridge_url'),
            (string) config('services.whatsapp.qr_bridge_url', 'http://localhost:8602/qr'),
            'http://whatsapp_bridge:8602/qr',
            'http://support_whatsapp_bridge:8602/qr',
            'http://localhost:8602/qr',
            'http://127.0.0.1:8602/qr',
            'http://host.docker.internal:8602/qr',
        ]));

        foreach ($bases as $base) {
            if (!str_contains($base, '/qr') && !str_ends_with($base, '/qr-proxy')) {
                $base = rtrim($base, '/') . '/qr';
            }

            foreach ($this->qrUrlCandidates($base) as $url) {
                if ($version !== '') {
                    $url .= (str_contains($url, '?') ? '&' : '?') . '_v=' . urlencode($version);
                }

                try {
                    $candidate = Http::timeout(5)->accept('*/*')->get($url);
                    $contentType = strtolower((string) $candidate->header('Content-Type', ''));
                    $body = $candidate->body();
                    $looksLikePng = str_starts_with($body, "\x89PNG\r\n\x1a\n");

                    if ($candidate->successful() && $body !== '' && (
                        str_contains($contentType, 'image/png') ||
                        str_contains($contentType, 'image/') ||
                        $looksLikePng
                    )) {
                        $response = $candidate;
                        break 2;
                    }
                } catch (\Exception $e) {
                    // Suppress exception to continue testing candidates
                }
            }
        }

        if (!$response) {
            return response('WhatsApp Bridge QR Code Unavailable', 503)
                ->header('Content-Type', 'text/plain')
                ->header('Cache-Control', 'no-store');
        }

        return response($response->body(), $response->status())
            ->header('Content-Type', $response->header('Content-Type', 'image/png'))
            ->header('Cache-Control', 'no-store');
    }

    private function qrUrlCandidates(string $base): array
    {
        $base = strtok($base, '?') ?: $base;
        $base = rtrim($base, '/');

        if (str_ends_with($base, '/qr')) {
            $candidates = [$base . '.png', $base];
        } elseif (str_ends_with($base, '/qr.json')) {
            $candidates = [substr($base, 0, -5) . 'png', $base, substr($base, 0, -5)];
        } elseif (!str_ends_with($base, '/qr.png')) {
            $candidates = [$base . '/qr.png', $base . '/qr', $base];
        } else {
            $candidates = [$base];
        }

        return array_values(array_unique($candidates));
    }
}
