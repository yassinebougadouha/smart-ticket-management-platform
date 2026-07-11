<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AiService;

class AiController extends Controller
{
    protected AiService $ai;

    public function __construct(AiService $ai)
    {
        $this->ai = $ai;
    }

    // ═══════════════════════════════════════════════════════════
    // CLIENT
    // ═══════════════════════════════════════════════════════════

    // POST /tickets/classify — classification real-time pendant saisie
    public function classify(Request $request)
    {
        $title       = $request->input('title', '');
        $description = $request->input('description', '');
        $motif       = $request->input('motif', '');

        if (strlen($title) < 5 || !$this->ai->isAvailable()) {
            return response()->json(['available' => false]);
        }

        $result = $this->ai->classify($motif, $title, $description);

        if (!$result) {
            return response()->json(['available' => false]);
        }

        return response()->json([
            'available'      => true,
            'category'       => $result['category']       ?? 'autre',
            'category_label' => $result['category_label'] ?? 'Autre',
            'priority'       => $result['priority']       ?? 3,
            'priority_label' => $result['priority_label'] ?? 'Moyenne',
            'urgency'        => $result['urgency']        ?? 3,
            'confidence'     => $result['confidence']     ?? 0,
            'solutions'      => $result['solutions']      ?? [],
        ]);
    }

    // POST /tickets/reformulate — LLM améliore la description
    public function reformulate(Request $request)
    {
        $title       = $request->input('title', '');
        $description = $request->input('description', '');

        if (!$this->ai->isAvailable()) {
            return response()->json(['available' => false]);
        }

        $improved = $this->ai->reformulate($title, $description);

        return response()->json([
            'available'    => true,
            'reformulated' => $improved ?? $description,
        ]);
    }

    // GET /tickets/similar?q=...&category=... — tickets similaires résolus
    public function similar(Request $request)
    {
        $q        = $request->input('q', '');
        $category = $request->input('category', '');

        if (strlen($q) < 4) {
            return response()->json(['tickets' => []]);
        }

        $tickets = $this->ai->findSimilar($q, $category);

        return response()->json(['tickets' => $tickets]);
    }

    // ═══════════════════════════════════════════════════════════
    // ADMIN
    // ═══════════════════════════════════════════════════════════

