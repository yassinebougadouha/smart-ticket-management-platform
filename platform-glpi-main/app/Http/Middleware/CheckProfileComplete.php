<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckProfileComplete
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        // Ensure admin also completes profile manually


        if (
            $user &&
            in_array($user->role, ['admin', 'super_admin', 'client']) &&
            !$user->profile_completed &&
            !$request->routeIs('profile.*') &&
            !$request->routeIs('logout') &&
            !$request->routeIs('password.*')
        ) {
            if ($user->role === 'admin') {
                $missingTeams  = empty(trim($user->teams_email ?? ''));
                $missingMobile = empty(trim($user->phone_mobile ?? ''));

                if ($missingTeams && $missingMobile) {
                    $msg = '⚠️ Complétez votre profil : téléphone mobile et email Microsoft Teams obligatoires.';
                } elseif ($missingTeams) {
                    $msg = '⚠️ Ajoutez votre email Microsoft Teams pour recevoir les notifications.';
                } else {
                    $msg = '⚠️ Ajoutez votre numéro de téléphone mobile pour compléter votre profil.';
                }
            } else {
                $msg = '⚠️ Complétez votre profil avant de continuer.';
            }

            return redirect()->route('profile.edit')
                ->with('warning', $msg);
        }

        return $next($request);
    }
}