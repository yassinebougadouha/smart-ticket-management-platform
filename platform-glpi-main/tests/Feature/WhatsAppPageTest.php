<?php

use App\Models\User;

test('whatsapp page uses the laravel qr proxy for the client image', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this
        ->actingAs($user)
        ->get('/admin/whatsapp');

    $response->assertOk();

    $html = $response->getContent();
    $this->assertStringContainsString("const QR_PROXY_URL =", $html);
    $this->assertStringContainsString("buildQrPngUrl(QR_PROXY_URL || QR_BRIDGE_URL, qrVersion)", $html);
});