    // POST /admin/ai/analyze — résumé + réponse suggérée
    public function analyzeTicket(Request $request)
    {
        $validated = $request->validate([
            'ticket_id' => 'required|integer|exists:tickets,id',
        ]);
        $ticket = Ticket::with('comments.user')->findOrFail($validated['ticket_id']);

        // Commentaires — avec auteur + date pour contexte complet
        $comments = $ticket->comments
            ->sortBy('created_at')
            ->map(fn($c) => '[' . ($c->user->name ?? 'Client') . ' — ' . optional($c->created_at)->format('d/m H:i') . '] ' . ($c->content ?? ''))
            ->toArray();

        // Historique réponses de CET admin (style learning)
        $pastResponses = Ticket::where('solved_by', auth()->id())
            ->whereNotNull('solution')
            ->where('category', $ticket->category)
            ->latest()
            ->limit(3)
            ->pluck('solution')
            ->toArray();

        // Fallback si IA indisponible
        if (!$this->ai->isAvailable()) {
            return response()->json([
                'available' => false,
                'summary'   => $this->fallbackSummary($ticket),
                'response'  => $this->fallbackResponse($ticket),
                'urgency'   => $this->assessUrgency($ticket),
                'tags'      => [],
            ]);
        }

        $result = $this->ai->analyzeForAdmin(
            $ticket->title,
            $ticket->description,
            $ticket->category ?? 'autre',
            $comments,
            $pastResponses,
        );

        if (!$result) {
            return response()->json([
                'available' => false,
                'summary'   => $this->fallbackSummary($ticket),
                'response'  => $this->fallbackResponse($ticket),
                'urgency'   => $this->assessUrgency($ticket),
                'tags'      => [],
            ]);
        }

        $urgency = $this->assessUrgency($ticket);
        $urgency['label'] = $result['urgency_label'] ?? 'Dans les délais';
        $urgency['is_urgent'] = $result['is_urgent'] ?? false;

        return response()->json([
            'available' => true,
            'summary'   => $result['summary'] ?? $this->fallbackSummary($ticket),
            'response'  => $result['response'] ?? $this->fallbackResponse($ticket),
            'urgency'   => $urgency,
            'tags'      => $result['tags'] ?? [],
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // SUPER ADMIN
    // ═══════════════════════════════════════════════════════════

    // GET /super-admin/ai/leaderboard — performance admins
    public function adminLeaderboard()
    {
        $admins = User::where('role', 'admin')
            ->where(function($q) {
                $q->where('is_active', true)
                  ->orWhere('is_active', 1)
                  ->orWhereNull('is_active');
            })
            ->get();

        $adminIds = $admins->pluck('id')->toArray();

        // ── Bulk query: comment counts & ticket ids per admin ──────────────────
        // Tickets each admin commented on (distinct ticket_id per user_id)
        $commentCounts = \App\Models\TicketComment::whereIn('user_id', $adminIds)
            ->selectRaw('user_id, count(distinct ticket_id) as cnt')
            ->groupBy('user_id')
            ->pluck('cnt', 'user_id');

        // Tickets resolved by each admin (solved_by)
        $resolvedBySelf = Ticket::whereIn('solved_by', $adminIds)
            ->whereIn('sync_status', ['resolved', 'closed', 'synced'])
            ->selectRaw('solved_by, count(*) as cnt')
            ->groupBy('solved_by')
            ->pluck('cnt', 'solved_by');

        // Assigned ticket ids per admin
        $assignedTickets = Ticket::whereIn('assigned_to', $adminIds)
            ->selectRaw('assigned_to, id, sync_status')
            ->get()
            ->groupBy('assigned_to');

        // Urgent tickets handled per admin (via comments on high-priority tickets)
        $urgentComments = \App\Models\TicketComment::whereIn('user_id', $adminIds)
            ->whereHas('ticket', fn($q) => $q->where('priority', '>=', 4))
            ->selectRaw('user_id, count(distinct ticket_id) as cnt')
            ->groupBy('user_id')
            ->pluck('cnt', 'user_id');

        // Avg resolution hours per admin
        $resolvedTickets = Ticket::whereIn('solved_by', $adminIds)
            ->whereIn('sync_status', ['resolved', 'closed', 'synced'])
            ->whereNotNull('resolved_at')
            ->selectRaw('solved_by, extract(epoch from (resolved_at - created_at))/3600 as hours')
            ->get()
            ->groupBy('solved_by');

        $result = $admins->map(function ($admin) use (
            $commentCounts, $resolvedBySelf, $assignedTickets,
            $urgentComments, $resolvedTickets
        ) {
            $answered      = (int) ($commentCounts[$admin->id] ?? 0);
            $resolvedSelf  = (int) ($resolvedBySelf[$admin->id] ?? 0);
            $assigned      = $assignedTickets[$admin->id] ?? collect();
            $totalAssigned = $assigned->count();
            $resolvedAssigned = $assigned->whereIn('sync_status', ['resolved', 'closed', 'synced'])->count();
            $pending       = $assigned->whereIn('sync_status', ['pending', 'in_progress'])->count();
            $resolved      = max($resolvedSelf, $resolvedAssigned);
            $total         = max($answered, $totalAssigned);
            $urgentHandled = (int) ($urgentComments[$admin->id] ?? 0);

            // Avg hours from pre-fetched data
            $adminResolved = $resolvedTickets[$admin->id] ?? collect();
            $avgHours      = $adminResolved->count() > 0
                ? round($adminResolved->avg('hours'), 1)
                : null;

            // ── Score (0-100) ──────────────────────────────────────────────────
            $answerPts  = min(35, $answered * 5);
            $resolvedPts = min(30, $resolved * 6);
            $speedPts   = 10;
            if ($avgHours !== null) {
                if ($avgHours <= 2)  $speedPts = 20;
                elseif ($avgHours <= 8)  $speedPts = 15;
                elseif ($avgHours <= 24) $speedPts = 10;
                elseif ($avgHours <= 72) $speedPts = 5;
                else $speedPts = 2;
            }
            $urgentPts  = min(15, $urgentHandled * 5);
            $score      = min(100, (int)($answerPts + $resolvedPts + $speedPts + $urgentPts));

            $daysSinceCreation = $admin->created_at
                ? (int) $admin->created_at->diffInDays(now())
                : 0;

            return [
                'id'             => $admin->id,
                'name'           => $admin->name,
                'email'          => $admin->email,
                'resolved'       => $resolved,
                'answered'       => $answered,
                'total'          => $total,
                'pending'        => $pending,
                'avg_hours'      => $avgHours,
                'urgent_handled' => $urgentHandled,
                'score'          => $score,
                'days_active'    => $daysSinceCreation,
                'suggestion'     => $this->adminSuggestion($score, $answered, $avgHours, $urgentHandled, $total),
            ];
        })
        ->sortByDesc('score')
        ->values();

        return response()->json([
            'admins'         => $result,
            'total_resolved' => Ticket::whereIn('sync_status', ['resolved', 'closed'])->count(),
            'total_pending'  => Ticket::whereIn('sync_status', ['pending', 'in_progress'])->count(),
            'urgent_pending' => Ticket::whereIn('sync_status', ['pending', 'in_progress'])
                                    ->where('priority', '>=', 4)->count(),
        ]);
    }

    // GET /super-admin/ai/urgent-tickets — tickets urgents non résolus
    public function urgentTickets()
    {
        // Show escalated tickets for urgent view
        $tickets = Ticket::with('user')
            ->whereNotIn('sync_status', ['resolved', 'closed', 'synced'])
            ->where(function ($q) {
                // Include escalated tickets
                $q->where('escalation_flag', true)
                  ->orWhere('sla_breached', true)
                  ->orWhere('sync_status', '=', 'escalated')
                  ->orWhere('status', '=', 'escalated');
            })
            ->orderByDesc('priority')
            ->orderBy('created_at')
            ->limit(5)
            ->get()
            ->map(function ($t) {
                $hoursOpen = $t->created_at->diffInHours(now());
                $slaMap = [
                    5 => (int) \App\Models\Setting::get('sla_très haute', '4'),
                    4 => (int) \App\Models\Setting::get('sla_haute',      '8'),
                    3 => (int) \App\Models\Setting::get('sla_moyenne',    '24'),
                    2 => (int) \App\Models\Setting::get('sla_basse',      '48'),
                    1 => (int) \App\Models\Setting::get('sla_basse',      '48'),
                ];
                $slaLimit  = $slaMap[$t->priority] ?? 8;
                return [
                    'id'         => $t->id,
                    'title'      => $t->title,
                    'client'     => $t->user->name ?? 'N/A',
                    'priority'   => $t->priority,
                    'hours_open' => $hoursOpen,
                    'sla_limit'  => $slaLimit,
                    'sla_risk'   => $hoursOpen >= $slaLimit * 0.8,
                    'created_at' => $t->created_at->format('d/m H:i'),
                ];
            });

        return response()->json(['tickets' => $tickets]);
    }

    // GET /super-admin/urgent-tickets — vue blade liste complète
public function urgentTicketsList()
{
    $slaMap = [
        5 => (int) \App\Models\Setting::get('sla_très haute', '4'),
        4 => (int) \App\Models\Setting::get('sla_haute',      '8'),
        3 => (int) \App\Models\Setting::get('sla_moyenne',    '24'),
        2 => (int) \App\Models\Setting::get('sla_basse',      '48'),
        1 => (int) \App\Models\Setting::get('sla_basse',      '48'),
    ];

    // Consider non-closed tickets for the urgent list view as well.
    // Show escalated tickets OR tickets at risk
    $tickets = Ticket::with('user')
        ->whereNotIn('sync_status', ['resolved', 'closed', 'synced'])
        ->where(function ($q) {
            // Show escalated tickets OR tickets flagged for escalation OR SLA breached
            $q->where('escalation_flag', true)
              ->orWhere('sla_breached', true)
              ->orWhere('sync_status', '=', 'escalated')
              ->orWhere('status', '=', 'escalated');
        })
        ->orderByDesc('priority')
        ->orderBy('created_at')
        ->get()
        ->map(function ($t) use ($slaMap) {
            $hoursOpen = (int) round($t->created_at->floatDiffInHours(now()));
            $slaLimit  = $slaMap[$t->priority] ?? 8;
            $slaUsed   = $slaLimit > 0 ? ($hoursOpen / $slaLimit) * 100 : 100;
            $hoursLeft = $slaLimit - $hoursOpen;

            return (object)[
                'id'           => $t->id,
                'title'        => $t->title,
                'client'       => $t->user->name ?? 'N/A',
                'priority'     => $t->priority,
                'hours_open'   => $hoursOpen,
                'sla_limit'    => $slaLimit,
                'sla_used'     => round($slaUsed, 1),
                'sla_risk'     => !($hoursLeft < 0) && $slaUsed >= 80,
                'sla_breached' => $hoursLeft < 0,
                'hours_left'   => $hoursLeft,
                'sla_ratio'    => $slaLimit > 0 ? $hoursOpen / $slaLimit : 999,
                'created_at'   => $t->created_at,
                'status'       => $t->sync_status,
            ];
        })
        ->sortByDesc('sla_ratio')
        ->values();

    $role = auth()->user()->role;
    $view = $role === 'admin' ? 'admin.urgent-tickets' : 'super-admin.urgent-tickets';

    return view($view, compact('tickets'));
}

    // GET /super-admin/ai/weekly-report — rapport IA hebdo
    public function weeklyReport()
    {
        $stats = [
            'total_tickets'   => Ticket::whereBetween('created_at', [now()->subDays(7), now()])->count(),
            'resolved'        => Ticket::whereBetween('created_at', [now()->subDays(7), now()])
                                    ->whereIn('sync_status', ['resolved', 'closed'])->count(),
            'urgent'          => Ticket::whereBetween('created_at', [now()->subDays(7), now()])
                                    ->where('priority', '>=', 4)->count(),
            'by_category'     => Ticket::whereBetween('created_at', [now()->subDays(7), now()])
                                    ->selectRaw('category, count(*) as total')
                                    ->groupBy('category')->pluck('total', 'category'),
            'active_admins'   => User::where('role', 'admin')->where('is_active', true)->count(),
        ];

        $report = $this->ai->isAvailable()
            ? $this->ai->generateWeeklyReport($stats)
            : null;

        return response()->json([
            'stats'  => $stats,
            'report' => $report ?? "Rapport indisponible — service IA non configuré.",
        ]);
    }


    private function fallbackSummary(Ticket $ticket): string
    {
        $cat  = ['incident_technique' => 'Incident technique', 'integration_api' => 'Problème API', 'facturation' => 'Facturation', 'plateforme' => 'Plateforme', 'paiement_mobile' => 'Paiement mobile', 'autre' => 'Demande'][$ticket->category] ?? 'Demande';
        $prio = ['', 'très basse', 'basse', 'moyenne', 'haute', 'critique'][$ticket->priority ?? 3] ?? 'moyenne';
        return "{$cat} — priorité {$prio}. \"" . substr($ticket->title, 0, 80) . "\" — " . $ticket->created_at->diffForHumans() . ".";
    }

    private function fallbackResponse(Ticket $ticket): string
    {
        $responses = [
            'incident_technique' => "Bonjour,\n\nNous avons bien reçu votre signalement. Notre équipe technique prend en charge votre incident immédiatement.\n\nPouvez-vous nous préciser depuis quand le problème est apparu et si vous observez un message d'erreur spécifique ?\n\nCordialement,\nL'équipe Support L2T",
            'integration_api'    => "Bonjour,\n\nMerci pour votre message. Pour résoudre ce problème, veuillez vérifier votre token API dans votre espace client et consulter notre documentation.\n\nN'hésitez pas à nous partager le message d'erreur exact.\n\nCordialement,\nL'équipe Support L2T",
            'facturation'        => "Bonjour,\n\nVotre demande a été transmise à notre service comptabilité. Vous recevrez une réponse dans les 24h ouvrées.\n\nCordialement,\nL'équipe Support L2T",
            'plateforme'         => "Bonjour,\n\nMerci de nous avoir signalé ce problème. Essayez de vider le cache de votre navigateur et de vous reconnecter. Si le problème persiste, notre équipe prend en charge votre demande.\n\nCordialement,\nL'équipe Support L2T",
        ];
        return $responses[$ticket->category] ?? "Bonjour,\n\nNous avons bien reçu votre demande et notre équipe vous répondra dans les meilleurs délais.\n\nCordialement,\nL'équipe Support L2T";
    }

    private function assessUrgency(Ticket $ticket): array
    {
        $hoursOpen = $ticket->created_at->diffInHours(now());
        $slaLimit  = [
            5 => (int) \App\Models\Setting::get('sla_très haute', '4'),
            4 => (int) \App\Models\Setting::get('sla_haute',      '8'),
            3 => (int) \App\Models\Setting::get('sla_moyenne',    '24'),
            2 => (int) \App\Models\Setting::get('sla_basse',      '48'),
            1 => (int) \App\Models\Setting::get('sla_basse',      '48'),
        ][$ticket->priority ?? 3] ?? 24;
        $slaUsed   = min(100, (int)(($hoursOpen / $slaLimit) * 100));
        return [
            'is_urgent'  => ($ticket->priority ?? 3) >= 4 || $slaUsed >= 80,
            'hours_open' => $hoursOpen,
            'sla_limit'  => $slaLimit,
            'sla_used'   => $slaUsed,
        ];
    }

    private function adminSuggestion(int $score, int $answered, ?float $avgHours, int $urgentHandled, int $total = 0): string
    {
        if ($total === 0) return "Aucun ticket assigné pour le moment.";
        if ($score >= 80) return "Excellente performance — top performer de l'équipe. 🏆";
        if ($score >= 60) return "Bonne performance — continuer sur cette lancée !";
        if ($answered === 0) return "A reçu des tickets mais n'a pas encore répondu.";
        if ($avgHours !== null && $avgHours > 48) return "Délai moyen élevé (" . round($avgHours) . "h) — prioriser les tickets urgents.";
        if ($urgentHandled === 0 && $total > 2) return "N'a pas traité de tickets urgents — les inclure en priorité.";
        if ($total > 0 && $answered > 0) return "A traité {$answered}/{$total} tickets — bonne réactivité.";
        return "Performance en cours d'évaluation — données insuffisantes.";
    }
}