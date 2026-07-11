<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\AuditLog;

class CleanupWhatsappUsers extends Command
{
    protected $signature = 'users:cleanup-whatsapp {--force : Execute without confirmation} {--dry-run : Show what would be removed without deleting}';
    protected $description = 'Remove platform users that represent WhatsApp/system identities (emails like @whatsapp.local, wa_*, noreply, etc.)';

    public function handle(): int
    {
        $this->info('Scanning for WhatsApp/system client accounts...');

        $query = User::where('role', 'client')
            ->where(function ($q) {
                $q->where('email', 'like', '%@whatsapp.local')
                  ->orWhere('email', 'like', 'wa_%')
                  ->orWhere('email', 'ilike', '%noreply%')
                  ->orWhere('email', 'ilike', '%no-reply%')
                  ->orWhere('name',  'ilike', '%whatsapp%');
            });

        $count = $query->count();
        if ($count === 0) {
            $this->info('No matching users found.');
            return 0;
        }

        $this->line("Found {$count} candidate user(s).");

        $users = $query->get();

        foreach ($users as $u) {
            $this->line(" - {$u->id} | {$u->name} <{$u->email}>  tickets={$u->tickets()->count()} ");
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run complete — no changes were made.');
            return 0;
        }

        if (!$this->option('force')) {
            if (!$this->confirm('Proceed to delete these users and their conversations/tickets?')) {
                $this->info('Aborted by user.');
                return 1;
            }
        }

        foreach ($users as $user) {
            DB::beginTransaction();
            try {
                // delete conversations referencing this user (DB table uses uuid user_id)
                DB::table('conversations')->where('user_id', $user->id)->delete();

                // delete tickets
                $user->tickets()->delete();

                AuditLog::log('DELETE', 'Users', "Cleanup: removing WhatsApp/system user {$user->name} ({$user->email})");

                $user->delete();

                DB::commit();
                $this->info("Deleted {$user->email} ({$user->id})");
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error('[CleanupWhatsappUsers] Failed deleting user ' . $user->id . ': ' . $e->getMessage());
                $this->error('Failed removing ' . $user->email . ': ' . $e->getMessage());
            }
        }

        $this->info('Cleanup finished.');
        return 0;
    }
}
