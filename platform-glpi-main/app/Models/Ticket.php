<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'title', 'description', 'status',
        'urgency', 'impact', 'priority',
        'category', 'solution', 'attachments',
        'glpi_ticket_id', 'sync_status', 'last_error',
        'solved_by', 'source',
        'assigned_to',
        'resolved_at',
        // Champs db2
        'channel_source',
        'conversation_id',
        'source_email_id',
        'source_voice_call_id',
        'escalation_flag',
        'is_deleted',
        'glpi_sync_status',
        'glpi_sync_error',
        'resolution_note',
        'sla_breached',
        'sla_due_at',
    ];

    protected $casts = [
        'resolved_at'     => 'datetime',
        'sla_due_at'      => 'datetime',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
        'escalation_flag' => 'boolean',
        'is_deleted'      => 'boolean',
    ];

    protected function normalizeDateTime(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception) {
            return null;
        }
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function solver()
    {
        return $this->belongsTo(User::class, 'solved_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function events()
    {
        return $this->hasMany(TicketEvent::class);
    }

    public function comments()
    {
        return $this->hasMany(TicketComment::class)->latest();
    }

    public function isEscalated(): bool
    {
        if ((bool) $this->escalation_flag) {
            return true;
        }

        if (in_array((string) $this->sync_status, ['escalated', 'escalation'], true)) {
            return true;
        }

        if (in_array((string) $this->status, ['escalated', 'escalation'], true)) {
            return true;
        }

        if ((bool) $this->sla_breached) {
            return true;
        }

        $slaDueAt = $this->normalizeDateTime($this->sla_due_at);
        if ($slaDueAt && $slaDueAt->isPast()) {
            return true;
        }

        return false;
    }

    public function isUrgent(): bool
    {
        if ($this->isEscalated()) {
            return false;
        }

        $priority = (int) ($this->priority ?? 3);
        if ($priority >= 4) {
            return true;
        }

        $createdAt = $this->normalizeDateTime($this->created_at);
        if ($createdAt && $createdAt->lte(now()->subHours(20))) {
            return $priority >= 3;
        }

        return false;
    }

    public function canEdit(): bool
    {
        return $this->sync_status === 'pending' && $this->user_id === auth()->id();
    }

    public function canDelete(): bool
    {
        return $this->sync_status === 'pending' && $this->user_id === auth()->id();
    }
}
