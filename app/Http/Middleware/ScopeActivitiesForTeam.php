<?php

namespace App\Http\Middleware;

use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScopeActivitiesForTeam
{
    /**
     * Enforce team users to their own activity stream even if query params are tampered.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Unauthorized.');
        }

        if ($user->isAdmin() || !$user->hasRole('team')) {
            return $next($request);
        }

        $linkedTeamId = Team::query()
            ->whereRaw('LOWER(email) = ?', [strtolower((string) $user->email)])
            ->value('id');

        if (!$linkedTeamId) {
            abort(403, 'No team account is linked to this user.');
        }

        $requestedTeamId = $request->query('team_id');

        if ($requestedTeamId !== null && (int) $requestedTeamId !== (int) $linkedTeamId) {
            abort(403, 'You are not allowed to view activity for another team.');
        }

        // Force team_id regardless of any supplied value.
        $request->merge(['team_id' => (int) $linkedTeamId]);

        return $next($request);
    }
}
