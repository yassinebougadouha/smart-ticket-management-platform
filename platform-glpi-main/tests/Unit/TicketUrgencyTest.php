<?php

use App\Models\Ticket;

test('sla breached tickets are treated as escalations and not urgent', function () {
    $ticket = new Ticket();
    $ticket->forceFill([
        'priority' => 3,
        'created_at' => now()->subHours(25),
        'sla_breached' => true,
        'escalation_flag' => false,
        'sync_status' => 'pending',
        'status' => 'open',
    ]);

    expect($ticket->isEscalated())->toBeTrue()
        ->and($ticket->isUrgent())->toBeFalse();
});

test('old medium priority tickets remain urgent until escalated', function () {
    $ticket = new Ticket();
    $ticket->forceFill([
        'priority' => 3,
        'created_at' => now()->subHours(25),
        'sla_breached' => false,
        'escalation_flag' => false,
        'sync_status' => 'pending',
        'status' => 'open',
    ]);

    expect($ticket->isUrgent())->toBeTrue();
});
