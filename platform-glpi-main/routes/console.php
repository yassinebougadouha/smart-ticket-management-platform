<?php

use Illuminate\Support\Facades\Schedule;

// ─── Lecture boîte support (l2t.glpi2026@gmail.com) → création tickets ────────
// Toutes les 2 minutes — détecte rapidement les nouveaux emails clients
Schedule::command('mail:fetch-support')->everyTwoMinutes();

// ─── Auto-fermeture tickets résolus depuis 5 jours ─────────────────────────────
Schedule::command('tickets:auto-close')->daily();

// ─── Vérification breaches SLA ───y5dmou kol 1h─────────────────────────────────────────────
Schedule::command('glpi:check-sla')->hourly();

// ─── Sync utilisateurs GLPI ────bech ysancronizi les utilisateurs fi wa9t re9da (fih a9al charge).────────────────────────────────────────────────
Schedule::command('glpi:sync-users')->dailyAt('02:00');

// ─── Sync catégories GLPI ────y5dm kol youm fi 2:30AM ba3ed nos sa3a min user ────────────────────────────────────────────────
Schedule::command('glpi:sync-categories')->dailyAt('02:30');