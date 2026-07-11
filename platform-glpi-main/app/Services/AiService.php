<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Setting;
use App\Models\Ticket;

class AiService
{
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        // Attempt to get AI configuration from Settings; fallback to environment variables if not set.
        $this->apiKey  = Setting::get('openai_api_key')
            ?: Setting::get('ai_api_key')
            ?: env('AI_API_KEY', config('services.ai.key', ''));
        $this->model   = Setting::get('openai_model')
            ?: env('AI_MODEL', config('services.ai.model', 'llama-3.3-70b-versatile'));
        $this->baseUrl = Setting::get('ai_base_url')
            ?: env('AI_BASE_URL', config('services.ai.base_url', 'https://api.groq.com/openai/v1'));
        $this->timeout = (int) (
            Setting::get('ai_timeout')
            ?: env('AI_TIMEOUT', config('services.ai.timeout', 15))
        );
    }

    // ─── Appel LLM central ────────────────────────────────────────────────────
    private function chat(string $systemPrompt, string $userMessage, int $maxTokens = 500): ?string
    {
        if (empty($this->apiKey)) {
            Log::warning('AI: No API key configured');
            return null;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ])
                ->post($this->baseUrl . '/chat/completions', [
                    'model'       => $this->model,
                    'max_tokens'  => $maxTokens,
                    'temperature' => 0.1,
                    'messages'    => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user',   'content' => $userMessage],
                    ],
                ]);

            if ($response->successful()) {
                return $response->json('choices.0.message.content');
            }

            Log::warning('AI API error: ' . $response->status() . ' — ' . $response->body());
            return null;

        } catch (\Exception $e) {
            Log::warning('AI unavailable: ' . $e->getMessage());
            return null;
        }
    }

    // ─── Vérifier disponibilité ───────────────────────────────────────────────
    public function isAvailable(): bool
    {
        // Consider the service available if an API key is present either from settings or environment.
        return !empty($this->apiKey);
    }

    // ─── 1. Classification ticket (client — formulaire) ───────────────────────
    public function classify(string $motif, string $title, string $description = ''): ?array
    {
        // Detect input language to respond in the same language
        $inputLang = $this->detectLanguage($title . ' ' . $description);
        $langInstruction = $inputLang === 'en'
            ? 'IMPORTANT: The user wrote in English. Write ALL solutions in English.'
            : 'IMPORTANT: L\'utilisateur a écrit en français. Écris TOUTES les solutions en français.';

        $system = <<<PROMPT
You are an expert IT support AI for L2T (Landolsi Telecom Technology), a Tunisian SMS marketing company.
Analyze the support ticket below using ALL available information and return ONLY a valid JSON object — no explanation, no markdown, just raw JSON.

{$langInstruction}

Available categories:
- incident_technique: outages, server errors, service down, timeout, undelivered SMS
- integration_api: API issues, token, authentication, endpoints, documentation
- facturation: invoices, SMS credits, recharge, quotes, refunds
- plateforme: platform login, campaigns, Didon SMS, Cloud Messaging
- paiement_mobile: micropayment, transactions, mobile billing
- autre: general questions, partnership, demo

Priorities: 1=very low, 2=low, 3=medium, 4=high, 5=critical

Analyze the ticket using ALL available information:
1. Motif (the category the user selected — this is important and MUST influence your classification and solutions)
2. Title
3. Description

TEST TICKET RULE:
If the ticket is clearly a test, demonstration, training or validation (e.g. title contains "test", "essai", "demo"),
DO NOT invent technical solutions.
Instead return solutions like:
- Validate that the ticket was created successfully.
- Verify that notifications are sent correctly.
- Close the ticket after testing.
And set priority to 1 and confidence to 95.

SOLUTION RULES — follow strictly:
1. Generate ONLY solutions that directly solve THIS specific ticket.
2. Each solution must be a precise, actionable step — mention exact error codes, API endpoints, settings, or commands when relevant.
3. NEVER write vague advice like "check your settings", "contact support", or "provide more details".
4. Solutions must be SHORT (max 20 words each) and start with an action verb.
5. Every solution must be derived from the motif, title, and description combined.
6. Never reuse generic solutions — each solution must match the exact problem.

For example:
- If the ticket is about login → solutions must be about login.
- If the ticket is about API → solutions must be about API endpoints, tokens, or calls.
- If the ticket is about SMS → solutions must be about SMS delivery or routing.
- If the ticket is about invoices → solutions must be about billing or credits.
- If the ticket is only a test → generate testing validation steps instead of fixes.

Expected JSON format (return this exactly):
{"category":"...","category_label":"...","priority":3,"priority_label":"...","urgency":3,"impact":3,"confidence":85,"solutions":["Precise fix 1","Precise fix 2"]}
PROMPT;

        $user = <<<TEXT
Ticket Motif: {$motif}

Ticket Title:
{$title}

Ticket Description:
{$description}
TEXT;

        $raw = $this->chat($system, $user, 700);
        if (!$raw) {
            return $this->localClassify($motif, $title, $description);
        }

        // Extraire le JSON de la réponse
        preg_match('/\{.*\}/s', $raw, $matches);
        if (empty($matches[0])) {
            return $this->localClassify($motif, $title, $description);
        }

        try {
            $result = json_decode($matches[0], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->localClassify($motif, $title, $description);
            }
            return $result;
        } catch (\Exception $e) {
            return $this->localClassify($motif, $title, $description);
        }
    }

    public function localClassify(string $motif, string $title, string $description = ''): ?array
    {
        $text = mb_strtolower(trim($motif . ' ' . $title . ' ' . $description));

        // ── Detect test ticket first ─────────────────────────────────────────────
        if (preg_match('/\b(test|essai|demo|démonstration|validation|exemple|sample|trial)\b/i', $text)) {
            $isFrench = $this->detectLanguage($title . ' ' . $description) !== 'en';
            return [
                'category'       => 'autre',
                'category_label' => 'Test / Validation',
                'priority'       => 1,
                'priority_label' => $isFrench ? 'Très basse' : 'Very Low',
                'urgency'        => 1,
                'confidence'     => 95,
                'solutions'      => $isFrench
                    ? [
                        'Vérifiez que le ticket a été créé avec succès.',
                        'Contrôlez le bon fonctionnement des notifications.',
                        'Fermez le ticket après validation.',
                    ]
                    : [
                        'Validate that the ticket was created successfully.',
                        'Verify that notifications are sent correctly.',
                        'Close the ticket after testing.',
                    ],
            ];
        }

        // ── Category mapping — motif first, then full text ───────────────────────
        $map = [
            'incident_technique' => ['incident', 'timeout', 'erreur', 'panne', 'serveur', 'down', 'connexion', 'sms non délivrés', 'service indisponible', 'outage', 'error', 'crash'],
            'integration_api'    => ['api', 'token', 'authentification', 'endpoint', 'clé api', 'webhook', 'json', 'paramètre', 'intégration', 'callback', 'authentication', 'key'],
            'facturation'        => ['facture', 'paiement', 'devis', 'crédit', 'remboursement', 'tarif', 'prix', 'montant', 'invoice', 'billing', 'credit', 'refund'],
            'plateforme'         => ['plateforme', 'login', 'didon', 'cloud', 'tableau de bord', 'campagne', 'interface', 'dashboard', 'campaign', 'platform'],
            'paiement_mobile'    => ['mobile', 'paiement mobile', 'm-pesa', 'transaction', 'débit mobile', 'opérateur', 'sms premium', 'micropayment'],
        ];

        $motifLower = mb_strtolower(trim($motif));
        $category   = 'autre';

        // Give priority to motif match
        foreach ($map as $key => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_strpos($motifLower, $keyword) !== false) {
                    $category = $key;
                    break 2;
                }
            }
        }
        // Fallback: scan full text
        if ($category === 'autre') {
            foreach ($map as $key => $keywords) {
                foreach ($keywords as $keyword) {
                    if (mb_strpos($text, $keyword) !== false) {
                        $category = $key;
                        break 2;
                    }
                }
            }
        }

        // Detect language from input
        $inputLang = $this->detectLanguage($title . ' ' . $description);
        $isFrench  = ($inputLang !== 'en');

        $priority = 3;
        if (preg_match('/\b(critique|urgent|immédiat|prioritaire|asap|immédiatement|critical|immediately|emergency)\b/i', $text)) {
            $priority = 5;
        } elseif (preg_match('/\b(grave|important|bloquant|erreur 500|erreur 503|timeout|interruption|blocking|broken|down)\b/i', $text)) {
            $priority = max($priority, 4);
        } elseif (preg_match('/\b(problème|bug|ne fonctionne pas|impossible|défaillance|not working|cannot|issue|error)\b/i', $text)) {
            $priority = max($priority, 4);
        }

        $labels = [
            'incident_technique' => $isFrench ? 'Incident technique' : 'Technical Incident',
            'integration_api'    => $isFrench ? 'Intégration API'    : 'API Integration',
            'facturation'        => $isFrench ? 'Facturation'        : 'Billing',
            'plateforme'         => $isFrench ? 'Plateforme'         : 'Platform',
            'paiement_mobile'    => $isFrench ? 'Paiement mobile'    : 'Mobile Payment',
            'autre'              => $isFrench ? 'Autre'              : 'Other',
        ];

        $solutionsFr = [
            'incident_technique' => [
                'Vérifiez les logs du service et redémarrez le processus concerné.',
                'Testez l\'envoi SMS via l\'API et comparez les codes d\'erreur retournés.',
                'Contrôlez l\'état des serveurs dans le tableau de bord infrastructure.',
            ],
            'integration_api' => [
                'Régénérez votre clé API dans Tableau de bord > API Keys > Révoquer & Créer.',
                'Vérifiez que votre IP est autorisée sous Paramètres > Sécurité > IPs autorisées.',
                'Testez l\'endpoint avec : curl -X POST https://api.l2t.tn/send -H \'Authorization: Bearer TOKEN\'.',
            ],
            'facturation' => [
                'Vérifiez le solde SMS dans Tableau de bord > Crédits > Historique.',
                'Téléchargez la facture PDF depuis Facturation > Mes factures > Exporter.',
                'Contactez la comptabilité en joignant le numéro de commande concerné.',
            ],
            'plateforme' => [
                'Videz le cache navigateur (Ctrl+Shift+R) et reconnectez-vous.',
                'Vérifiez vos droits d\'accès dans Admin > Utilisateurs > Permissions.',
                'Si le problème persiste, essayez un autre navigateur ou mode privé.',
            ],
            'paiement_mobile' => [
                'Vérifiez l\'état de la transaction dans Paiements > Historique des transactions.',
                'Confirmez que le numéro mobile est actif et autorisé pour le micropaiement.',
                'Contactez l\'opérateur mobile avec le code de transaction pour investigation.',
            ],
            'autre' => [
                'Précisez l\'objet exact de votre demande pour un traitement rapide.',
                'Joignez tout fichier ou capture d\'écran illustrant le problème.',
            ],
        ];

        $solutionsEn = [
            'incident_technique' => [
                'Check service logs and restart the affected process.',
                'Test SMS sending via API and compare returned error codes.',
                'Monitor server status in the infrastructure dashboard.',
            ],
            'integration_api' => [
                'Regenerate your API key in Dashboard > API Keys > Revoke & Create New.',
                'Check if your IP is whitelisted under Settings > Security > Allowed IPs.',
                'Test the endpoint: curl -X POST https://api.l2t.tn/send -H \'Authorization: Bearer TOKEN\'.',
            ],
            'facturation' => [
                'Check SMS credit balance in Dashboard > Credits > History.',
                'Download the PDF invoice from Billing > My Invoices > Export.',
                'Contact accounting with the relevant order number.',
            ],
            'plateforme' => [
                'Clear browser cache (Ctrl+Shift+R) and log in again.',
                'Check your access rights in Admin > Users > Permissions.',
                'If the issue persists, try a different browser or private mode.',
            ],
            'paiement_mobile' => [
                'Check transaction status in Payments > Transaction History.',
                'Confirm the mobile number is active and enabled for micropayment.',
                'Contact the mobile operator with the transaction code for investigation.',
            ],
            'autre' => [
                'Specify the exact subject of your request for faster processing.',
                'Attach any file or screenshot illustrating the issue.',
            ],
        ];

        $solutions = $isFrench ? $solutionsFr : $solutionsEn;

        return [
            'category'       => $category,
            'category_label' => $labels[$category] ?? ($isFrench ? 'Autre' : 'Other'),
            'priority'       => $priority,
            'priority_label' => $isFrench
                ? ['1' => 'Très basse', '2' => 'Basse', '3' => 'Moyenne', '4' => 'Haute', '5' => 'Critique'][$priority] ?? 'Moyenne'
                : ['1' => 'Very Low',   '2' => 'Low',   '3' => 'Medium',  '4' => 'High',  '5' => 'Critical'][$priority] ?? 'Medium',
            'urgency'        => $priority,
            'confidence'     => 60,
            'solutions'      => $solutions[$category] ?? [],
        ];
    }

    // ─── Detect language (simple heuristic) ──────────────────────────────────
    private function detectLanguage(string $text): string
    {
        $text = mb_strtolower($text);
        // English strong signals
        $enWords = ['the', 'this', 'our', 'your', 'please', 'send', 'us', 'information', 'about', 'can', 'you', 'have', 'how', 'get', 'with', 'for', 'and', 'are', 'not', 'using', 'need', 'want'];
        // French strong signals
        $frWords = ['je', 'nous', 'vous', 'le', 'la', 'les', 'un', 'une', 'des', 'est', 'pas', 'que', 'qui', 'avec', 'sur', 'dans', 'pour', 'cette', 'notre'];

        $enCount = 0;
        $frCount = 0;
        foreach ($enWords as $w) {
            if (preg_match('/\b' . preg_quote($w, '/') . '\b/u', $text)) $enCount++;
        }
        foreach ($frWords as $w) {
            if (preg_match('/\b' . preg_quote($w, '/') . '\b/u', $text)) $frCount++;
        }

        return $enCount > $frCount ? 'en' : 'fr';
    }

    // ─── 2. Analyse ticket pour admin (résumé + réponse suggérée) ─────────────
    public function analyzeForAdmin(string $title, string $description, string $category, array $comments = [], array $pastResponses = []): ?array
    {
        $commentsText = '';
        foreach ($comments as $c) {
            $commentsText .= "- {$c}\n";
        }

        $styleText = '';
        if (!empty($pastResponses)) {
            $styleText = "\nExemples de réponses précédentes de cet admin (adapter le ton):\n";
            foreach (array_slice($pastResponses, 0, 2) as $r) {
                $styleText .= "---\n" . substr($r, 0, 150) . "\n";
            }
        }

        $system = <<<PROMPT
Tu es un assistant IA pour les admins support de L2T (société SMS Tunisie).
Tu analyses les tickets ET tous les commentaires du client pour répondre au problème LE PLUS RÉCENT.
Si le client a ajouté des commentaires, ta réponse doit tenir compte de ces informations supplémentaires.
Réponds UNIQUEMENT en JSON valide sans explication.

Format JSON attendu:
{
  "summary": "résumé du problème actuel en 1-2 phrases (inclure les infos des commentaires)",
  "response": "réponse complète professionnelle qui adresse TOUS les éléments du ticket ET des commentaires",
  "urgency_label": "Dans les délais | Réponse urgente requise",
  "is_urgent": false,
  "tags": ["TAG1","TAG2"]
}

Tags possibles: URGENT, API, FACTURATION, TECHNIQUE, PLATEFORME, PAIEMENT
PROMPT;

        $user = "Ticket:\nTitre: {$title}\nDescription initiale: {$description}\nCatégorie: {$category}";
        if ($commentsText) $user .= "\n\n=== NOUVEAUX MESSAGES DU CLIENT (à traiter en priorité) ===\n{$commentsText}=== FIN MESSAGES ===";
        if ($styleText)    $user .= $styleText;

        $raw = $this->chat($system, $user, 600);
        if (!$raw) return null;

        preg_match('/\{.*\}/s', $raw, $matches);
        if (empty($matches[0])) return null;

        try {
            return json_decode($matches[0], true);
        } catch (\Exception $e) {
            return null;
        }
    }

    // ─── 3. Reformulation description client ──────────────────────────────────
    public function reformulate(string $title, string $description): ?string
    {
        $inputLang = $this->detectLanguage($title . ' ' . $description);
        $langHint  = $inputLang === 'en'
            ? 'The user wrote in English. Keep your reformulation in English.'
            : 'L\'utilisateur a écrit en français. Reformulez en français.';

        $system = "You are a support ticket assistant for L2T (Tunisian SMS company). "
                . "Improve the description while keeping the exact same meaning and language. "
                . $langHint . " Reply with the improved description only, no introduction.";

        $user = "Title: {$title}\nOriginal description: {$description}";

        $improved = $this->chat($system, $user, 300);
        if (!$improved) {
            return $this->localReformulate($description);
        }
        return $improved;
    }

    public function localReformulate(string $description): ?string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', $description));
        if (empty($clean)) {
            return null;
        }
        $clean = preg_replace('/\s*([\.\!?])\s*/', '$1 ', $clean);
        $clean = ucfirst(trim($clean));
        if (!preg_match('/[\.\!\?]$/', $clean)) {
            $clean .= '.';
        }
        return $clean;
    }

    // ─── 4. Tickets similaires — GLPI d'abord, fallback PostgreSQL ─────────────
    public function findSimilar(string $query, string $category = ''): array
    {
        if (strlen($query) < 4) return [];

        // ── 1. GLPI searchItems (prioritaire)
        try {
            $glpi = app(\App\Services\GlpiService::class);

            $criteria = [
                ['field' => '1',  'searchtype' => 'contains', 'value' => $query, 'link' => 'AND'],
                ['field' => '12', 'searchtype' => 'equals',   'value' => 5,      'link' => 'AND'],
            ];

            $result = $glpi->searchItems('Ticket', $criteria, [
                'range'           => '0-4',
                'forcedisplay[0]' => 2,
                'forcedisplay[1]' => 1,
                'forcedisplay[2]' => 21,
                'forcedisplay[3]' => 24,
                'order'           => 'DESC',
            ]);

            $glpi->killSession();

            $tickets = [];
            $seen    = [];
            foreach ($result['data'] ?? [] as $item) {
                $title = trim($item['1'] ?? '');
                if (!$title) continue;

                $key = preg_replace('/\s+/', ' ', strtolower($title));
                if (isset($seen[$key])) continue;
                $seen[$key] = true;

                $desc = trim(strip_tags(html_entity_decode($item['21'] ?? '')));
                $sol  = trim(strip_tags(html_entity_decode($item['24'] ?? '')));

                if (strlen($sol) < 10) continue;

                $tickets[] = [
                    'id'          => $item['2'] ?? null,
                    'title'       => $title,
                    'description' => strlen($desc) > 5 ? \Illuminate\Support\Str::limit($desc, 150) : null,
                    'solution'    => \Illuminate\Support\Str::limit($sol, 200),
                    'source'      => 'glpi',
                ];
            }

            if (!empty($tickets)) return $tickets;

        } catch (\Exception $e) {
            Log::info('findSimilar GLPI fallback: ' . $e->getMessage());
        }

        // ── 2. Fallback base locale — recherche multi-mots intelligente
        $stopwords = ['dans', 'pour', 'avec', 'plus', 'cette', 'notre', 'depuis', 'lors', 'vers', 'tout', "l'api", "l'envoi"];
        $words = array_filter(
            array_unique(explode(' ', strtolower(preg_replace('/[^\w\s]/u', ' ', $query)))),
            fn($w) => strlen($w) > 3 && !in_array($w, $stopwords)
        );
        $words = array_slice(array_values($words), 0, 5);

        if (empty($words)) return [];

        $driver = \DB::connection()->getDriverName();

        $dbQuery = Ticket::whereIn('sync_status', ['resolved', 'closed'])
            ->whereNotNull('solution')
            ->where('solution', '!=', '')
            ->where(function ($q) use ($words, $driver) {
                foreach ($words as $word) {
                    if ($driver === 'pgsql') {
                        $q->orWhere('title',       'ilike', "%{$word}%")
                          ->orWhere('description', 'ilike', "%{$word}%");
                    } else {
                        $q->orWhereRaw('LOWER(title) LIKE ?',       ['%' . $word . '%'])
                          ->orWhereRaw('LOWER(description) LIKE ?', ['%' . $word . '%']);
                    }
                }
            });

        // If category provided, try category-filtered first, broaden if empty
        $results = collect();
        if (!empty($category)) {
            $results = (clone $dbQuery)->where('category', $category)
                ->latest('resolved_at')
                ->limit(8)
                ->get(['id', 'title', 'description', 'solution', 'category']);
        }

        if ($results->isEmpty()) {
            $results = $dbQuery->latest('resolved_at')
                ->limit(8)
                ->get(['id', 'title', 'description', 'solution', 'category']);
        }

        if ($results->isEmpty()) return [];

        $queryLower = strtolower($query);

        $scored = $results->map(function ($t) use ($words, $queryLower, $category) {
            $titleLower = strtolower($t->title);
            $descLower  = strtolower($t->description ?? '');
            $score      = 0;

            if (str_contains($titleLower, $queryLower)) $score += 10;

            foreach ($words as $w) {
                if (str_contains($titleLower, $w)) $score += 3;
                if (str_contains($descLower,  $w)) $score += 1;
            }

            // Bonus for matching category
            if (!empty($category) && $t->category === $category) {
                $score += 5;
            }

            return ['ticket' => $t, 'score' => $score];
        })
        ->filter(fn($item) => $item['score'] > 0)
        ->sortByDesc('score')
        ->take(3)
        ->values();

        $seen   = [];
        $output = [];
        foreach ($scored as $item) {
            $t   = $item['ticket'];
            $key = preg_replace('/\s+/', ' ', strtolower($t->title));
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $output[] = [
                'id'          => $t->id,
                'title'       => $t->title,
                'description' => $t->description
                    ? \Illuminate\Support\Str::limit(strip_tags($t->description), 150)
                    : null,
                'solution'    => \Illuminate\Support\Str::limit(strip_tags($t->solution), 200),
                'source'      => 'local',
            ];
        }

        return $output;
    }

    // ─── 5. Prédiction SLA ────────────────────────────────────────────────────
    public function predictSla(int $priority, string $category): array
    {
        $slaHours    = [5 => 4, 4 => 8, 3 => 24, 2 => 48, 1 => 72];
        $slaLimit    = $slaHours[$priority] ?? 24;
        $openTickets = Ticket::whereIn('sync_status', ['pending', 'in_progress'])->count();
        $hour        = (int) now()->format('H');

        $risk = 0;
        $risk += (5 - $priority) * 5;
        if ($hour >= 17) $risk += 25;
        elseif ($hour >= 15) $risk += 15;
        if ($openTickets > 10) $risk += 30;
        elseif ($openTickets > 5) $risk += 15;

        $complexity = ['incident_technique' => 5, 'integration_api' => 10, 'facturation' => 0, 'plateforme' => 5, 'paiement_mobile' => 8, 'autre' => 0];
        $risk += $complexity[$category] ?? 0;
        $risk = min(100, max(0, $risk));

        return [
            'risk_score'      => $risk,
            'breach_likely'   => $risk >= 50,
            'sla_limit_hours' => $slaLimit,
            'sla_used'        => 0,
            'recommendation'  => $risk > 70 ? 'Escalader immédiatement' : ($risk > 40 ? 'Surveiller' : 'Normal'),
        ];
    }

    // ─── 6. Rapport hebdo super admin ─────────────────────────────────────────
    public function generateWeeklyReport(array $stats): ?string
    {
        $system = "Tu es un assistant analytique pour L2T. "
                . "Tu génères un rapport hebdomadaire concis sur les tickets support. "
                . "Réponds en français, format structuré, 150 mots maximum.";

        $user = "Stats de la semaine:\n" . json_encode($stats, JSON_UNESCAPED_UNICODE);

        return $this->chat($system, $user, 400);
    }
}