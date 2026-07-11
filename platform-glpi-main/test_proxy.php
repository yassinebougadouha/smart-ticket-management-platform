<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$request = Illuminate\Http\Request::create('/', 'GET');
$app->instance('request', $request);

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$user = \App\Models\User::find(1);
if (!$user) {
    echo "No user found with ID 1\n";
    exit(1);
}
auth()->login($user);

// Fetch python token
$pythonRole = 'ADMIN';
$url = 'http://support_api:8600/api/v1/internal';
$sync = Http::timeout(5)
    ->withHeaders(['X-Service-Key' => 'change-me-internal-key'])
    ->post($url . '/sync-laravel-user', [
        'laravel_user_id' => $user->id,
        'email'           => $user->email,
        'role'            => $pythonRole,
    ]);

$tokenResp = Http::timeout(5)
    ->withHeaders(['X-Service-Key' => 'change-me-internal-key'])
    ->post($url . '/laravel-token', ['laravel_user_id' => $user->id]);

if (!$tokenResp->successful()) {
    echo "Token fetch failed: " . $tokenResp->body() . "\n";
    exit(1);
}
$token = $tokenResp->json('access_token');

// Make direct request with token
$response = Http::timeout(30)
    ->withHeaders(['Authorization' => 'Bearer ' . $token])
    ->asMultipart()
    ->attach('file', file_get_contents(__DIR__.'/public/assets/img/favicon.png'), 'favicon.png')
    ->post('http://support_api:8600/api/v1/visual-ai/analyze-raw');

echo "Direct HTTP Client Status: " . $response->status() . "\n";
echo "Direct HTTP Client Body: " . $response->body() . "\n";
