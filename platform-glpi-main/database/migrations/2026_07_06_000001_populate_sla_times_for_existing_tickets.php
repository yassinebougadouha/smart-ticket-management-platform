<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Populate sla_due_at for existing tickets ──────────────────────
        // This migration ensures all existing tickets have SLA times calculated
        // based on their priority and creation date
        
        $slaMap = [
            5 => 2,   // CRITICAL: 2 hours
            4 => 4,   // HIGH: 4 hours
            3 => 8,   // MEDIUM: 8 hours
            2 => 24,  // LOW: 24 hours
            1 => 24,  // VERY LOW: 24 hours
        ];

        // Get all tickets without sla_due_at
        $tickets = DB::table('tickets')
            ->whereNull('sla_due_at')
            ->where('sync_status', '!=', 'resolved')
            ->where('sync_status', '!=', 'closed')
            ->where('sync_status', '!=', 'synced')
            ->get();

        foreach ($tickets as $ticket) {
            $priority = $ticket->priority ?? 1;
            $hours = $slaMap[$priority] ?? 8;
            $slaTime = Carbon::parse($ticket->created_at)->addHours($hours);

            DB::table('tickets')
                ->where('id', $ticket->id)
                ->update([
                    'sla_due_at' => $slaTime,
                    'updated_at' => now(),
                ]);
        }

        echo "Populated SLA times for " . count($tickets) . " tickets\n";
    }

    public function down(): void
    {
        // Revert by clearing sla_due_at for non-critical tickets
        DB::table('tickets')
            ->where('sync_status', '!=', 'resolved')
            ->where('sync_status', '!=', 'closed')
            ->update(['sla_due_at' => null]);
    }
};
